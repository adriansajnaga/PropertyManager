<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Services\Ksef\KsefClient;
use App\Services\Ksef\KsefException;
use App\Services\Ksef\KsefInvoiceImporter;
use App\Services\Ksef\InvoiceQrCode;
use App\Services\Ksef\KsefInvoiceSender;
use App\Services\RentInvoiceFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $invoices = Invoice::with('tenant', 'unit')
            ->whereYear('issued_on', $year)
            ->orderByDesc('issued_on')
            ->orderByDesc('id')
            ->get();

        return view('invoices.index', [
            'year' => $year,
            'years' => $this->years($year),
            'invoices' => $invoices,
            'netTotal' => $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total_net),
            'grossTotal' => $invoices->sum(fn (Invoice $invoice) => (float) $invoice->total_gross),
            // Wystawiamy tylko za bieżący miesiąc — starsze naliczenia mają swoje
            // faktury w KSeF i stamtąd je pobieramy.
            'uncharged' => RentCharge::with('unit', 'tenant')
                ->whereDoesntHave('invoice')
                ->whereNotNull('tenant_id')
                ->whereBetween('month', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
                ->orderBy('unit_id')
                ->get(),
        ]);
    }

    public function show(Invoice $invoice)
    {
        return view('invoices.show', [
            'invoice' => $invoice->load('lines', 'tenant', 'unit', 'rentCharge'),
        ]);
    }

    /** Formularz wystawienia faktury — wypełniony danymi z naliczenia, ale edytowalny. */
    public function create(Request $request, RentInvoiceFactory $factory)
    {
        $charge = RentCharge::with('tenant', 'unit')->findOrFail($request->integer('rent_charge_id'));

        try {
            $draft = $factory->draft($charge);
        } catch (RuntimeException $e) {
            return redirect()->route('invoices.index')->withErrors(['invoice' => $e->getMessage()]);
        }

        return view('invoices.create', ['charge' => $charge, 'draft' => $draft]);
    }

    public function store(Request $request, RentInvoiceFactory $factory)
    {
        $data = $request->validate([
            'rent_charge_id' => ['nullable', 'exists:rent_charges,id'],
            'number' => ['required', 'string', 'max:64', 'unique:invoices,number'],
            'issued_on' => ['required', 'date'],
            'sold_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'line_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'numeric', 'min:0.0001'],
            'unit_price_net' => ['required', 'numeric', 'min:0'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [], [
            'number' => 'numer faktury',
            'issued_on' => 'data wystawienia',
            'sold_on' => 'data sprzedaży',
            'due_on' => 'termin płatności',
            'line_name' => 'nazwa pozycji',
            'unit_price_net' => 'cena netto',
            'vat_rate' => 'stawka VAT',
        ]);

        try {
            $invoice = $factory->store($data);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', "Faktura {$invoice->number} została wystawiona. Możesz ją teraz wysłać do KSeF.");
    }

    /** Wysyłka faktury do KSeF sesją interaktywną. */
    public function send(Invoice $invoice, KsefInvoiceSender $sender)
    {
        try {
            $invoice = KsefClient::wrapConnectionErrors(fn () => $sender->send($invoice));
        } catch (KsefException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['invoice' => 'Wysyłka do KSeF nie powiodła się: '.$e->getMessage()]);
        }

        return back()->with('status', "Faktura {$invoice->number} przyjęta przez KSeF — numer {$invoice->ksef_number}.");
    }

    /** Pobranie z KSeF faktur wystawionych najemcom — dokumentów sprzed wdrożenia aplikacji. */
    public function import(Request $request)
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ], [], ['from' => 'data od', 'to' => 'data do']);

        try {
            $summary = KsefClient::wrapConnectionErrors(fn () => app(KsefInvoiceImporter::class)->import(
                CarbonImmutable::parse($data['from'])->startOfDay(),
                CarbonImmutable::parse($data['to'])->endOfDay(),
            ));
        } catch (KsefException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['invoice' => 'Import z KSeF nie powiódł się: '.$e->getMessage()]);
        }

        return back()->with('status', sprintf(
            'Pobrano z KSeF: %d nowych faktur, %d było już w aplikacji, %d pominięto (nabywca nie jest najemcą).',
            $summary['imported'],
            $summary['known'],
            $summary['foreign'],
        ));
    }

    /** Wizualizacja faktury w PDF, z kodem QR weryfikującym ją w KSeF. */
    public function pdf(Invoice $invoice, InvoiceQrCode $qr)
    {
        $invoice->load('lines', 'tenant', 'unit');
        $url = $qr->url($invoice);

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'settings' => InvoiceSetting::current(),
            'qrUrl' => $url,
            'qrCode' => $url ? $qr->dataUri($url) : null,
        ])->setPaper('a4')->download('faktura-'.str($invoice->number)->slug().'.pdf');
    }

    public function destroy(Invoice $invoice)
    {
        if ($invoice->isInKsef()) {
            return back()->withErrors(['invoice' => 'Faktury wysłanej do KSeF nie można usunąć.']);
        }

        $number = $invoice->number;
        $invoice->rentCharge?->update(['invoice_number' => null]);
        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('status', "Faktura {$number} została usunięta.");
    }

    /**
     * @return array<int, int>
     */
    private function years(int $selected): array
    {
        return Invoice::query()
            ->pluck('issued_on')
            ->map(fn ($date) => (int) substr((string) $date, 0, 4))
            ->toBase()
            ->push($selected)
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
