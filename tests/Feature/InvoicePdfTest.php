<?php

namespace Tests\Feature;

use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ksef\InvoiceQrCode;
use App\Services\RentInvoiceFactory;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakesKsefApi;
use Tests\TestCase;

/**
 * Wizualizacja faktury w PDF wraz z kodem QR, którym każdy może sprawdzić
 * w KSeF, czy dokument tam jest i czy nie został zmieniony.
 */
class InvoicePdfTest extends TestCase
{
    use FakesKsefApi;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-26');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->admin()->create());
        $this->configureKsef();

        InvoiceSetting::create([
            'seller_name' => 'ASCOMM Adrian Sajnaga',
            'seller_nip' => '8792451081',
            'seller_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'seller_address_l2' => '87-100 Toruń',
            'bank_account' => '87 1160 2202 0000 0002 5233 8336',
            'issue_place' => 'Toruń',
            'payment_days' => 7,
            'vat_rate' => 23,
            'rent_is_gross' => true,
        ]);

        Unit::where('description', 'Lokal 12')->firstOrFail()
            ->currentTenant()
            ->update(['nip' => '8792688305']);
    }

    private function invoice()
    {
        $unit = Unit::where('description', 'Lokal 12')->firstOrFail();

        $charge = RentCharge::create([
            'unit_id' => $unit->id,
            'tenant_id' => $unit->currentTenant()->id,
            'month' => '2026-09-01',
            'amount' => 2214,
        ]);

        return app(RentInvoiceFactory::class)->fromRentCharge($charge);
    }

    public function test_the_pdf_is_generated_and_fits_on_one_page(): void
    {
        $invoice = $this->invoice();

        $pdf = $this->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->getContent();

        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page[^s]/', $pdf));
    }

    public function test_the_visualisation_carries_the_invoice_data(): void
    {
        $invoice = $this->invoice();

        $html = view('pdf.invoice', [
            'invoice' => $invoice->load('lines', 'tenant'),
            'settings' => InvoiceSetting::current(),
            'qrUrl' => null,
            'qrCode' => null,
        ])->render();

        $this->assertStringContainsString('Faktura 1/9/2026', $html);
        $this->assertStringContainsString('ASCOMM Adrian Sajnaga', $html);
        $this->assertStringContainsString('Czynsz wrzesień 2026', $html);
        $this->assertStringContainsString('1 800,00', $html);
        $this->assertStringContainsString('414,00', $html);
        $this->assertStringContainsString('2 214,00', $html);
        $this->assertStringContainsString('87 1160 2202 0000 0002 5233 8336', $html);
        $this->assertStringContainsString('nie został jeszcze wysłany do KSeF', $html);
    }

    public function test_a_sent_invoice_gets_a_verification_link_built_from_its_xml(): void
    {
        $this->fakeKsefSending();
        $invoice = $this->invoice();

        $this->post(route('invoices.send', $invoice));
        $invoice->refresh();

        $url = app(InvoiceQrCode::class)->url($invoice);

        $expectedHash = rtrim(strtr(base64_encode(hash('sha256', $invoice->xml, true)), '+/', '-_'), '=');

        $this->assertSame(
            // Data w linku to data wystawienia faktury (pole P_1), w formacie DD-MM-RRRR.
            'https://qr-test.ksef.mf.gov.pl/invoice/8792451081/26-09-2026/'.$expectedHash,
            $url,
        );
    }

    public function test_the_sent_invoice_pdf_shows_the_qr_code_and_the_ksef_number(): void
    {
        $this->fakeKsefSending();
        $invoice = $this->invoice();

        $this->post(route('invoices.send', $invoice));
        $invoice->refresh();

        $qr = app(InvoiceQrCode::class);
        $url = $qr->url($invoice);

        $html = view('pdf.invoice', [
            'invoice' => $invoice->load('lines', 'tenant'),
            'settings' => InvoiceSetting::current(),
            'qrUrl' => $url,
            'qrCode' => $qr->dataUri($url),
        ])->render();

        // Strona weryfikacyjna wzorowana na wizualizacji z KSeF.
        $this->assertStringContainsString('Sprawdź, czy Twoja faktura znajduje się w KSeF!', $html);
        $this->assertStringContainsString('link weryfikacyjny', $html);
        $this->assertStringContainsString('8792451081-20260926-9132F5C00005-ED', $html);
        $this->assertStringContainsString('data:image/svg+xml;base64,', $html);

        $this->get(route('invoices.pdf', $invoice))->assertOk();
    }

    public function test_an_invoice_without_xml_has_no_verification_link(): void
    {
        $this->assertNull(app(InvoiceQrCode::class)->url($this->invoice()));
    }
}
