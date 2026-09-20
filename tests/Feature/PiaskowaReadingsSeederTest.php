<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use Database\Seeders\PiaskowaReadingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PiaskowaReadingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_first_of_month_readings_and_links_them_to_meters(): void
    {
        $this->seed(PiaskowaReadingsSeeder::class);

        $this->assertSame(27, Reading::count());
        $this->assertSame(0, Reading::orphaned()->count());
        $this->assertSame(4, Meter::count());

        $boiler = Meter::where('serial_number', 'CB01756')->first();
        $this->assertSame(config('pm.boiler_meter_name'), $boiler->name);
        $this->assertSame(7, $boiler->readings()->count());

        $gora = Meter::where('serial_number', 'CB00703')->first();
        $this->assertSame(6, $gora->readings()->count(), 'W źródle brakuje odczytu z 01.04.2026.');
    }

    public function test_readings_only_grow_over_time(): void
    {
        $this->seed(PiaskowaReadingsSeeder::class);

        foreach (Meter::all() as $meter) {
            $values = $meter->readings()->orderBy('reading_date')->pluck('consumption')
                ->map(fn ($v) => (float) $v)->all();

            $sorted = $values;
            sort($sorted);

            $this->assertSame($sorted, $values, "Odczyty licznika {$meter->serial_number} nie rosną.");
        }
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(PiaskowaReadingsSeeder::class);
        $this->seed(PiaskowaReadingsSeeder::class);

        $this->assertSame(27, Reading::count());
        $this->assertSame(4, Meter::count());
    }

    public function test_it_reuses_a_meter_that_already_exists(): void
    {
        $existing = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00703',
            'name' => 'Mój własny opis licznika',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->seed(PiaskowaReadingsSeeder::class);

        $this->assertSame(4, Meter::count());
        $this->assertSame('Mój własny opis licznika', $existing->fresh()->name);
        $this->assertSame('F&F LE-03M', $existing->fresh()->model, 'Brakujący model zostaje uzupełniony.');
        $this->assertSame(6, $existing->readings()->count());
    }

    public function test_it_does_not_overwrite_a_model_entered_by_hand(): void
    {
        $existing = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00836',
            'name' => 'Dół Duży',
            'model' => 'Inny model',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->seed(PiaskowaReadingsSeeder::class);

        $this->assertSame('Inny model', $existing->fresh()->model);
    }
}
