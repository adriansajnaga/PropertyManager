<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Services\Ksef\InvoiceNumbering;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Faktura za czynsz powstaje z naliczenia. Stawka w kartotece lokalu jest kwotą
 * do zapłaty (brutto), więc kwotę netto wyliczamy wstecz ze stawki VAT — tak jak
 * na fakturach wystawianych dotąd ręcznie.
 */
class RentInvoiceFactory
{
    public function __construct(private readonly InvoiceNumbering $numbering) {}

    /**
     * Wartości do wypełnienia formularza. Nic jeszcze nie zapisujemy — numer i kwoty
     * użytkownik może poprawić przed wystawieniem.
     *
     * @return array<string, mixed>
     */
    public function draft(
        RentCharge $charge,
        ?CarbonInterface $issuedOn = null,
        DocumentType $type = DocumentType::Invoice,
    ): array {
        $settings = InvoiceSetting::current();

        $this->guard($charge, $settings, $type);

        $issuedOn = CarbonImmutable::parse($issuedOn ?? now())->startOfDay();

        if (! $issuedOn->isSameMonth(now())) {
            throw new RuntimeException('Datę wystawienia można ustawić tylko w bieżącym miesiącu.');
        }

        // Rachunek imienny jest bez podatku — kwota z kartoteki jest kwotą do zapłaty.
        $vatRate = $type === DocumentType::Receipt ? 0.0 : (float) $settings->vat_rate;

        [$net, $vat, $gross] = $this->amounts((float) $charge->amount, $vatRate, $settings->rent_is_gross);
        $numbering = $this->numbering->next($issuedOn, $type);

        return [
            'document_type' => $type->value,
            'rent_charge_id' => $charge->id,
            'tenant_id' => $charge->tenant_id,
            'unit_id' => $charge->unit_id,
            'number' => $numbering['number'],
            'number_warning' => $numbering['warning'],
            'issued_on' => $issuedOn->toDateString(),
            'sold_on' => $issuedOn->toDateString(),
            'due_on' => $issuedOn->addDays($settings->payment_days)->toDateString(),
            'vat_rate' => $vatRate,
            'lines' => [[
                'name' => $settings->rentLineDescription($charge->month),
                'unit' => 'szt.',
                'quantity' => 1,
                'unit_price_net' => $net,
            ]],
            'total_net' => $net,
            'total_vat' => $vat,
            'total_gross' => $gross,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Invoice
    {
        $type = DocumentType::from($data['document_type'] ?? DocumentType::Invoice->value);
        $charge = isset($data['rent_charge_id']) ? RentCharge::find($data['rent_charge_id']) : null;

        if ($charge !== null) {
            $this->guard($charge, InvoiceSetting::current(), $type);
        }

        $vatRate = (float) $data['vat_rate'];

        // Pozycje liczymy osobno, a dokument sumuje ich wartości.
        $lines = collect($data['lines'])
            ->values()
            ->map(function (array $line, int $index) use ($vatRate) {
                $net = round((float) $line['unit_price_net'] * (float) ($line['quantity'] ?? 1), 2);
                $vat = round($net * $vatRate / 100, 2);

                return [
                    'position' => $index + 1,
                    'name' => $line['name'],
                    'unit' => $line['unit'] ?? 'szt.',
                    'quantity' => (float) ($line['quantity'] ?? 1),
                    'unit_price_net' => (float) $line['unit_price_net'],
                    'vat_rate' => $vatRate,
                    'net' => $net,
                    'vat' => $vat,
                    'gross' => round($net + $vat, 2),
                ];
            });

        $net = round($lines->sum('net'), 2);
        $vat = round($lines->sum('vat'), 2);

        return DB::transaction(function () use ($data, $charge, $lines, $net, $vat, $vatRate, $type) {
            $invoice = Invoice::create([
                'number' => $data['number'],
                'document_type' => $type,
                'tenant_id' => $data['tenant_id'] ?? $charge?->tenant_id,
                'unit_id' => $data['unit_id'] ?? $charge?->unit_id,
                'rent_charge_id' => $charge?->id,
                'issued_on' => $data['issued_on'],
                'sold_on' => $data['sold_on'],
                'due_on' => $data['due_on'],
                'vat_rate' => $vatRate,
                'total_net' => $net,
                'total_vat' => $vat,
                'total_gross' => round($net + $vat, 2),
                'status' => InvoiceStatus::Draft,
            ]);

            foreach ($lines as $line) {
                $invoice->lines()->create($line);
            }

            // Naliczenie czynszu przejmuje numer i termin z faktury — dzięki temu
            // lista czynszów od razu pokazuje, czym został udokumentowany.
            $charge?->update([
                'invoice_number' => $invoice->number,
                'due_on' => $invoice->due_on,
            ]);

            return $invoice->load('lines', 'tenant', 'unit');
        });
    }

    public function fromRentCharge(
        RentCharge $charge,
        ?CarbonInterface $issuedOn = null,
        DocumentType $type = DocumentType::Invoice,
    ): Invoice {
        return $this->store($this->draft($charge, $issuedOn, $type));
    }

    private function guard(RentCharge $charge, InvoiceSetting $settings, DocumentType $type): void
    {
        if ($type === DocumentType::Receipt && ! $settings->receiptIsConfigured()) {
            throw new RuntimeException('Uzupełnij dane wystawcy rachunku w ustawieniach faktur.');
        }

        if ($type === DocumentType::Invoice && ! $settings->isConfigured()) {
            throw new RuntimeException('Uzupełnij dane sprzedawcy w ustawieniach faktur.');
        }

        if ($charge->tenant === null) {
            throw new RuntimeException('Naliczenie nie ma przypisanego najemcy — faktury nie ma komu wystawić.');
        }

        if ($charge->invoice()->exists()) {
            throw new RuntimeException('Do tego naliczenia wystawiono już dokument.');
        }

        // Faktur nie wystawiamy wstecz: w aplikacji powstają wyłącznie dokumenty
        // za bieżący miesiąc, a zaległe pobieramy z KSeF.
        if (! $charge->month->isSameMonth(now())) {
            throw new RuntimeException(sprintf(
                'Czynsz za %s jest spoza bieżącego miesiąca — taki dokument pobierz z KSeF zamiast wystawiać ponownie.',
                $charge->month->isoFormat('MMMM YYYY'),
            ));
        }
    }

    /**
     * @return array{0: float, 1: float, 2: float} netto, VAT, brutto
     */
    private function amounts(float $amount, float $vatRate, bool $isGross): array
    {
        if ($isGross) {
            $net = round($amount / (1 + $vatRate / 100), 2);

            return [$net, round($amount - $net, 2), round($amount, 2)];
        }

        $vat = round($amount * $vatRate / 100, 2);

        return [round($amount, 2), $vat, round($amount + $vat, 2)];
    }
}
