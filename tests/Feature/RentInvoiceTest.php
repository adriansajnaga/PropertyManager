<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use App\Services\RentInvoiceFactory;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faktury odwzorowują dokumenty, które użytkownik wystawiał dotąd ręcznie:
 * stawka z kartoteki lokalu jest kwotą brutto, numer ma postać „3/9/2026",
 * termin płatności to 7 dni, a pozycja nazywa się „Czynsz wrzesień 2026".
 */
class RentInvoiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-01');
        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->admin()->create());

        InvoiceSetting::create([
            'seller_name' => 'ASCOMM Adrian Sajnaga',
            'seller_nip' => '8792451081',
            'seller_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'seller_address_l2' => '87-100 Toruń',
            'issue_place' => 'Toruń',
            'payment_days' => 7,
            'vat_rate' => 23,
            'rent_is_gross' => true,
        ]);
    }

    private function charge(float $amount = 2214, string $unitName = 'Lokal 12', string $month = '2026-09-01'): RentCharge
    {
        $unit = Unit::where('description', $unitName)->firstOrFail();

        return RentCharge::create([
            'unit_id' => $unit->id,
            'tenant_id' => $unit->currentTenant()->id,
            'month' => $month,
            'amount' => $amount,
        ]);
    }

    public function test_the_rent_rate_is_treated_as_the_amount_due_and_split_into_net_and_vat(): void
    {
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(2214));

        $this->assertSame('1800.00', $invoice->total_net);
        $this->assertSame('414.00', $invoice->total_vat);
        $this->assertSame('2214.00', $invoice->total_gross);

        $line = $invoice->lines->sole();

        $this->assertSame('Czynsz wrzesień 2026', $line->name);
        $this->assertSame('1800.00', $line->unit_price_net);
        $this->assertSame('szt.', $line->unit);
    }

    public function test_a_net_rate_is_used_when_the_setting_says_so(): void
    {
        InvoiceSetting::current()->update(['rent_is_gross' => false]);

        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(1000));

        $this->assertSame('1000.00', $invoice->total_net);
        $this->assertSame('230.00', $invoice->total_vat);
        $this->assertSame('1230.00', $invoice->total_gross);
    }

    public function test_the_number_counts_within_the_month_and_the_due_date_follows_the_setting(): void
    {
        $factory = app(RentInvoiceFactory::class);

        $first = $factory->fromRentCharge($this->charge(1230));
        $second = $factory->fromRentCharge($this->charge(1845, 'Lokal 14'));

        $this->assertSame('1/9/2026', $first->number);
        $this->assertSame('2/9/2026', $second->number);
        $this->assertSame('2026-09-08', $first->due_on->toDateString());
        $this->assertSame('2026-09-01', $first->sold_on->toDateString());
    }

    public function test_a_number_already_taken_outside_the_application_is_skipped(): void
    {
        Invoice::create([
            'number' => '1/9/2026',
            'issued_on' => '2026-09-01',
            'sold_on' => '2026-09-01',
            'due_on' => '2026-09-08',
            'vat_rate' => 23,
            'total_net' => 100,
            'total_vat' => 23,
            'total_gross' => 123,
        ]);

        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->assertSame('2/9/2026', $invoice->number);
    }

    public function test_the_rent_charge_takes_the_invoice_number_and_due_date(): void
    {
        $charge = $this->charge();

        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($charge);

        $charge->refresh();

        $this->assertSame($invoice->number, $charge->invoice_number);
        $this->assertSame('2026-09-08', $charge->due_on->toDateString());
        $this->assertSame(\App\Enums\RentStatus::Issued, $charge->status);
    }

    public function test_an_invoice_for_an_earlier_month_is_refused(): void
    {
        // Zaległe faktury istnieją już w KSeF — aplikacja nie wystawia ich drugi raz.
        $charge = $this->charge(1230, 'Lokal 12', '2026-08-01');

        $this->from(route('invoices.index'))
            ->post(route('invoices.store'), ['rent_charge_id' => $charge->id])
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_list_only_offers_charges_from_the_current_month(): void
    {
        $this->charge(1230, 'Lokal 12', '2026-08-01');
        $this->charge(1845, 'Lokal 14');

        $html = $this->get(route('invoices.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Lokal 14', $html);
        $this->assertStringNotContainsString('sierpień 2026', $html);
    }

    public function test_one_charge_cannot_be_invoiced_twice(): void
    {
        $charge = $this->charge();

        $this->post(route('invoices.store'), ['rent_charge_id' => $charge->id])->assertRedirect();

        $this->from(route('invoices.index'))
            ->post(route('invoices.store'), ['rent_charge_id' => $charge->id])
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::count());
    }

    public function test_without_seller_data_nothing_is_issued(): void
    {
        InvoiceSetting::query()->delete();

        $this->from(route('invoices.index'))
            ->post(route('invoices.store'), ['rent_charge_id' => $this->charge()->id])
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, Invoice::count());
    }

    public function test_the_list_offers_charges_without_an_invoice_and_shows_the_issued_ones(): void
    {
        $charge = $this->charge();

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('Czynsze bez faktury')
            ->assertSee('Lokal 12');

        app(RentInvoiceFactory::class)->fromRentCharge($charge);

        $this->get(route('invoices.index'))
            ->assertOk()
            ->assertSee('1/9/2026')
            ->assertSee('2 214,00 zł');
    }

    public function test_an_invoice_can_be_deleted_until_it_reaches_ksef(): void
    {
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->delete(route('invoices.destroy', $invoice))->assertRedirect();
        $this->assertSame(0, Invoice::count());

        $second = app(RentInvoiceFactory::class)->fromRentCharge($this->charge(1230, 'Lokal 14'));
        $second->update(['ksef_number' => '8792451081-20260901-9132F5C00005-ED', 'status' => InvoiceStatus::Sent]);

        $this->from(route('invoices.show', $second))
            ->delete(route('invoices.destroy', $second))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::count());
    }

    public function test_the_invoice_page_shows_the_buyer_and_the_totals(): void
    {
        $invoice = app(RentInvoiceFactory::class)->fromRentCharge($this->charge());

        $this->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Czynsz wrzesień 2026')
            ->assertSee('1 800,00 zł')
            ->assertSee('414,00 zł')
            ->assertSee('2 214,00 zł');
    }

    public function test_the_settings_screen_stores_the_seller_data(): void
    {
        $this->post(route('invoice-settings.update'), [
            'seller_name' => 'ASCOMM Adrian Sajnaga',
            'seller_nip' => '8792451081',
            'seller_address_l1' => 'ul. Konstytucji 3 Maja 15/12',
            'seller_address_l2' => '87-100 Toruń',
            'bank_account' => '87 1160 2202 0000 0002 5233 8336',
            'bank_swift' => 'BIGBPLPW',
            'issue_place' => 'Toruń',
            'payment_days' => 7,
            'vat_rate' => 23,
            'rent_is_gross' => '1',
            'line_description' => 'Czynsz {miesiac} {rok}',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $settings = InvoiceSetting::current();

        $this->assertSame('BIGBPLPW', $settings->bank_swift);
        $this->assertTrue($settings->rent_is_gross);
        $this->assertSame('Czynsz wrzesień 2026', $settings->rentLineDescription(now()));
    }
}
