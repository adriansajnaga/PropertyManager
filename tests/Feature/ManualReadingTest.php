<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualReadingTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->meter = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'W-12',
            'name' => 'Lokal 12 — Woda',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->reading('2026-02-01', 120);
        $this->reading('2026-03-01', 135);
    }

    public function test_a_reading_lower_than_the_previous_one_is_rejected(): void
    {
        $this->post(route('readings.store'), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-03-15',
            'consumption' => 130,
        ])->assertSessionHasErrors(['consumption' => 'Odczyt nie może być mniejszy od poprzedniego: 135,00 z dnia 01.03.2026.']);

        $this->assertSame(2, Reading::count());
    }

    public function test_a_reading_equal_to_or_above_the_previous_one_is_accepted(): void
    {
        $this->post(route('readings.store'), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-03-15',
            'consumption' => 135,
        ])->assertSessionHasNoErrors();

        $this->post(route('readings.store'), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-04-01',
            'consumption' => 142.5,
        ])->assertSessionHasNoErrors();

        $this->assertSame(4, Reading::count());
    }

    public function test_a_backdated_reading_must_fit_between_its_neighbours(): void
    {
        $this->post(route('readings.store'), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-02-15',
            'consumption' => 140,
        ])->assertSessionHasErrors(['consumption' => 'Odczyt nie może być większy od późniejszego odczytu: 135,00 z dnia 01.03.2026.']);

        $this->post(route('readings.store'), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-02-15',
            'consumption' => 128,
        ])->assertSessionHasNoErrors();
    }

    public function test_editing_a_reading_does_not_compare_it_with_itself(): void
    {
        $latest = Reading::where('consumption', 135)->first();

        $this->put(route('readings.update', $latest), [
            'meter_id' => $this->meter->id,
            'reading_date' => '2026-03-01',
            'consumption' => 133,
        ])->assertSessionHasNoErrors();

        $this->assertSame('133.0000', $latest->fresh()->consumption);
    }

    public function test_the_form_lists_recent_readings_of_each_meter(): void
    {
        $this->get(route('readings.create', ['meter_id' => $this->meter->id]))
            ->assertOk()
            ->assertSee('Ostatnie odczyty licznika')
            ->assertSee('01.03.2026')
            ->assertSee('01.02.2026');
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
