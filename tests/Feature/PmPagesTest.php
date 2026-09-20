<?php

namespace Tests\Feature;

use App\Enums\UtilityType;
use App\Models\HeatSettlement;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Reading;
use App\Models\Tenant;
use App\Models\TenantSettlement;
use App\Models\Unit;
use App\Models\User;
use App\Models\UtilityBill;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PmPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DemoSeeder::class);
        $this->actingAs(User::factory()->create());
    }

    public function test_dashboard_redirects_to_the_overview(): void
    {
        $this->get('/dashboard')->assertRedirect('/overview');
    }

    public function test_every_list_and_create_page_renders(): void
    {
        $pages = [
            'overview',
            'properties.index', 'properties.create',
            'units.index', 'units.create',
            'tenants.index', 'tenants.create',
            'meters.index', 'meters.create',
            'readings.index', 'readings.create',
            'maintenance-costs.index', 'maintenance-costs.create',
            'utility-bills.index', 'utility-bills.create',
            'heat-settlements.index', 'heat-settlements.create',
            'tenant-settlements.index',
        ];

        foreach ($pages as $page) {
            $this->get(route($page))->assertOk();
        }
    }

    public function test_detail_and_edit_pages_render(): void
    {
        $property = Property::first();
        $unit = Unit::first();
        $tenant = Tenant::first();
        $meter = Meter::first();

        $this->get(route('properties.show', $property))->assertOk()->assertSee('Lokal 12');
        $this->get(route('properties.edit', $property))->assertOk();
        $this->get(route('units.show', $unit))->assertOk();
        $this->get(route('units.edit', $unit))->assertOk();
        $this->get(route('tenants.show', $tenant))->assertOk();
        $this->get(route('tenants.edit', $tenant))->assertOk();
        $this->get(route('meters.show', $meter))->assertOk();
        $this->get(route('meters.edit', $meter))->assertOk();
        $this->get(route('readings.edit', Reading::first()))->assertOk();
        $this->get(route('utility-bills.edit', UtilityBill::first()))->assertOk();
    }

    public function test_a_bill_from_another_month_can_be_chosen_when_settling(): void
    {
        $unit = Unit::where('description', 'Lokal 12')->first();
        $quarterlyBill = UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/Q4/2025',
            'net_price' => 10.00,
            'month' => '2025-12-01',
        ]);

        $this->get(route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => '2026-02', 'water_bill_id' => $quarterlyBill->id]))
            ->assertOk()
            ->assertSee('FV/W/Q4/2025')
            ->assertSee('150,00');

        $this->post(route('tenant-settlements.store'), [
            'unit_id' => $unit->id,
            'month' => '2026-02-01',
            'water_bill_id' => $quarterlyBill->id,
        ])->assertRedirect();

        $settlement = TenantSettlement::first();
        $this->assertSame($quarterlyBill->id, $settlement->water_bill_id);

        // Ponowne otwarcie szkicu podpowiada zapamiętany rachunek.
        $this->get(route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertSee('Zapisz zmiany')
            ->assertSee('150,00');
    }

    public function test_a_meter_can_store_its_device_model(): void
    {
        $this->post(route('meters.store'), [
            'type' => 'electric',
            'serial_number' => 'CB09999',
            'name' => 'Lokal 20 — Prąd',
            'model' => 'F&F LE-03M',
            'is_active' => '1',
        ])->assertRedirect();

        $meter = \App\Models\Meter::where('serial_number', 'CB09999')->first();
        $this->assertSame('F&F LE-03M', $meter->model);

        $this->get(route('meters.show', $meter))->assertOk()->assertSee('F&amp;F LE-03M', false);
        $this->get(route('meters.index'))->assertOk()->assertSee('F&amp;F LE-03M', false);
    }

    public function test_a_bill_of_the_wrong_type_is_rejected(): void
    {
        $unit = Unit::first();
        $electricBill = UtilityBill::where('type', UtilityType::Electric)->first();

        $this->post(route('tenant-settlements.store'), [
            'unit_id' => $unit->id,
            'month' => '2026-02-01',
            'water_bill_id' => $electricBill->id,
        ])->assertSessionHasErrors('water_bill_id');

        $this->assertSame(0, TenantSettlement::count());
    }

    public function test_the_full_settlement_flow_renders_from_heat_cost_to_pdf(): void
    {
        $bill = UtilityBill::where('type', UtilityType::Electric)->first();

        $this->get(route('heat-settlements.create', ['month' => '2026-02-01', 'electric_bill_id' => $bill->id]))
            ->assertOk()
            ->assertSee('4,25');

        $this->post(route('heat-settlements.store'), ['month' => '2026-02-01', 'electric_bill_id' => $bill->id])
            ->assertRedirect();

        $this->get(route('heat-settlements.show', HeatSettlement::first()))->assertOk();

        $unit = Unit::where('description', 'Lokal 12')->first();

        $this->get(route('tenant-settlements.create', ['unit_id' => $unit->id, 'month' => '2026-02']))
            ->assertOk()
            ->assertSee('475,00');

        $this->post(route('tenant-settlements.store'), ['unit_id' => $unit->id, 'month' => '2026-02-01'])
            ->assertRedirect();

        $settlement = TenantSettlement::first();

        app()->setLocale('pl');
        $this->get(route('tenant-settlements.index', ['month' => '2026-02']))
            ->assertOk()
            ->assertSee($settlement->number)
            ->assertSee('luty 2026');
        $this->get(route('tenant-settlements.show', $settlement))
            ->assertOk()
            ->assertSee('584,25')
            ->assertSee('Razem koszty utrzymania')
            ->assertSee('01.02.2026')
            ->assertSee('01.03.2026');
        $this->get(route('tenant-settlements.pdf', $settlement))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
