<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\KsefEnvironment;
use App\Models\Invoice;
use App\Models\KsefSetting;
use App\Models\RentCharge;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Faktury sprzed wdrożenia aplikacji istnieją tylko w KSeF. Pobieramy stamtąd
 * wyłącznie te wystawione naszym najemcom i doklejamy do naliczeń czynszu.
 */
class KsefInvoiceImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-25');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        KsefSetting::create([
            'environment' => KsefEnvironment::Test,
            'nip' => '8792451081',
            'token' => 'TOKEN',
        ]);

        // Uwierzytelnianie ma swoje testy — tutaj wystarczy gotowy token dostępowy.
        Cache::put('ksef.access-token.test.8792451081', 'DOSTEPOWY', 600);

        $this->tenant = Tenant::where('name', 'Sajnaga Logistics sp. z o.o.')->firstOrFail();
        $this->tenant->update(['nip' => '8792688305']);
    }

    private function invoiceXml(string $number = '1/9/2026', string $amount = '1800'): string
    {
        $vat = (string) round((float) $amount * 0.23, 2);
        $gross = (string) ((float) $amount + (float) $vat);

        return <<<XML
        <?xml version="1.0" encoding="utf-8"?>
        <Faktura xmlns="http://crd.gov.pl/wzor/2025/06/25/13775/">
          <Naglowek><KodFormularza kodSystemowy="FA (3)" wersjaSchemy="1-0E">FA</KodFormularza><WariantFormularza>3</WariantFormularza></Naglowek>
          <Podmiot1><DaneIdentyfikacyjne><NIP>8792451081</NIP><Nazwa>ASCOMM Adrian Sajnaga</Nazwa></DaneIdentyfikacyjne></Podmiot1>
          <Podmiot2><DaneIdentyfikacyjne><NIP>8792688305</NIP><Nazwa>Najemca</Nazwa></DaneIdentyfikacyjne></Podmiot2>
          <Fa>
            <KodWaluty>PLN</KodWaluty>
            <P_1>2026-09-01</P_1>
            <P_2>{$number}</P_2>
            <P_6>2026-09-01</P_6>
            <P_13_1>{$amount}</P_13_1>
            <P_14_1>{$vat}</P_14_1>
            <P_15>{$gross}</P_15>
            <RodzajFaktury>VAT</RodzajFaktury>
            <FaWiersz>
              <NrWierszaFa>1</NrWierszaFa>
              <P_7>Czynsz wrzesień 2026</P_7>
              <P_8A>szt.</P_8A>
              <P_8B>1</P_8B>
              <P_9A>{$amount}</P_9A>
              <P_11>{$amount}</P_11>
              <P_12>23</P_12>
            </FaWiersz>
            <Platnosc><TerminPlatnosci><Termin>2026-09-08</Termin></TerminPlatnosci></Platnosc>
          </Fa>
        </Faktura>
        XML;
    }

    private function fakeKsef(array $metadata, array $extra = []): void
    {
        Http::fake(array_merge([
            '*/invoices/query/metadata*' => Http::response(['invoices' => $metadata, 'hasMore' => false]),
            '*/invoices/ksef/*' => Http::response($this->invoiceXml(), 200, ['Content-Type' => 'application/xml']),
        ], $extra));
    }

    private function metadata(string $ksefNumber, string $buyerNip = '8792688305'): array
    {
        return [
            'ksefNumber' => $ksefNumber,
            'invoiceNumber' => '1/9/2026',
            'issueDate' => '2026-09-01',
            'buyer' => ['identifier' => ['type' => 'Nip', 'value' => $buyerNip], 'name' => 'Najemca'],
            'seller' => ['nip' => '8792451081', 'name' => 'ASCOMM Adrian Sajnaga'],
        ];
    }

    public function test_it_imports_an_invoice_issued_to_a_tenant(): void
    {
        $this->fakeKsef([$this->metadata('8792451081-20260901-9132F5C00005-ED')]);

        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertRedirect()
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 nowych faktur'));

        $invoice = Invoice::sole();

        $this->assertSame('1/9/2026', $invoice->number);
        $this->assertSame('8792451081-20260901-9132F5C00005-ED', $invoice->ksef_number);
        $this->assertSame($this->tenant->id, $invoice->tenant_id);
        $this->assertSame(InvoiceStatus::Sent, $invoice->status);
        $this->assertSame('1800.00', $invoice->total_net);
        $this->assertSame('414.00', $invoice->total_vat);
        $this->assertSame('2214.00', $invoice->total_gross);
        $this->assertSame('2026-09-08', $invoice->due_on->toDateString());
        $this->assertSame('Czynsz wrzesień 2026', $invoice->lines->sole()->name);
        $this->assertStringContainsString('<P_2>1/9/2026</P_2>', $invoice->xml);
    }

    public function test_it_asks_ksef_only_about_invoices_where_we_are_the_seller(): void
    {
        $this->fakeKsef([]);

        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/invoices/query/metadata')
            && $request['subjectType'] === 'Subject1'
            && $request['dateRange']['dateType'] === 'Issue');
    }

    public function test_invoices_of_companies_that_are_not_tenants_are_skipped(): void
    {
        $this->fakeKsef([$this->metadata('8792451081-20260901-OBCY-01', '1111111111')]);

        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 pominięto'));

        $this->assertSame(0, Invoice::count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/invoices/ksef/'));
    }

    public function test_importing_twice_does_not_duplicate_anything(): void
    {
        $this->fakeKsef([$this->metadata('8792451081-20260901-9132F5C00005-ED')]);

        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30']);
        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertSessionHas('status', fn ($status) => str_contains($status, '1 było już w aplikacji'));

        $this->assertSame(1, Invoice::count());
    }

    public function test_an_imported_invoice_closes_the_matching_rent_charge(): void
    {
        $unit = Unit::where('description', 'Lokal 12')->firstOrFail();

        $charge = RentCharge::create([
            'unit_id' => $unit->id,
            'tenant_id' => $this->tenant->id,
            'month' => '2026-09-01',
            'amount' => 2214,
        ]);

        $this->fakeKsef([$this->metadata('8792451081-20260901-9132F5C00005-ED')]);
        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30']);

        $charge->refresh();

        $this->assertSame('1/9/2026', $charge->invoice_number);
        $this->assertSame('2026-09-08', $charge->due_on->toDateString());
        $this->assertSame($charge->id, Invoice::sole()->rent_charge_id);
        $this->assertSame($unit->id, Invoice::sole()->unit_id);
    }

    public function test_the_tenant_card_lists_the_imported_invoices(): void
    {
        $this->fakeKsef([$this->metadata('8792451081-20260901-9132F5C00005-ED')]);
        $this->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30']);

        $this->get(route('tenants.show', $this->tenant))
            ->assertOk()
            ->assertSee('1/9/2026')
            ->assertSee('8792451081-20260901-9132F5C00005-ED');
    }

    public function test_a_failing_import_reports_the_reason(): void
    {
        $this->fakeKsef([], [
            '*/invoices/query/metadata*' => Http::response(['message' => 'Brak uprawnień'], 403),
        ]);

        $this->from(route('invoices.index'))
            ->post(route('invoices.import'), ['from' => '2026-09-01', 'to' => '2026-09-30'])
            ->assertSessionHasErrors('invoice');
    }
}
