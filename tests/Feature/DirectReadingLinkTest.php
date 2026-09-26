<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Kolektor zapisuje odczyty wprost do tabeli `readings`, znając wyłącznie numer
 * seryjny licznika. Takie wiersze muszą same znaleźć swój licznik.
 */
class DirectReadingLinkTest extends TestCase
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

    /** Zapis z pominięciem Eloquenta — dokładnie tak, jak robi to kolektor. */
    private function insertRaw(array $attributes = []): int
    {
        return DB::table('readings')->insertGetId(array_merge([
            'source_table' => 'kolektor',
            'source_meter_serial' => 'CB00703',
            'consumption' => 1234.5678,
            'reading_date' => '2026-09-22',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attributes));
    }

    public function test_opening_the_readings_list_links_a_reading_written_straight_into_the_table(): void
    {
        $id = $this->insertRaw();

        $this->assertNull(Reading::find($id)->meter_id);

        $this->get(route('readings.index'))
            ->assertOk()
            ->assertSee('Przypisano odczyty po numerze seryjnym: 1');

        $this->assertSame($this->meter->id, Reading::find($id)->meter_id);
    }

    public function test_the_dashboard_links_them_too(): void
    {
        $id = $this->insertRaw();

        $this->get(route('overview'))->assertOk();

        $this->assertSame($this->meter->id, Reading::find($id)->meter_id);
    }

    public function test_the_lists_show_the_unit_next_to_the_reading(): void
    {
        $this->insertRaw();

        // Jednostka i dokładność biorą się z medium licznika: prąd to kWh z jednym miejscem.
        $this->get(route('readings.index'))->assertOk()->assertSee('1 234,6 kWh');
        $this->get(route('overview'))->assertOk()->assertSee('1 234,6 kWh');
    }

    public function test_an_unlinked_reading_takes_its_unit_from_the_medium_in_the_source(): void
    {
        $id = $this->insertRaw([
            'source_table' => 'water',
            'source_meter_serial' => 'NIEZNANY',
            'consumption' => 12.3456,
        ]);

        $this->assertNull(Reading::find($id)->meter_id);
        $this->assertSame('m³', Reading::find($id)->unit());

        $this->get(route('readings.index'))->assertOk()->assertSee('12,346 m³');
    }

    public function test_the_command_links_them_for_the_scheduler(): void
    {
        $id = $this->insertRaw();

        $this->artisan('pm:readings-link')
            ->expectsOutputToContain('Przypisano odczytów: 1.')
            ->assertSuccessful();

        $this->assertSame($this->meter->id, Reading::find($id)->meter_id);
    }

    public function test_spaces_and_lower_case_in_the_serial_number_do_not_break_the_match(): void
    {
        $id = $this->insertRaw(['source_meter_serial' => ' cb00703 ']);

        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->assertSame($this->meter->id, Reading::find($id)->meter_id);
    }

    public function test_an_unknown_serial_number_stays_unlinked_and_is_reported(): void
    {
        $id = $this->insertRaw(['source_meter_serial' => 'NIEZNANY']);

        $this->artisan('pm:readings-link')
            ->expectsOutputToContain('Bez licznika zostaje: 1')
            ->assertSuccessful();

        $this->assertNull(Reading::find($id)->meter_id);

        $this->get(route('readings.index', ['orphaned' => 1]))
            ->assertOk()
            ->assertSee('NIEZNANY');
    }

    public function test_the_source_may_name_the_medium_instead_of_a_table(): void
    {
        // Kolektor wysyła `medium=water`, więc w source_table ląduje samo medium.
        $water = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'CB00703',
            'name' => 'Lokal 1 — Woda',
            'is_active' => true,
            'is_main' => false,
        ]);

        $electric = $this->insertRaw(['source_table' => 'electric']);
        $waterReading = $this->insertRaw(['source_table' => 'water']);

        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->assertSame($this->meter->id, Reading::find($electric)->meter_id);
        $this->assertSame($water->id, Reading::find($waterReading)->meter_id);
    }

    public function test_the_medium_from_the_source_table_still_wins_over_a_repeated_serial_number(): void
    {
        $water = Meter::create([
            'type' => MeterType::Water,
            'serial_number' => 'CB00703',
            'name' => 'Lokal 1 — Woda',
            'is_active' => true,
            'is_main' => false,
        ]);

        $id = $this->insertRaw(['source_table' => 'wmbus_water_readings']);

        $this->artisan('pm:readings-link')->assertSuccessful();

        $this->assertSame($water->id, Reading::find($id)->meter_id);
    }
}
