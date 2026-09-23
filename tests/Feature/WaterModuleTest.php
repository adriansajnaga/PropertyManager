<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use App\Services\ModuleReadingConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Wodomierz jest mechaniczny, a odczyt zdalny robi nakładka radiowa, która liczy
 * dalej od stanu zastanego u poprzedniego użytkownika.
 */
class WaterModuleTest extends TestCase
{
    use RefreshDatabase;

    private Meter $meter;

    private Meter $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->meter = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => '73206437',
            'name' => 'Lokal 1 — Woda',
            'model' => 'Apator JS16-02',
            'is_active' => true,
            'is_main' => false,
        ]);

        // Nakładka pokazuje 152,345 m³, a wodomierz pod nią 3,200 m³.
        $this->module = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'WM00123',
            'name' => 'Nakładka Lokal 1',
            'model' => 'Apator AT-WMBUS-16-2',
            'is_active' => true,
            'is_main' => false,
            'is_module' => true,
            'module_for_meter_id' => $this->meter->id,
            'module_offset' => -149.145,
        ]);
    }

    private function moduleReading(string $value, string $moment = '2026-09-24 06:15:00'): int
    {
        return DB::table('readings')->insertGetId([
            'source_table' => 'water',
            'source_meter_serial' => $this->module->serial_number,
            'consumption' => $value,
            'reading_date' => $moment,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_a_module_reading_becomes_the_state_of_the_meter_under_it(): void
    {
        $rawId = $this->moduleReading('152.3450');

        $this->artisan('pm:readings-link')->assertSuccessful();

        // Surowy odczyt zostaje przy nakładce…
        $raw = Reading::find($rawId);
        $this->assertSame($this->module->id, $raw->meter_id);

        // …a na koncie wodomierza pojawia się przeliczony stan.
        $converted = Reading::where('meter_id', $this->meter->id)->sole();

        $this->assertSame('3.2000', $converted->consumption);
        $this->assertSame(Reading::SOURCE_MODULE, $converted->source_table);
        $this->assertSame($rawId, (int) $converted->source_id);
        $this->assertSame('nakładka WM00123', $converted->source_meter_name);
        $this->assertSame('24.09.2026, 06:15', $converted->measuredAtLabel());
        $this->assertSame('z nakładki', $converted->sourceLabel());
    }

    public function test_repeating_the_conversion_does_not_duplicate_anything(): void
    {
        $this->moduleReading('152.3450');

        $this->artisan('pm:readings-link')->assertSuccessful();
        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->assertSame(1, Reading::where('meter_id', $this->meter->id)->count());
    }

    public function test_correcting_the_difference_recalculates_the_readings_already_made(): void
    {
        $this->moduleReading('152.3450');
        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->module->update(['module_offset' => -150]);

        $this->assertSame('2.3450', Reading::where('meter_id', $this->meter->id)->sole()->consumption);
        $this->assertSame(1, Reading::where('meter_id', $this->meter->id)->count());
    }

    public function test_a_difference_that_would_push_the_meter_below_zero_is_refused(): void
    {
        $this->module->update(['module_offset' => -1000]);
        $this->moduleReading('152.3450');

        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->assertSame(0, Reading::where('meter_id', $this->meter->id)->count());
    }

    public function test_the_converted_readings_drive_the_consumption_of_the_meter(): void
    {
        $this->moduleReading('152.3450', '2026-09-01 06:00:00');
        $this->moduleReading('154.8450', '2026-10-01 06:00:00');

        $this->artisan('pm:readings-link')->assertSuccessful();

        $states = Reading::where('meter_id', $this->meter->id)
            ->orderBy('reading_date')
            ->pluck('consumption')
            ->map(fn ($value) => (float) $value)
            ->all();

        $this->assertSame([3.2, 5.7], $states);
        $this->assertSame(2.5, round($states[1] - $states[0], 4), 'Zużycie liczy się z przeliczonych stanów wodomierza.');
    }

    public function test_the_form_records_a_module_and_demands_the_meter_it_sits_on(): void
    {
        $this->post(route('meters.store'), [
            'type' => MeterType::Water->value,
            'serial_number' => 'WM00999',
            'name' => 'Nakładka Lokal 2',
            'is_active' => '1',
            'is_module' => '1',
        ])->assertSessionHasErrors('module_for_meter_id');

        $this->post(route('meters.store'), [
            'type' => MeterType::Water->value,
            'serial_number' => 'WM00999',
            'name' => 'Nakładka Lokal 2',
            'is_active' => '1',
            'is_module' => '1',
            'module_for_meter_id' => $this->meter->id,
            'module_offset' => '-12.5',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $module = Meter::where('serial_number', 'WM00999')->sole();

        $this->assertTrue($module->is_module);
        $this->assertSame($this->meter->id, $module->module_for_meter_id);
        $this->assertSame('-12.5000', $module->module_offset);
    }

    public function test_a_module_is_not_offered_as_a_meter_of_a_unit(): void
    {
        $html = $this->get(route('units.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Lokal 1 — Woda', $html);
        $this->assertStringNotContainsString('Nakładka Lokal 1', $html);
    }
}
