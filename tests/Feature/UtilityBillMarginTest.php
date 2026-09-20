<?php

namespace Tests\Feature;

use App\Enums\UtilityType;
use App\Models\User;
use App\Models\UtilityBill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityBillMarginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, string>
     */
    private function form(array $overrides = []): array
    {
        return array_merge([
            'type' => 'electric',
            'invoice_number' => 'FV/E/02/2026',
            'month' => '2026-02',
            'base_net_price' => '1.0000',
            'margin_percent' => '5',
            'consumption_value' => '990',
            'net_amount' => '990',
        ], $overrides);
    }

    public function test_the_margin_raises_the_price_used_in_settlements(): void
    {
        $this->post(route('utility-bills.store'), $this->form())->assertRedirect();

        $bill = UtilityBill::first();

        $this->assertSame('1.0000', $bill->base_net_price);
        $this->assertSame('5.00', $bill->margin_percent);
        $this->assertSame('1.0500', $bill->net_price);
    }

    public function test_a_bill_without_a_margin_keeps_the_invoice_price(): void
    {
        $this->post(route('utility-bills.store'), $this->form(['margin_percent' => '']))->assertRedirect();

        $bill = UtilityBill::first();

        $this->assertSame('0.00', $bill->margin_percent);
        $this->assertSame('1.0000', $bill->net_price);
    }

    public function test_editing_the_margin_recalculates_the_price(): void
    {
        $this->post(route('utility-bills.store'), $this->form());
        $bill = UtilityBill::first();

        $this->put(route('utility-bills.update', $bill), $this->form(['margin_percent' => '10']))->assertRedirect();

        $this->assertSame('1.1000', $bill->fresh()->net_price);
        $this->assertSame('1.0000', $bill->fresh()->base_net_price, 'Cena z faktury zostaje bez zmian.');
    }

    public function test_the_margin_is_only_visible_on_the_bill_form(): void
    {
        $this->post(route('utility-bills.store'), $this->form());
        $bill = UtilityBill::first();

        // Formularz: marża widoczna i edytowalna.
        $this->get(route('utility-bills.edit', $bill))
            ->assertOk()
            ->assertSee('Marża (%)')
            ->assertSee('name="margin_percent"', false);

        // Lista rachunków: tylko cena obowiązująca.
        $this->get(route('utility-bills.index'))
            ->assertOk()
            ->assertSee('1,0500')
            ->assertDontSee('Marża')
            ->assertDontSee('1,0000');
    }

    public function test_the_settlement_and_its_pdf_never_mention_the_margin(): void
    {
        $this->seed(\Database\Seeders\DemoSeeder::class);

        UtilityBill::where('type', UtilityType::Electric)->each(
            fn (UtilityBill $bill) => $bill->update(['margin_percent' => 5]),
        );

        $settlement = app(\App\Services\TenantSettlementCalculator::class)->store(
            \App\Models\Unit::where('description', 'Lokal 12')->first(),
            \Carbon\CarbonImmutable::parse('2026-02-01'),
        );

        $response = $this->get(route('tenant-settlements.show', $settlement))->assertOk();
        $response->assertDontSee('marż', false);
        $response->assertDontSee('Marża');

        // 0,85 zł/kWh + 5% = 0,8925 zł/kWh — w rozliczeniu widać już cenę z marżą.
        $electric = $settlement->lines->firstWhere('category', \App\Enums\SettlementLineCategory::Electric);
        $this->assertSame('0.8925', $electric->unit_price);
    }
}
