<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\UtilityType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\UtilityBill;
use App\Services\HeatSettlementCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HeatSettlementCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_per_gj_uses_boiler_electricity_cost_divided_by_total_heat(): void
    {
        $bill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 0.85,
            'month' => '2026-02-01',
        ]);

        $this->meterWithReadings(MeterType::Electric, 'E-KOT', config('pm.boiler_meter_name'), 10000, 10500);
        $this->meterWithReadings(MeterType::Heat, 'H-1', 'Lokal 12', 300, 320);
        $this->meterWithReadings(MeterType::Heat, 'H-2', 'Lokal 14', 500, 530);
        $this->meterWithReadings(MeterType::Heat, 'H-3', 'Lokal 16', 700, 730);
        $this->meterWithReadings(MeterType::Heat, 'H-4', 'Lokal 18', 150, 170);

        $result = app(HeatSettlementCalculator::class)->preview(CarbonImmutable::parse('2026-02-01'), $bill);

        $this->assertSame(500.0, $result['boiler_kwh_consumed']);
        $this->assertSame(100.0, $result['total_gj_consumed']);
        $this->assertSame(4.25, $result['price_per_gj']);
        $this->assertEmpty($result['warnings']);
    }

    public function test_main_heat_meter_is_not_counted_as_sub_meter(): void
    {
        $bill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 1.0,
            'month' => '2026-02-01',
        ]);

        $this->meterWithReadings(MeterType::Electric, 'E-KOT', config('pm.boiler_meter_name'), 0, 100);
        $this->meterWithReadings(MeterType::Heat, 'H-1', 'Lokal 12', 0, 50);
        $this->meterWithReadings(MeterType::Heat, 'H-MAIN', 'Licznik budynkowy', 0, 999, isMain: true);

        $result = app(HeatSettlementCalculator::class)->preview(CarbonImmutable::parse('2026-02-01'), $bill);

        $this->assertSame(50.0, $result['total_gj_consumed']);
        $this->assertSame(2.0, $result['price_per_gj']);
    }

    public function test_the_boiler_submeter_is_found_by_its_flag_regardless_of_name(): void
    {
        $bill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 1.0,
            'month' => '2026-02-01',
        ]);

        $boiler = $this->meterWithReadings(MeterType::Electric, 'CB01756', 'Piaskowa 27 Kotłownia', 0, 100);
        $boiler->update(['is_boiler_supply' => true]);

        $this->meterWithReadings(MeterType::Heat, 'H-1', 'Lokal 1', 0, 50);

        $result = app(HeatSettlementCalculator::class)->preview(CarbonImmutable::parse('2026-02-01'), $bill);

        $this->assertSame($boiler->id, $result['boiler_meter']->id);
        $this->assertSame(100.0, $result['boiler_kwh_consumed']);
        $this->assertSame(2.0, $result['price_per_gj']);
    }

    public function test_the_building_main_electric_meter_is_not_mistaken_for_the_boiler_submeter(): void
    {
        $bill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 1.0,
            'month' => '2026-02-01',
        ]);

        $this->meterWithReadings(MeterType::Electric, 'E-GLOWNY', 'Licznik budynkowy', 0, 9999, isMain: true);
        $this->meterWithReadings(MeterType::Heat, 'H-1', 'Lokal 12', 0, 50);

        $result = app(HeatSettlementCalculator::class)->preview(CarbonImmutable::parse('2026-02-01'), $bill);

        $this->assertNull($result['boiler_meter']);
        $this->assertSame(0.0, $result['boiler_kwh_consumed']);
        $this->assertStringContainsString('podlicznika prądu kotłowni', implode(' ', $result['warnings']));
    }

    public function test_saving_the_same_month_twice_updates_instead_of_duplicating(): void
    {
        $bill = UtilityBill::create([
            'type' => UtilityType::Electric,
            'invoice_number' => 'FV/E/02/2026',
            'net_price' => 1.0,
            'month' => '2026-02-01',
        ]);

        $this->meterWithReadings(MeterType::Electric, 'E-KOT', config('pm.boiler_meter_name'), 0, 100);
        $this->meterWithReadings(MeterType::Heat, 'H-1', 'Lokal 12', 0, 50);

        $calculator = app(HeatSettlementCalculator::class);
        $month = CarbonImmutable::parse('2026-02-01');

        $first = $calculator->store($month, $bill);
        $second = $calculator->store($month, $bill);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\HeatSettlement::count());
        $this->assertCount(1, $second->lines);
    }

    private function meterWithReadings(
        MeterType $type,
        string $serial,
        string $name,
        float $start,
        float $end,
        bool $isMain = false,
    ): Meter {
        $meter = Meter::create([
            'type' => $type,
            'serial_number' => $serial,
            'name' => $name,
            'is_active' => true,
            'is_main' => $isMain,
        ]);

        foreach ([['2026-02-01', $start], ['2026-03-01', $end]] as [$date, $value]) {
            Reading::create([
                'meter_id' => $meter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'consumption' => $value,
                'reading_date' => $date,
            ]);
        }

        return $meter;
    }
}
