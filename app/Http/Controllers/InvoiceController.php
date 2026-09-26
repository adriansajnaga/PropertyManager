<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Mail\InvoiceMail;
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
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
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

    public function show(Invoice $invoice, InvoiceQrCode $qr)
    {
        $invoice->load('lines', 'tenant', 'unit', 'rentCharge');

        return view('invoices.show', [
            'invoice' => $invoice,
            'verificationUrl' => $qr->url($invoice),
        ]);
    }

    /** Formularz wystawienia faktury — wypełniony danymi z naliczenia, ale edytowalny. */
    public function create(Request $request, RentInvoiceFactory $factory)
    {
        $charge = RentCharge::with('tenant', 'unit')->findOrFail($request->integer('rent_charge_id'));
        $type = DocumentType::tryFrom((string) $request->query('document_type')) ?? DocumentType::Invoice;

        try {
            $draft = $factory->draft($charge, null, $type);
        } catch (RuntimeException $e) {
            return redirect()->route('invoices.index')->withErrors(['invoice' => $e->getMessage()]);
        }

        return view('invoices.create', ['charge' => $charge, 'draft' => $draft, 'type' => $type]);
    }

    public function store(Request $request, RentInvoiceFactory $factory)
    {
        $data = $request->validate([
            'rent_charge_id' => ['nullable', 'exists:rent_charges,id'],
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            // Numer musi być wolny w obrębie swojej serii — faktur albo rachunków.
            'number' => [
                'required', 'string', 'max:64',
                Rule::unique('invoices', 'number')->where('document_type', $request->input('document_type')),
            ],
            'issued_on' => ['required', 'date'],
            'sold_on' => ['required', 'date'],
            'due_on' => ['required', 'date', 'after_or_equal:issued_on'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.name' => ['required', 'string', 'max:255'],
            'lines.*.unit' => ['required', 'string', 'max:20'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'lines.*.unit_price_net' => ['required', 'numeric', 'min:0'],
        ], [], [
            'number' => 'numer faktury',
            'issued_on' => 'data wystawienia',
            'sold_on' => 'data sprzedaży',
            'due_on' => 'termin płatności',
            'vat_rate' => 'stawka VAT',
            'lines' => 'pozycje',
            'lines.*.name' => 'nazwa pozycji',
            'lines.*.quantity' => 'ilość',
            'lines.*.unit_price_net' => 'cena netto',
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
        if (! $invoice->goesToKsef()) {
            return back()->withErrors(['invoice' => 'Rachunek nie jest wysyłany do KSeF — zostaje w aplikacji.']);
        }

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
        return $this->pdfFor($invoice, $qr)
            ->download(InvoiceSetting::current()->documentFileName($invoice));
    }

    /** Wysłanie dokumentu najemcy e-mailem, z PDF-em w załączniku. */
    public function email(Request $request, Invoice $invoice, InvoiceQrCode $qr)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
        ], [], [
            'email' => 'adres e-mail',
            'subject' => 'temat',
            'body' => 'treść wiadomości',
        ]);

        $filename = InvoiceSetting::current()->documentFileName($invoice);

        try {
            Mail::to($data['email'])->send(new InvoiceMail(
                $invoice,
                $this->pdfFor($invoice, $qr)->output(),
                $filename,
                $data['body'],
                $data['subject'],
            ));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['email' => 'Nie udało się wysłać: '.$e->getMessage()]);
        }

        $invoice->forceFill(['emailed_at' => now(), 'emailed_to' => $data['email']])->save();

        return back()->with('status', "{$invoice->title()} wysłana na adres {$data['email']}.");
    }

    private function pdfFor(Invoice $invoice, InvoiceQrCode $qr)
    {
        $invoice->load('lines', 'tenant', 'unit');
        $url = $invoice->goesToKsef() ? $qr->url($invoice) : null;

        // Jeden szablon obsługuje oba dokumenty — różnią się kolumną VAT i wystawcą.
        $environment = \App\Models\KsefSetting::current()->environment;

        return Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'settings' => InvoiceSetting::current(),
            'qrUrl' => $url,
            'qrCode' => $url ? $qr->dataUri($url, 340) : null,
            // KSeF znaczy wizualizacje ze środowisk nieprodukcyjnych.
            'environmentNote' => $environment && ! $environment->isProduction()
                ? 'Środowisko '.($environment === \App\Enums\KsefEnvironment::Demo ? 'Demo' : 'Testowe')
                : null,
        ])->setPaper('a4');
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
