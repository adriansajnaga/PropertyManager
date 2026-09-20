<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\SettlementLineCategory;
use App\Enums\SettlementStatus;
use App\Enums\UtilityType;
use App\Models\HeatSettlement;
use App\Models\MaintenanceCost;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Reading;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\UtilityBill;
use App\Services\TenantSettlementCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TenantSettlementCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();

        $property = Property::create([
            'name' => 'Budynek A',
            'address' => 'ul. Piaskowa 27',
            'total_area' => 1000,
        ]);

        MaintenanceCost::create([
            'property_id' => $property->id,
            'description' => 'Utrzymanie budynku',
            'annual_cost' => 24000,
            'year' => 2026,
        ]);

        $this->unit = Unit::create([
            'property_id' => $property->id,
            'description' => 'Lokal 12',
            'area' => 50,
        ]);

        $tenant = Tenant::create(['name' => 'Najemca sp. z o.o.', 'is_active' => true]);
        $this->unit->tenantAssignments()->create(['tenant_id' => $tenant->id, 'valid_from' => '2026-01-01']);

        $this->attachMeter(MeterType::Water, 'W-12', 120, 135);
        $this->attachMeter(MeterType::Electric, 'E-12', 1000, 1200);
        $this->attachMeter(MeterType::Heat, 'H-12', 300, 320);

        UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/02/2026',
            'net_price' => 8.00,
            'month' => '2026-02-01',
        ]);

        $electricBill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 0.85,
            'month' => '2026-02-01',
        ]);

        HeatSettlement::create([
            'month' => '2026-02-01',
            'electric_bill_id' => $electricBill->id,
            'boiler_kwh_consumed' => 500,
            'total_gj_consumed' => 100,
            'price_per_gj' => 4.25,
        ]);
    }

    public function test_it_bills_media_by_consumption_and_prorates_maintenance_costs(): void
    {
        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'));

        $amounts = collect($result['lines'])->mapWithKeys(
            fn (array $line) => [$line['category']->value.'|'.$line['label'] => $line['amount']],
        );

        $this->assertSame(120.0, $amounts['water|Woda']);
        $this->assertSame(170.0, $amounts['electric|Prąd']);
        $this->assertSame(85.0, $amounts['heat|Ciepło']);
        $this->assertSame(100.0, $amounts['maintenance_cost|Utrzymanie budynku']);

        $this->assertSame(475.0, $result['total_net']);
        $this->assertSame(584.25, $result['total_gross']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_electricity_is_priced_from_the_monthly_invoice(): void
    {
        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'));

        $electric = collect($result['lines'])->firstWhere('category', SettlementLineCategory::Electric);

        $this->assertSame(0.85, $electric['unit_price']);
        $this->assertSame('FV/E/02/2026', $electric['invoice_number']);
    }

    public function test_it_freezes_line_data_on_the_stored_settlement(): void
    {
        $settlement = app(TenantSettlementCalculator::class)
            ->store($this->unit, CarbonImmutable::parse('2026-02-01'));

        $this->assertSame('ROZ/2026-02/'.$this->unit->id, $settlement->number);
        $this->assertSame(SettlementStatus::Draft, $settlement->status);
        $this->assertCount(4, $settlement->lines);
        $this->assertSame('475.00', $settlement->total_net);

        $water = $settlement->lines->firstWhere('category', SettlementLineCategory::Water);
        $this->assertSame('120.0000', $water->start_reading);
        $this->assertSame('135.0000', $water->end_reading);
        $this->assertSame('W-12', $water->meter_serial);
        $this->assertSame('F&F LE-03M', $water->meter_model);
    }

    public function test_recalculating_a_finalized_settlement_is_refused(): void
    {
        $calculator = app(TenantSettlementCalculator::class);
        $month = CarbonImmutable::parse('2026-02-01');

        $calculator->store($this->unit, $month)->update(['status' => SettlementStatus::Final]);

        $this->expectException(RuntimeException::class);

        $calculator->store($this->unit, $month);
    }

    public function test_outside_the_heating_season_a_missing_heat_settlement_is_not_reported(): void
    {
        // Lato: kotłownia stoi, licznik ciepła ma ten sam stan na obu granicach miesiąca.
        HeatSettlement::query()->delete();
        $heatMeter = Meter::where('serial_number', 'H-12')->first();
        $heatMeter->readings()->update(['consumption' => 300]);

        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'));

        $heat = collect($result['lines'])->firstWhere('category', SettlementLineCategory::Heat);

        $this->assertSame(0.0, $heat['consumption']);
        $this->assertSame(0.0, $heat['amount']);
        $this->assertStringNotContainsString('Brak rozliczenia kosztów ciepła', implode(' ', $result['warnings']));
    }

    public function test_missing_price_produces_a_warning_instead_of_a_silent_zero(): void
    {
        UtilityBill::where('type', UtilityType::Water)->delete();

        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'));

        $this->assertNotEmpty($result['warnings']);
        $this->assertStringContainsString('Brak rachunku (Woda)', implode(' ', $result['warnings']));
    }

    public function test_a_manually_chosen_bill_from_another_month_is_used(): void
    {
        $quarterlyWaterBill = UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/Q4/2025',
            'net_price' => 10.00,
            'month' => '2025-12-01',
        ]);

        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'), ['water' => $quarterlyWaterBill->id]);

        $water = collect($result['lines'])->firstWhere('category', SettlementLineCategory::Water);

        $this->assertSame(10.0, $water['unit_price']);
        $this->assertSame('FV/W/Q4/2025', $water['invoice_number']);
        $this->assertSame(150.0, $water['amount']);
        $this->assertSame($quarterlyWaterBill->id, $result['bills']['water']->id);
    }

    public function test_a_bill_of_the_wrong_type_is_ignored(): void
    {
        $electricBill = UtilityBill::where('type', UtilityType::Electric)->first();

        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'), ['water' => $electricBill->id]);

        $this->assertSame('FV/W/02/2026', $result['bills']['water']->invoice_number);
    }

    public function test_without_a_bill_for_the_month_the_latest_earlier_bill_is_suggested_with_a_warning(): void
    {
        UtilityBill::where('type', UtilityType::Water)->delete();

        UtilityBill::create(['type' => UtilityType::Water, 'invoice_number' => 'FV/W/10/2025', 'net_price' => 7.00, 'month' => '2025-10-01']);
        UtilityBill::create(['type' => UtilityType::Water, 'invoice_number' => 'FV/W/12/2025', 'net_price' => 9.00, 'month' => '2025-12-01']);
        UtilityBill::create(['type' => UtilityType::Water, 'invoice_number' => 'FV/W/04/2026', 'net_price' => 99.00, 'month' => '2026-04-01']);

        $result = app(TenantSettlementCalculator::class)
            ->preview($this->unit, CarbonImmutable::parse('2026-02-01'));

        $water = collect($result['lines'])->firstWhere('category', SettlementLineCategory::Water);

        $this->assertSame('FV/W/12/2025', $water['invoice_number']);
        $this->assertSame(135.0, $water['amount']);
        $this->assertStringContainsString('użyto ostatniego wcześniejszego: FV/W/12/2025', implode(' ', $result['warnings']));
    }

    public function test_chosen_bills_are_remembered_and_reused_when_recalculating(): void
    {
        $calculator = app(TenantSettlementCalculator::class);
        $month = CarbonImmutable::parse('2026-02-01');

        $olderWaterBill = UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/11/2025',
            'net_price' => 6.00,
            'month' => '2025-11-01',
        ]);

        $settlement = $calculator->store($this->unit, $month, ['water' => $olderWaterBill->id]);

        $this->assertSame($olderWaterBill->id, $settlement->water_bill_id);
        $this->assertNotNull($settlement->electric_bill_id);

        $recalculated = $calculator->store($this->unit, $month, $settlement->billIds());
        $water = $recalculated->lines->firstWhere('category', SettlementLineCategory::Water);

        $this->assertSame('FV/W/11/2025', $water->invoice_number);
        $this->assertSame('90.00', $water->amount);
    }

    private function attachMeter(MeterType $type, string $serial, float $start, float $end): void
    {
        $meter = Meter::create([
            'type' => $type,
            'serial_number' => $serial,
            'name' => 'Lokal 12 — '.$type->label(),
            'model' => 'F&F LE-03M',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->unit->meterAssignments()->create(['meter_id' => $meter->id, 'valid_from' => '2026-01-01']);

        foreach ([['2026-02-01', $start], ['2026-03-01', $end]] as [$date, $value]) {
            Reading::create([
                'meter_id' => $meter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'consumption' => $value,
                'reading_date' => $date,
            ]);
        }
    }
}
