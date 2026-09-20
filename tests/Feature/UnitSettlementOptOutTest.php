<?php

namespace Tests\Feature;

use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitSettlementOptOutTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create());

        $this->unit = Unit::where('description', 'Lokal 12')->firstOrFail();
    }

    public function test_the_checkbox_turns_utility_settlements_off_for_a_unit(): void
    {
        $this->assertTrue($this->unit->settles_utilities);

        $this->put(route('units.update', $this->unit), [
            'property_id' => $this->unit->property_id,
            'description' => $this->unit->description,
            'area' => $this->unit->area,
            'skip_utilities' => '1',
        ])->assertRedirect();

        $this->assertFalse($this->unit->refresh()->settles_utilities);
    }

    public function test_such_a_unit_cannot_be_settled(): void
    {
        $this->unit->update(['settles_utilities' => false]);
        $month = CarbonImmutable::parse('2026-02-01');

        $this->get(route('tenant-settlements.create', ['unit_id' => $this->unit->id, 'month' => $month->format('Y-m')]))
            ->assertRedirect(route('units.show', $this->unit))
            ->assertSessionHasErrors('settlement');

        $this->post(route('tenant-settlements.store'), [
            'unit_id' => $this->unit->id,
            'month' => $month->toDateString(),
        ])->assertSessionHasErrors('settlement');

        $this->assertSame(0, TenantSettlement::count());
    }

    public function test_the_settlement_list_offers_no_button_for_such_a_unit(): void
    {
        $this->unit->update(['settles_utilities' => false]);

        $html = $this->get(route('tenant-settlements.index', ['month' => '2026-02']))
            ->assertOk()
            ->assertSee('rozlicza się samodzielnie')
            ->getContent();

        $this->assertStringNotContainsString(
            route('tenant-settlements.create', ['unit_id' => $this->unit->id, 'month' => '2026-02'], absolute: false),
            $html,
        );
    }

    public function test_rent_is_still_charged_to_such_a_unit(): void
    {
        $this->unit->update(['settles_utilities' => false, 'rent_amount' => 2500]);

        app(\App\Services\RentAccrualService::class)->run();

        $this->assertGreaterThan(0, $this->unit->rentCharges()->count());
    }
}
