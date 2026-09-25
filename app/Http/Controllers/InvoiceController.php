<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RentCharge;
use App\Services\Ksef\KsefClient;
use App\Services\Ksef\KsefException;
use App\Services\Ksef\KsefInvoiceImporter;
use App\Services\RentInvoiceFactory;
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

    /** Wystawienie faktury za naliczony czynsz. */
    public function store(Request $request, RentInvoiceFactory $factory)
    {
        $data = $request->validate([
            'rent_charge_id' => ['required', 'exists:rent_charges,id'],
            'issued_on' => ['nullable', 'date'],
        ], [], ['rent_charge_id' => 'naliczenie czynszu']);

        try {
            $invoice = $factory->fromRentCharge(
                RentCharge::findOrFail($data['rent_charge_id']),
                $data['issued_on'] ?? null,
            );
        } catch (RuntimeException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)
            ->with('status', "Faktura {$invoice->number} została wystawiona.");
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
