<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\RentCharge;
use App\Services\RentInvoiceFactory;
use Illuminate\Http\Request;
use RuntimeException;

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
            'uncharged' => RentCharge::with('unit', 'tenant')
                ->whereDoesntHave('invoice')
                ->whereNotNull('tenant_id')
                ->orderByDesc('month')
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
