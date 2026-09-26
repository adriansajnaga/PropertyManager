<?php

namespace App\Services\Ksef;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\KsefSetting;
use App\Models\RentCharge;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

/**
 * Pobiera z KSeF faktury wystawione najemcom. Dokumenty sprzed wdrożenia aplikacji
 * powstały w aplikacji podatnika, więc to KSeF jest ich źródłem prawdy — my je tylko
 * odwzorowujemy, żeby kartoteka najemcy pokazywała pełną historię.
 */
class KsefInvoiceImporter
{
    /** Schemat FA(3) — wszystkie pola faktury żyją w tej przestrzeni nazw. */
    private const FA3_NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** KSeF przyjmuje zapytania o zakres nie dłuższy niż 100 dni. */
    private const MAX_RANGE_DAYS = 100;

    public function __construct(private readonly KsefClient $client) {}

    /**
     * @return array{imported: int, known: int, foreign: int}
     */
    public function import(CarbonInterface $from, CarbonInterface $to): array
    {
        $tenants = $this->tenantsByNip();
        $summary = ['imported' => 0, 'known' => 0, 'foreign' => 0];

        foreach ($this->windows($from, $to) as [$windowFrom, $windowTo]) {
            $this->importWindow($windowFrom, $windowTo, $tenants, $summary);
        }

        return $summary;
    }

    /**
     * @param  array<string, Tenant>  $tenants
     * @param  array<string, int>  $summary
     */
    private function importWindow(CarbonInterface $from, CarbonInterface $to, array $tenants, array &$summary): void
    {
        $offset = 0;

        do {
            $page = $this->client->queryInvoiceMetadata($from, $to, 'Subject1', $offset);

            foreach ($page['invoices'] as $metadata) {
                $ksefNumber = $metadata['ksefNumber'] ?? null;
                $buyerNip = $this->digits($metadata['buyer']['identifier']['value'] ?? null);

                if ($ksefNumber === null) {
                    continue;
                }

                // Interesują nas wyłącznie faktury wystawione naszym najemcom.
                if ($buyerNip === null || ! isset($tenants[$buyerNip])) {
                    $summary['foreign']++;

                    continue;
                }

                if (Invoice::where('ksef_number', $ksefNumber)->exists()) {
                    $summary['known']++;

                    continue;
                }

                $this->store($ksefNumber, $tenants[$buyerNip]);
                $summary['imported']++;
            }

            $offset += count($page['invoices']);
        } while ($page['hasMore'] && $page['invoices'] !== []);
    }

    /**
     * Dzieli żądany okres na kawałki mieszczące się w limicie KSeF.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    private function windows(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::parse($from);
        $end = CarbonImmutable::parse($to);
        $windows = [];

        while ($start->lte($end)) {
            $stop = $start->addDays(self::MAX_RANGE_DAYS - 1)->endOfDay();
            $windows[] = [$start, $stop->gt($end) ? $end : $stop];
            $start = $stop->addSecond();
        }

        return $windows;
    }

    /**
     * Potwierdzenie wysłanej faktury: ściągamy z KSeF to, co tam rzeczywiście
     * leży, i tym nadpisujemy zapis w bazie. Dzięki temu lokalna kartoteka jest
     * odbiciem KSeF, a nie tym, co aplikacja sama sobie dopisała po wysyłce.
     */
    public function confirm(Invoice $invoice): Invoice
    {
        if (blank($invoice->ksef_number)) {
            return $invoice;
        }

        $xml = $this->client->downloadInvoice($invoice->ksef_number);
        $value = $this->reader($xml);

        $issuedOn = CarbonImmutable::parse($value('//fa:Fa/fa:P_1') ?? $invoice->issued_on->toDateString());
        $due = $value('//fa:Platnosc/fa:TerminPlatnosci/fa:Termin');

        $invoice->update([
            'number' => $value('//fa:Fa/fa:P_2') ?? $invoice->number,
            'issued_on' => $issuedOn->toDateString(),
            'sold_on' => CarbonImmutable::parse($value('//fa:Fa/fa:P_6') ?? $issuedOn->toDateString())->toDateString(),
            'due_on' => $due ? CarbonImmutable::parse($due)->toDateString() : $invoice->due_on->toDateString(),
            'total_net' => (float) ($value('//fa:Fa/fa:P_13_1') ?? $invoice->total_net),
            'total_vat' => (float) ($value('//fa:Fa/fa:P_14_1') ?? $invoice->total_vat),
            'total_gross' => (float) ($value('//fa:Fa/fa:P_15') ?? $invoice->total_gross),
            'xml' => $xml,
        ]);

        return $invoice->refresh();
    }

    /** Odczyt pól FA(3) po ścieżce XPath. */
    private function reader(string $xml): callable
    {
        $document = new SimpleXMLElement($xml);
        $document->registerXPathNamespace('fa', self::FA3_NAMESPACE);

        return function (string $path) use ($document): ?string {
            $found = $document->xpath($path);

            return $found ? trim((string) $found[0]) : null;
        };
    }

    private function store(string $ksefNumber, Tenant $tenant): Invoice
    {
        $xml = $this->client->downloadInvoice($ksefNumber);
        $document = new SimpleXMLElement($xml);
        $document->registerXPathNamespace('fa', self::FA3_NAMESPACE);

        $value = function (string $path) use ($document): ?string {
            $found = $document->xpath($path);

            return $found ? trim((string) $found[0]) : null;
        };

        $issuedOn = CarbonImmutable::parse($value('//fa:Fa/fa:P_1') ?? now()->toDateString());
        $soldOn = CarbonImmutable::parse($value('//fa:Fa/fa:P_6') ?? $issuedOn->toDateString());
        $due = $value('//fa:Platnosc/fa:TerminPlatnosci/fa:Termin');

        $net = (float) ($value('//fa:Fa/fa:P_13_1') ?? 0);
        $vat = (float) ($value('//fa:Fa/fa:P_14_1') ?? 0);
        $gross = (float) ($value('//fa:Fa/fa:P_15') ?? $net + $vat);

        return DB::transaction(function () use ($document, $value, $ksefNumber, $tenant, $issuedOn, $soldOn, $due, $net, $vat, $gross, $xml) {
            $charge = $this->matchingRentCharge($tenant, $soldOn);

            $invoice = Invoice::create([
                'number' => $value('//fa:Fa/fa:P_2') ?? $ksefNumber,
                'tenant_id' => $tenant->id,
                'unit_id' => $charge?->unit_id,
                'rent_charge_id' => $charge?->id,
                'issued_on' => $issuedOn->toDateString(),
                'sold_on' => $soldOn->toDateString(),
                'due_on' => $due ? CarbonImmutable::parse($due)->toDateString() : $issuedOn->toDateString(),
                'vat_rate' => $net > 0 ? round($vat / $net * 100) : 0,
                'total_net' => $net,
                'total_vat' => $vat,
                'total_gross' => $gross,
                'status' => InvoiceStatus::Imported,
                'ksef_number' => $ksefNumber,
                'ksef_environment' => KsefSetting::current()->environment,
                'ksef_sent_at' => $issuedOn,
                'xml' => $xml,
            ]);

            foreach ($document->xpath('//fa:FaWiersz') ?: [] as $position => $row) {
                $row->registerXPathNamespace('fa', self::FA3_NAMESPACE);
                $line = fn (string $tag) => trim((string) ($row->xpath('fa:'.$tag)[0] ?? ''));

                $lineNet = (float) ($line('P_11') ?: 0);
                $lineRate = (float) ($line('P_12') ?: 0);
                $lineVat = round($lineNet * $lineRate / 100, 2);

                $invoice->lines()->create([
                    'position' => (int) ($line('NrWierszaFa') ?: $position + 1),
                    'name' => $line('P_7') ?: 'Pozycja faktury',
                    'unit' => $line('P_8A') ?: 'szt.',
                    'quantity' => (float) ($line('P_8B') ?: 1),
                    'unit_price_net' => (float) ($line('P_9A') ?: $lineNet),
                    'vat_rate' => $lineRate,
                    'net' => $lineNet,
                    'vat' => $lineVat,
                    'gross' => round($lineNet + $lineVat, 2),
                ]);
            }

            // Zaległa faktura domyka naliczenie czynszu: numer i termin trafiają na listę czynszów.
            $charge?->update([
                'invoice_number' => $invoice->number,
                'due_on' => $invoice->due_on,
            ]);

            return $invoice;
        });
    }

    /** Naliczenie czynszu za miesiąc sprzedaży, o ile nie ma jeszcze faktury. */
    private function matchingRentCharge(Tenant $tenant, CarbonInterface $soldOn): ?RentCharge
    {
        return RentCharge::query()
            ->where('tenant_id', $tenant->id)
            ->whereDoesntHave('invoice')
            ->whereBetween('month', [
                CarbonImmutable::parse($soldOn)->startOfMonth()->toDateString(),
                CarbonImmutable::parse($soldOn)->endOfMonth()->toDateString(),
            ])
            ->first();
    }

    /**
     * @return array<string, Tenant>
     */
    private function tenantsByNip(): array
    {
        return Tenant::query()
            ->whereNotNull('nip')
            ->get()
            ->mapWithKeys(fn (Tenant $tenant) => [$this->digits($tenant->nip) ?? '' => $tenant])
            ->all();
    }

    private function digits(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits === '' ? null : $digits;
    }
}
