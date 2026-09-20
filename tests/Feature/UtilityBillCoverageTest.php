<?php

namespace Tests\Feature;

use App\Enums\UtilityType;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBill;
use App\Services\HeatSettlementCalculator;
use App\Services\TenantSettlementCalculator;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UtilityBillCoverageTest extends TestCase
{
    use RefreshDatabase;

    private UtilityBill $bill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->bill = UtilityBill::where('type', UtilityType::Electric)->first();
        $month = CarbonImmutable::parse('2026-02-01');

        app(HeatSettlementCalculator::class)->store($month, $this->bill);

        foreach (['Lokal 12', 'Lokal 14'] as $description) {
            app(TenantSettlementCalculator::class)->store(Unit::where('description', $description)->first(), $month);
        }
    }

    public function test_it_sums_the_energy_settled_from_a_bill(): void
    {
        $response = $this->get(route('utility-bills.show', $this->bill))->assertOk();

        // Lokal 12: 200 kWh, Lokal 14: 350 kWh, pompa ciepła: 500 kWh.
        $response->assertSee('550,0 kWh');   // rozliczone na lokale
        $response->assertSee('500,0 kWh');   // pompa ciepła
        $response->assertSee('1 050,0 kWh'); // razem
    }

    public function test_it_compares_the_settled_energy_with_the_invoice(): void
    {
        $this->bill->update(['consumption_value' => 1200]);

        $this->get(route('utility-bills.show', $this->bill))
            ->assertOk()
            ->assertSee('Pokrycie faktury')
            ->assertSee('87,5%')
            ->assertSee('150,0 kWh'); // nierozliczona reszta
    }

    public function test_it_lists_the_settlements_that_used_the_bill(): void
    {
        $this->get(route('utility-bills.show', $this->bill))
            ->assertOk()
            ->assertSee('Lokal 12')
            ->assertSee('Lokal 14')
            ->assertSee('Rozliczenie kosztów ciepła');
    }

    public function test_a_water_bill_skips_the_coverage_summary(): void
    {
        $water = UtilityBill::where('type', UtilityType::Water)->first();

        $this->get(route('utility-bills.show', $water))
            ->assertOk()
            ->assertDontSee('Ile z tego rachunku zostało rozliczone');
    }

    public function test_the_bill_list_links_to_the_preview(): void
    {
        $this->get(route('utility-bills.index'))
            ->assertOk()
            ->assertSee(route('utility-bills.show', $this->bill), false);
    }
}
