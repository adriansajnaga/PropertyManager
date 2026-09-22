<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReadingTimeTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00703',
            'name' => 'Piaskowa 27 Góra',
            'is_active' => true,
            'is_main' => false,
        ]);
    }

    public function test_a_reading_from_the_collector_shows_the_time_it_was_taken(): void
    {
        DB::table('readings')->insert([
            'meter_id' => $this->meter->id,
            'source_table' => 'kolektor',
            'source_meter_serial' => 'CB00703',
            'consumption' => 1234.5678,
            'reading_date' => '2026-09-22',
            'reading_timestamp' => '2026-09-22 06:15:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('22.09.2026, 06:15', Reading::first()->measuredAtLabel());

        $this->get(route('readings.index'))->assertOk()->assertSee('22.09.2026, 06:15');
    }

    public function test_the_time_written_straight_into_reading_date_is_shown(): void
    {
        // Kolektor zapisuje moment odczytu w kolumnie reading_date (typu DATETIME).
        DB::table('readings')->insert([
            'meter_id' => $this->meter->id,
            'source_table' => 'kolektor',
            'source_meter_serial' => 'CB00703',
            'consumption' => 2345.6789,
            'reading_date' => '2026-09-22 18:42:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reading = Reading::first();

        $this->assertTrue($reading->hasTime());
        $this->assertSame('22.09.2026, 18:42', $reading->measuredAtLabel());
        $this->assertSame('2026-09-22', $reading->reading_date->toDateString());

        $this->get(route('readings.index'))->assertOk()->assertSee('22.09.2026, 18:42');
    }

    public function test_readings_of_one_day_are_ordered_by_the_time_in_reading_date(): void
    {
        foreach (['2026-09-22 06:00:00' => 1000, '2026-09-22 18:00:00' => 1100] as $moment => $value) {
            DB::table('readings')->insert([
                'meter_id' => $this->meter->id,
                'source_table' => 'kolektor',
                'source_meter_serial' => 'CB00703',
                'consumption' => $value,
                'reading_date' => $moment,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $html = $this->get(route('readings.index'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, '22.09.2026, 06:00'),
            strpos($html, '22.09.2026, 18:00'),
        );
    }

    public function test_a_reading_without_a_timestamp_still_shows_just_the_date(): void
    {
        $reading = Reading::create([
            'meter_id' => $this->meter->id,
            'source_table' => Reading::SOURCE_MANUAL,
            'consumption' => 1000,
            'reading_date' => '2026-09-22',
        ]);

        $this->assertFalse($reading->hasTime());
        $this->assertSame('22.09.2026', $reading->measuredAtLabel());
        $this->assertSame('2026-09-22', $reading->measuredAt()->toDateString());
    }

    public function test_two_readings_from_the_same_day_are_listed_newest_first(): void
    {
        foreach (['06:00' => 1000, '18:00' => 1100] as $time => $value) {
            Reading::create([
                'meter_id' => $this->meter->id,
                'source_table' => Reading::SOURCE_MANUAL,
                'consumption' => $value,
                'reading_date' => '2026-09-22',
                'reading_timestamp' => '2026-09-22 '.$time.':00',
            ]);
        }

        $html = $this->get(route('readings.index'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, '22.09.2026, 06:00'),
            strpos($html, '22.09.2026, 18:00'),
            'Późniejszy odczyt z tego samego dnia ma być wyżej na liście.',
        );
    }
}
