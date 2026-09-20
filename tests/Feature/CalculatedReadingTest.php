<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Enums\UtilityType;
use App\Models\MaintenanceCost;
use App\Models\Meter;
use App\Models\Property;
use App\Models\Reading;
use App\Models\Unit;
use App\Models\UtilityBill;
use App\Services\TenantSettlementCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculatedReadingTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $property = Property::create(['name' => 'Budynek A', 'address' => 'ul. Piaskowa 27', 'total_area' => 1000]);
        MaintenanceCost::create(['property_id' => $property->id, 'description' => 'Utrzymanie', 'annual_cost' => 0, 'year' => 2026]);

        $this->unit = Unit::create(['property_id' => $property->id, 'description' => 'Lokal 12', 'area' => 50]);

        $this->meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00703',
            'name' => 'Góra',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->unit->meterAssignments()->create(['meter_id' => $this->meter->id, 'valid_from' => '2026-01-01']);

        foreach (['2026-02-01', '2026-03-01', '2026-04-01'] as $month) {
            UtilityBill::create([
                'type' => UtilityType::Electric,
                'invoice_number' => 'FV/E/'.substr($month, 5, 2).'/2026',
                'net_price' => 1.0,
                'month' => $month,
            ]);
        }

        // Brak odczytu na 01.04 — jest tylko z 04.04.
        $this->reading('2026-03-01', 300);
        $this->reading('2026-04-04', 330);
    }

    public function test_an_interpolated_boundary_state_is_stored_as_a_calculated_reading(): void
    {
        app(TenantSettlementCalculator::class)->store($this->unit, CarbonImmutable::parse('2026-03-01'));

        $calculated = Reading::where('source_table', Reading::SOURCE_CALCULATED)->get();

        $this->assertCount(1, $calculated);
        $this->assertSame('2026-04-01', $calculated->first()->reading_date->toDateString());
        $this->assertSame('obliczony', $calculated->first()->sourceLabel());
        $this->assertSame('CB00703', $calculated->first()->source_meter_serial);

        // 34 dni między odczytami, 31 dni do granicy: 300 + 30 × 31/34
        $this->assertEqualsWithDelta(327.35, (float) $calculated->first()->consumption, 0.01);
    }

    public function test_the_next_month_starts_where_the_previous_one_ended(): void
    {
        $calculator = app(TenantSettlementCalculator::class);

        $march = $calculator->store($this->unit, CarbonImmutable::parse('2026-03-01'));
        $april = $calculator->store($this->unit, CarbonImmutable::parse('2026-04-01'));

        $marchEnd = (float) $march->lines->firstWhere('category', \App\Enums\SettlementLineCategory::Electric)->end_state;
        $aprilStart = (float) $april->lines->firstWhere('category', \App\Enums\SettlementLineCategory::Electric)->start_state;

        $this->assertSame($marchEnd, $aprilStart, 'Kwiecień musi ruszyć z tego samego stanu, na którym skończył marzec.');
    }

    public function test_recalculating_does_not_pile_up_calculated_readings(): void
    {
        $calculator = app(TenantSettlementCalculator::class);
        $month = CarbonImmutable::parse('2026-03-01');

        $calculator->store($this->unit, $month);
        $calculator->store($this->unit, $month);

        $this->assertSame(1, Reading::where('source_table', Reading::SOURCE_CALCULATED)->count());
    }

    public function test_a_real_reading_on_the_boundary_is_not_duplicated_by_a_calculated_one(): void
    {
        $this->reading('2026-04-01', 328);

        app(TenantSettlementCalculator::class)->store($this->unit, CarbonImmutable::parse('2026-03-01'));

        $this->assertSame(0, Reading::where('source_table', Reading::SOURCE_CALCULATED)->count());
    }

    public function test_water_is_handled_the_same_way_as_electricity(): void
    {
        $waterMeter = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'W-12',
            'name' => 'Lokal 12 — Woda',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->unit->meterAssignments()->create(['meter_id' => $waterMeter->id, 'valid_from' => '2026-01-01']);

        UtilityBill::create([
            'type' => UtilityType::Water,
            'invoice_number' => 'FV/W/Q1/2026',
            'net_price' => 8.0,
            'month' => '2026-03-01',
        ]);

        // Wodomierz odczytany 01.03 i dopiero 04.04 — granica 01.04 wymaga wyliczenia.
        foreach ([['2026-03-01', 120], ['2026-04-04', 154]] as [$date, $value]) {
            Reading::create([
                'meter_id' => $waterMeter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'consumption' => $value,
                'reading_date' => $date,
            ]);
        }

        $settlement = app(TenantSettlementCalculator::class)->store($this->unit, CarbonImmutable::parse('2026-03-01'));

        $water = $settlement->lines->firstWhere('category', \App\Enums\SettlementLineCategory::Water);
        $calculated = Reading::where('source_table', Reading::SOURCE_CALCULATED)->where('meter_id', $waterMeter->id)->first();

        // 34 dni między odczytami, 31 dni do granicy: 120 + 34 × 31/34 = 151
        $this->assertNotNull($calculated, 'Dla wody też zapisujemy wyliczony stan.');
        $this->assertSame('2026-04-01', $calculated->reading_date->toDateString());
        $this->assertEqualsWithDelta(151.0, (float) $calculated->consumption, 0.01);
        $this->assertTrue((bool) $water->end_interpolated);
        $this->assertEqualsWithDelta(31.0, (float) $water->consumption, 0.01);
        $this->assertSame('2026-04-04', $water->end_date->toDateString(), 'Na rozliczeniu widać rzeczywisty odczyt.');
    }

    private function reading(string $date, float $value): void
    {
        Reading::create([
            'meter_id' => $this->meter->id,
            'source_table' => Reading::SOURCE_MANUAL,
            'consumption' => $value,
            'reading_date' => $date,
        ]);
    }
}
