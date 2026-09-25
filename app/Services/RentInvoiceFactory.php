<?php

namespace App\Services;

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
    public function draft(RentCharge $charge, ?CarbonInterface $issuedOn = null): array
    {
        $settings = InvoiceSetting::current();

        $this->guard($charge, $settings);

        $issuedOn = CarbonImmutable::parse($issuedOn ?? now())->startOfDay();

        if (! $issuedOn->isSameMonth(now())) {
            throw new RuntimeException('Datę wystawienia można ustawić tylko w bieżącym miesiącu.');
        }

        [$net, $vat, $gross] = $this->amounts((float) $charge->amount, (float) $settings->vat_rate, $settings->rent_is_gross);
        $numbering = $this->numbering->next($issuedOn);

        return [
            'rent_charge_id' => $charge->id,
            'tenant_id' => $charge->tenant_id,
            'unit_id' => $charge->unit_id,
            'number' => $numbering['number'],
            'number_warning' => $numbering['warning'],
            'issued_on' => $issuedOn->toDateString(),
            'sold_on' => $issuedOn->toDateString(),
            'due_on' => $issuedOn->addDays($settings->payment_days)->toDateString(),
            'vat_rate' => (float) $settings->vat_rate,
            'line_name' => $settings->rentLineDescription($charge->month),
            'unit_price_net' => $net,
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
        $charge = isset($data['rent_charge_id']) ? RentCharge::find($data['rent_charge_id']) : null;

        if ($charge !== null) {
            $this->guard($charge, InvoiceSetting::current());
        }

        $net = round((float) $data['unit_price_net'] * (float) ($data['quantity'] ?? 1), 2);
        $vatRate = (float) $data['vat_rate'];
        $vat = round($net * $vatRate / 100, 2);

        return DB::transaction(function () use ($data, $charge, $net, $vat, $vatRate) {
            $invoice = Invoice::create([
                'number' => $data['number'],
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

            $invoice->lines()->create([
                'position' => 1,
                'name' => $data['line_name'],
                'unit' => $data['unit'] ?? 'szt.',
                'quantity' => (float) ($data['quantity'] ?? 1),
                'unit_price_net' => (float) $data['unit_price_net'],
                'vat_rate' => $vatRate,
                'net' => $net,
                'vat' => $vat,
                'gross' => round($net + $vat, 2),
            ]);

            // Naliczenie czynszu przejmuje numer i termin z faktury — dzięki temu
            // lista czynszów od razu pokazuje, czym został udokumentowany.
            $charge?->update([
                'invoice_number' => $invoice->number,
                'due_on' => $invoice->due_on,
            ]);

            return $invoice->load('lines', 'tenant', 'unit');
        });
    }

    public function fromRentCharge(RentCharge $charge, ?CarbonInterface $issuedOn = null): Invoice
    {
        return $this->store($this->draft($charge, $issuedOn));
    }

    private function guard(RentCharge $charge, InvoiceSetting $settings): void
    {
        if (! $settings->isConfigured()) {
            throw new RuntimeException('Uzupełnij dane sprzedawcy w ustawieniach faktur.');
        }

        if ($charge->tenant === null) {
            throw new RuntimeException('Naliczenie nie ma przypisanego najemcy — faktury nie ma komu wystawić.');
        }

        if ($charge->invoice()->exists()) {
            throw new RuntimeException('Do tego naliczenia wystawiono już fakturę.');
        }

        // Faktur nie wystawiamy wstecz: w aplikacji powstają wyłącznie dokumenty
        // za bieżący miesiąc, a zaległe pobieramy z KSeF.
        if (! $charge->month->isSameMonth(now())) {
            throw new RuntimeException(sprintf(
                'Czynsz za %s jest spoza bieżącego miesiąca — taką fakturę pobierz z KSeF zamiast wystawiać ponownie.',
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
