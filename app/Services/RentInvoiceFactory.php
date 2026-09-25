<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
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
    public function fromRentCharge(RentCharge $charge, ?CarbonInterface $issuedOn = null): Invoice
    {
        $settings = InvoiceSetting::current();

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

        $issuedOn = CarbonImmutable::parse($issuedOn ?? now())->startOfDay();

        if (! $issuedOn->isSameMonth(now())) {
            throw new RuntimeException('Datę wystawienia można ustawić tylko w bieżącym miesiącu.');
        }

        [$net, $vat, $gross] = $this->amounts((float) $charge->amount, (float) $settings->vat_rate, $settings->rent_is_gross);

        return DB::transaction(function () use ($charge, $settings, $issuedOn, $net, $vat, $gross) {
            $invoice = Invoice::create([
                'number' => $this->nextNumber($issuedOn),
                'tenant_id' => $charge->tenant_id,
                'unit_id' => $charge->unit_id,
                'rent_charge_id' => $charge->id,
                'issued_on' => $issuedOn->toDateString(),
                'sold_on' => $issuedOn->toDateString(),
                'due_on' => $issuedOn->addDays($settings->payment_days)->toDateString(),
                'vat_rate' => $settings->vat_rate,
                'total_net' => $net,
                'total_vat' => $vat,
                'total_gross' => $gross,
                'status' => InvoiceStatus::Draft,
            ]);

            $invoice->lines()->create([
                'position' => 1,
                'name' => $settings->rentLineDescription($charge->month),
                'unit' => 'szt.',
                'quantity' => 1,
                'unit_price_net' => $net,
                'vat_rate' => $settings->vat_rate,
                'net' => $net,
                'vat' => $vat,
                'gross' => $gross,
            ]);

            // Naliczenie czynszu przejmuje numer i termin z faktury — dzięki temu
            // lista czynszów od razu pokazuje, czym został udokumentowany.
            $charge->update([
                'invoice_number' => $invoice->number,
                'due_on' => $invoice->due_on,
            ]);

            return $invoice->load('lines', 'tenant', 'unit');
        });
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

    /**
     * Numer w formacie „3/9/2026" — kolejny w miesiącu, miesiąc bez zera wiodącego.
     * Numery zajęte poza aplikacją (np. wystawione wcześniej w KSeF) są pomijane.
     */
    private function nextNumber(CarbonInterface $issuedOn): string
    {
        $position = Invoice::query()
            ->whereYear('issued_on', $issuedOn->year)
            ->whereMonth('issued_on', $issuedOn->month)
            ->count() + 1;

        do {
            $number = $position.'/'.$issuedOn->month.'/'.$issuedOn->year;
            $position++;
        } while (Invoice::where('number', $number)->exists());

        return $number;
    }
}
