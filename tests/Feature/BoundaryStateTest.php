<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Services\ReadingLookup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoundaryStateTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00703',
            'name' => 'Góra',
            'is_active' => true,
            'is_main' => false,
        ]);
    }

    public function test_a_reading_on_the_boundary_is_used_as_is(): void
    {
        $this->reading('2026-03-01', 100);

        $state = app(ReadingLookup::class)->stateAt($this->meter, '2026-03-01');

        $this->assertSame(100.0, $state['state']);
        $this->assertFalse($state['interpolated']);
        $this->assertSame('2026-03-01', $state['reading']->reading_date->toDateString());
    }

    public function test_a_missing_boundary_reading_is_interpolated_between_neighbours(): void
    {
        $this->reading('2026-02-28', 100);
        $this->reading('2026-03-04', 140);

        $state = app(ReadingLookup::class)->stateAt($this->meter, '2026-03-01');

        // 4 dni między odczytami, 1 dzień do granicy: 100 + 40 × 1/4
        $this->assertSame(110.0, $state['state']);
        $this->assertTrue($state['interpolated']);
        $this->assertSame('2026-02-28', $state['reading']->reading_date->toDateString(), 'Pokazujemy odczyt bliższy granicy.');
    }

    public function test_the_nearest_actual_reading_is_reported_for_information(): void
    {
        $this->reading('2026-02-20', 100);
        $this->reading('2026-03-04', 170);

        $state = app(ReadingLookup::class)->stateAt($this->meter, '2026-03-01');

        $this->assertSame('2026-03-04', $state['reading']->reading_date->toDateString());
        $this->assertTrue($state['interpolated']);
    }

    public function test_without_a_later_reading_the_last_known_state_is_used(): void
    {
        $this->reading('2026-02-20', 100);

        $state = app(ReadingLookup::class)->stateAt($this->meter, '2026-03-01');

        $this->assertSame(100.0, $state['state']);
        $this->assertFalse($state['interpolated']);
    }

    public function test_before_the_first_reading_nothing_is_guessed(): void
    {
        $this->reading('2026-03-04', 140);

        $state = app(ReadingLookup::class)->stateAt($this->meter, '2026-03-01');

        $this->assertNull($state['state']);
        $this->assertFalse($state['interpolated']);
        $this->assertSame('2026-03-04', $state['reading']->reading_date->toDateString());
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
