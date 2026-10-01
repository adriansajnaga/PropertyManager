<?php

namespace App\Services\Ksef;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\KsefSetting;
use App\Models\RentCharge;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use Throwable;

/**
 * Pobiera z KSeF faktury wystawione najemcom. Dokumenty sprzed wdrożenia aplikacji
 * powstały w aplikacji podatnika, więc to KSeF jest ich źródłem prawdy — my je tylko
 * odwzorowujemy, żeby kartoteka najemcy pokazywała pełną historię.
 */
class KsefInvoiceImporter
{
    /** Schemat FA(3) — wzór bieżący; starsze faktury w KSeF mają własne. */
    private const FA3_NAMESPACE = 'http://crd.gov.pl/wzor/2025/06/25/13775/';

    /** KSeF przyjmuje zapytania o zakres nie dłuższy niż 100 dni. */
    private const MAX_RANGE_DAYS = 100;

    public function __construct(private readonly KsefClient $client) {}

    /**
     * @return array{imported: int, known: int, foreign: int, unreadable: array<int, string>}
     */
    public function import(CarbonInterface $from, CarbonInterface $to): array
    {
        $tenants = $this->tenantsByNip();
        $summary = ['imported' => 0, 'known' => 0, 'foreign' => 0, 'unreadable' => []];

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

                if ($known = Invoice::where('ksef_number', $ksefNumber)->first()) {
                    $this->linkRentCharge($known);
                    $summary['known']++;

                    continue;
                }

                $invoice = $this->readDocument($ksefNumber, $tenants[$buyerNip], $this->issuedHere($metadata));

                if ($invoice === null) {
                    $summary['unreadable'][] = $ksefNumber;

                    continue;
                }

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

    /** Odczyt pól faktury po ścieżce XPath. */
    private function reader(string $xml): callable
    {
        return $this->readerFor($this->parse($xml));
    }

    /**
     * Faktury sprzed FA(3) mają własną przestrzeń nazw — FA(1) i FA(2) różnią się
     * od bieżącego wzoru adresem, a nie nazwami pól. Bierzemy ją więc z samego
     * dokumentu; wpisana na sztywno sprawiała, że z takiej faktury nie dawało się
     * odczytać niczego i do bazy trafiał pusty wpis.
     */
    private function parse(string $xml): SimpleXMLElement
    {
        $document = new SimpleXMLElement($xml);
        $namespaces = $document->getDocNamespaces();

        $document->registerXPathNamespace('fa', $namespaces[''] ?? self::FA3_NAMESPACE);

        return $document;
    }

    private function readerFor(SimpleXMLElement $document): callable
    {
        return function (string $path) use ($document): ?string {
            $found = $document->xpath($path);

            return $found ? trim((string) $found[0]) : null;
        };
    }

    /**
     * Faktura wystawiona w aplikacji, która czeka na potwierdzenie z KSeF —
     * import ma ją uzupełnić, a nie dopisać obok drugiej o tym samym numerze.
     */
    private function issuedHere(array $metadata): ?Invoice
    {
        $number = trim((string) ($metadata['invoiceNumber'] ?? ''));

        if ($number === '') {
            return null;
        }

        return Invoice::query()
            ->where('document_type', DocumentType::Invoice)
            ->where('number', $number)
            ->whereNull('ksef_number')
            ->first();
    }

    /**
     * Uszkodzony albo nieznany dokument nie może przerwać całego pobierania ani
     * trafić do bazy jako pusty wpis — zgłaszamy go po numerze KSeF.
     */
    private function readDocument(string $ksefNumber, Tenant $tenant, ?Invoice $existing): ?Invoice
    {
        try {
            return $this->store($ksefNumber, $tenant, $existing);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /** Zwraca null, gdy dokumentu nie da się odczytać — wtedy nic nie zapisujemy. */
    private function store(string $ksefNumber, Tenant $tenant, ?Invoice $existing = null): ?Invoice
    {
        $xml = $this->client->downloadInvoice($ksefNumber);
        $document = $this->parse($xml);
        $value = $this->readerFor($document);

        // Numer faktury jest w każdym wzorze. Jego brak znaczy, że to nie jest
        // faktura, którą rozumiemy — lepiej zgłosić dokument, niż zapisać pustkę.
        if ($value('//fa:Fa/fa:P_2') === null) {
            return null;
        }

        $issuedOn = CarbonImmutable::parse($value('//fa:Fa/fa:P_1') ?? now()->toDateString());
        $soldOn = CarbonImmutable::parse($value('//fa:Fa/fa:P_6') ?? $issuedOn->toDateString());
        $due = $value('//fa:Platnosc/fa:TerminPlatnosci/fa:Termin');

        $net = (float) ($value('//fa:Fa/fa:P_13_1') ?? 0);
        $vat = (float) ($value('//fa:Fa/fa:P_14_1') ?? 0);
        $gross = (float) ($value('//fa:Fa/fa:P_15') ?? $net + $vat);

        return DB::transaction(function () use ($document, $value, $ksefNumber, $tenant, $existing, $issuedOn, $soldOn, $due, $net, $vat, $gross, $xml) {
            $charge = $existing?->rentCharge ?? $this->matchingRentCharge($tenant, $soldOn);

            $attributes = [
                'number' => $value('//fa:Fa/fa:P_2'),
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
                // Dokument wystawiony u nas został wysłany; obcy — pobrany.
                'status' => $existing ? InvoiceStatus::Sent : InvoiceStatus::Imported,
                'ksef_number' => $ksefNumber,
                'ksef_environment' => KsefSetting::current()->environment,
                'ksef_sent_at' => $issuedOn,
                'xml' => $xml,
            ];

            if ($existing === null) {
                $invoice = Invoice::create($attributes);
            } else {
                $existing->update($attributes);
                $existing->lines()->delete();
                $invoice = $existing;
            }

            foreach ($document->xpath('//fa:FaWiersz') ?: [] as $position => $row) {
                $row->registerXPathNamespace('fa', $document->getDocNamespaces()[''] ?? self::FA3_NAMESPACE);
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

    /**
     * Faktura pobrana wcześniej mogła nie mieć do czego się przypiąć — naliczenie
     * bywało wtedy bez najemcy. Przy kolejnym pobraniu próbujemy jeszcze raz.
     */
    private function linkRentCharge(Invoice $invoice): void
    {
        if ($invoice->rent_charge_id !== null || $invoice->tenant === null) {
            return;
        }

        $charge = $this->matchingRentCharge($invoice->tenant, $invoice->sold_on);

        if ($charge === null) {
            return;
        }

        $invoice->update(['rent_charge_id' => $charge->id, 'unit_id' => $charge->unit_id]);

        $charge->update(['invoice_number' => $invoice->number, 'due_on' => $invoice->due_on]);
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
