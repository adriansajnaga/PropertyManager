<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ksef\Fa3InvoiceBuilder;
use App\Services\RentInvoiceFactory;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Tests\Support\FakesKsefApi;
use Tests\TestCase;

/**
 * Wysyłka faktury sesją interaktywną. Testy odszyfrowują to, co aplikacja wysłała,
 * więc pilnują samej treści dokumentu, a nie tylko kolejności wywołań.
 */
class KsefInvoiceSendingTest extends TestCase
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
        $this->fakeKsefSending();

        InvoiceSetting::create([
            'seller_name' => 'ASCOMM Adrian Sajnaga',
            'seller_nip' => '8792451081',
            'seller_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'seller_address_l2' => '87-100 Toruń',
            'seller_phone' => '+48606200547',
            'bank_account' => '87 1160 2202 0000 0002 5233 8336',
            'bank_swift' => 'BIGBPLPW',
            'issue_place' => 'Toruń',
            'payment_days' => 7,
            'vat_rate' => 23,
            'rent_is_gross' => true,
        ]);

        Unit::where('description', 'Lokal 12')->firstOrFail()
            ->currentTenant()
            ->update(['nip' => '8792688305', 'street' => 'Mierzynek 21a', 'zip' => '87-162', 'city' => 'Lubicz Górny']);
    }

    private function invoice(float $amount = 2214): Invoice
    {
        $unit = Unit::where('description', 'Lokal 12')->firstOrFail();

        $charge = RentCharge::create([
            'unit_id' => $unit->id,
            'tenant_id' => $unit->currentTenant()->id,
            'month' => '2026-09-01',
            'amount' => $amount,
        ]);

        return app(RentInvoiceFactory::class)->fromRentCharge($charge);
    }

    private function xpath(string $xml, string $path): ?string
    {
        $document = new SimpleXMLElement($xml);
        $document->registerXPathNamespace('fa', 'http://crd.gov.pl/wzor/2025/06/25/13775/');
        $found = $document->xpath($path);

        return $found ? trim((string) $found[0]) : null;
    }

    public function test_the_xml_matches_the_shape_of_invoices_issued_so_far(): void
    {
        $xml = app(Fa3InvoiceBuilder::class)->build($this->invoice());

        $this->assertSame('FA', $this->xpath($xml, '//fa:Naglowek/fa:KodFormularza'));
        $this->assertSame('3', $this->xpath($xml, '//fa:Naglowek/fa:WariantFormularza'));
        $this->assertSame('8792451081', $this->xpath($xml, '//fa:Podmiot1//fa:NIP'));
        $this->assertSame('ASCOMM Adrian Sajnaga', $this->xpath($xml, '//fa:Podmiot1//fa:Nazwa'));
        $this->assertSame('ul. Konstytucji 3 Maja 15/12', $this->xpath($xml, '//fa:Podmiot1//fa:AdresL1'));
        $this->assertSame('8792688305', $this->xpath($xml, '//fa:Podmiot2//fa:NIP'));
        $this->assertSame('87-162 Lubicz Górny', $this->xpath($xml, '//fa:Podmiot2//fa:AdresL2'));

        $this->assertSame('1/9/2026', $this->xpath($xml, '//fa:Fa/fa:P_2'));
        $this->assertSame('Toruń', $this->xpath($xml, '//fa:Fa/fa:P_1M'));
        $this->assertSame('1800', $this->xpath($xml, '//fa:Fa/fa:P_13_1'));
        $this->assertSame('414', $this->xpath($xml, '//fa:Fa/fa:P_14_1'));
        $this->assertSame('2214', $this->xpath($xml, '//fa:Fa/fa:P_15'));
        $this->assertSame('VAT', $this->xpath($xml, '//fa:Fa/fa:RodzajFaktury'));

        $this->assertSame('Czynsz wrzesień 2026', $this->xpath($xml, '//fa:FaWiersz/fa:P_7'));
        $this->assertSame('1800', $this->xpath($xml, '//fa:FaWiersz/fa:P_11'));
        $this->assertSame('23', $this->xpath($xml, '//fa:FaWiersz/fa:P_12'));

        $this->assertSame('2026-10-03', $this->xpath($xml, '//fa:Platnosc/fa:TerminPlatnosci/fa:Termin'));
        $this->assertSame('6', $this->xpath($xml, '//fa:Platnosc/fa:FormaPlatnosci'));
        $this->assertSame('87 1160 2202 0000 0002 5233 8336', $this->xpath($xml, '//fa:Platnosc//fa:NrRB'));
        $this->assertSame('BIGBPLPW', $this->xpath($xml, '//fa:Platnosc//fa:SWIFT'));
    }

    public function test_a_buyer_without_a_nip_stops_the_document(): void
    {
        $invoice = $this->invoice();
        $invoice->tenant->update(['nip' => null]);

        $this->from(route('invoices.show', $invoice))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertNull($invoice->refresh()->ksef_number);
    }

    public function test_the_invoice_reaches_ksef_and_comes_back_with_its_number(): void
    {
        $this->fakeKsefSending();
        $invoice = $this->invoice();

        $this->post(route('invoices.send', $invoice))
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, '8792451081-20260926-9132F5C00005-ED'));

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame('8792451081-20260926-9132F5C00005-ED', $invoice->ksef_number);
        $this->assertSame('FAKTURA-1', $invoice->ksef_reference);
        $this->assertNotNull($invoice->ksef_sent_at);
        $this->assertStringContainsString('<P_2>1/9/2026</P_2>', $invoice->xml);
    }

    public function test_what_ksef_receives_decrypts_back_into_our_invoice(): void
    {
        $this->fakeKsefSending();
        $invoice = $this->invoice();

        $this->post(route('invoices.send', $invoice));

        $session = null;
        $document = null;

        Http::recorded(function ($request) use (&$session, &$document) {
            if (str_ends_with($request->url(), '/sessions/online')) {
                $session = $request->data();
            }

            if (str_contains($request->url(), '/sessions/online/SESJA-1/invoices')) {
                $document = $request->data();
            }
        });

        $this->assertNotNull($session, 'Sesja wysyłkowa powinna zostać otwarta.');
        $this->assertSame('FA (3)', $session['formCode']['systemCode']);
        $this->assertSame('1-0E', $session['formCode']['schemaVersion']);
        $this->assertSame('klucz-sym', $session['encryption']['publicKeyId']);

        $xml = $this->decryptInvoice($session, $document);

        $this->assertSame('1/9/2026', $this->xpath($xml, '//fa:Fa/fa:P_2'));
        $this->assertSame(strlen($xml), $document['invoiceSize']);
        $this->assertSame(base64_encode(hash('sha256', $xml, true)), $document['invoiceHash']);
    }

    public function test_after_sending_the_invoice_is_read_back_from_ksef(): void
    {
        $invoice = $this->invoice();

        // W rejestrze faktura leży pod innym numerem — tak wygląda dokument, który
        // KSeF odda przy pobraniu.
        $fromKsef = str_replace(
            '<P_2>1/9/2026</P_2>',
            '<P_2>4/9/2026</P_2>',
            app(Fa3InvoiceBuilder::class)->build($invoice),
        );

        $this->fakeKsefSending([
            '*/invoices/ksef/8792451081-20260926-9132F5C00005-ED' => Http::response($fromKsef),
        ]);

        $this->post(route('invoices.send', $invoice))->assertRedirect();

        // To, co leży w rejestrze, jest wersją obowiązującą — także numer.
        $invoice->refresh();
        $this->assertSame('4/9/2026', $invoice->number);
        $this->assertSame($fromKsef, $invoice->xml);
    }

    public function test_a_rejected_invoice_keeps_the_reason(): void
    {
        $this->fakeKsefSending([
            '*/sessions/SESJA-1/invoices/FAKTURA-1' => Http::response([
                'status' => ['code' => 415, 'description' => 'Niezgodność z schemą XSD'],
            ]),
        ]);

        $invoice = $this->invoice();

        $this->from(route('invoices.show', $invoice))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHasErrors('invoice');

        $invoice->refresh();

        $this->assertSame(InvoiceStatus::Rejected, $invoice->status);
        $this->assertStringContainsString('Niezgodność z schemą XSD', $invoice->ksef_error);
        $this->assertNull($invoice->ksef_number);
    }

    public function test_an_invoice_already_in_ksef_is_not_sent_twice(): void
    {
        $this->fakeKsefSending();
        $invoice = $this->invoice();
        $invoice->update(['ksef_number' => '8792451081-20260901-AAA-01']);

        $this->from(route('invoices.show', $invoice))
            ->post(route('invoices.send', $invoice))
            ->assertSessionHasErrors('invoice');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/sessions/online'));
    }

    public function test_the_session_is_closed_after_sending(): void
    {
        $this->fakeKsefSending();

        $this->post(route('invoices.send', $this->invoice()));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/sessions/online/SESJA-1/close'));
    }
}
