<?php

namespace Tests\Feature;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\SyncState;
use App\Services\ReadingSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReadingSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.iascomm_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        Config::set('pm.sync.connection', 'iascomm_test');
        Config::set('pm.sync.tables', ['modbus_electric_readings' => 'electric']);

        Schema::connection('iascomm_test')->create('modbus_electric_readings', function ($table) {
            $table->id('ID');
            $table->string('METER');
            $table->string('NAME')->nullable();
            $table->string('HEX')->nullable();
            $table->decimal('CONSUMPTION', 14, 4)->nullable();
            $table->date('DATE');
            $table->timestamp('TIMESTAMP')->nullable();
        });
    }

    public function test_it_imports_rows_and_matches_them_to_meters_by_serial_number(): void
    {
        $meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '12345',
            'name' => 'Lokal 12',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->sourceRow(1, '12345', 1000.5);
        $this->sourceRow(2, '12345', 1100.25);

        $summary = app(ReadingSyncService::class)->syncAll();

        $this->assertSame(2, $summary['modbus_electric_readings']['synced']);
        $this->assertSame(0, $summary['modbus_electric_readings']['orphaned']);
        $this->assertSame(2, Reading::where('meter_id', $meter->id)->count());
        $this->assertSame(2, SyncState::find('modbus_electric_readings')->last_synced_id);
    }

    public function test_rerunning_the_sync_does_not_duplicate_readings(): void
    {
        Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '12345',
            'name' => 'Lokal 12',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->sourceRow(1, '12345', 1000);

        $service = app(ReadingSyncService::class);
        $service->syncAll();

        SyncState::find('modbus_electric_readings')->update(['last_synced_id' => 0]);
        $service->syncAll();

        $this->assertSame(1, Reading::count());
    }

    public function test_unknown_serial_numbers_are_stored_as_orphans_without_stopping_the_batch(): void
    {
        Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '12345',
            'name' => 'Lokal 12',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->sourceRow(1, 'NIEZNANY', 500);
        $this->sourceRow(2, '12345', 1000);

        $summary = app(ReadingSyncService::class)->syncAll();

        $this->assertSame(1, $summary['modbus_electric_readings']['synced']);
        $this->assertSame(1, $summary['modbus_electric_readings']['orphaned']);
        $this->assertSame(1, Reading::orphaned()->count());
        $this->assertSame('NIEZNANY', Reading::orphaned()->first()->source_meter_serial);
    }

    public function test_a_failing_source_leaves_the_cursor_untouched_for_the_next_run(): void
    {
        Schema::connection('iascomm_test')->drop('modbus_electric_readings');

        $summary = app(ReadingSyncService::class)->syncAll();

        $this->assertNotNull($summary['modbus_electric_readings']['error']);
        $this->assertSame(0, SyncState::find('modbus_electric_readings')->last_synced_id);
        $this->assertNull(SyncState::find('modbus_electric_readings')->last_success_at);
    }

    public function test_adding_a_meter_later_picks_up_its_earlier_orphaned_readings(): void
    {
        $this->sourceRow(1, '77777', 500);
        $this->sourceRow(2, '77777', 520);

        app(ReadingSyncService::class)->syncAll();
        $this->assertSame(2, Reading::orphaned()->count());

        $meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '77777',
            'name' => 'Nowy licznik',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->assertSame(0, Reading::orphaned()->count());
        $this->assertSame(2, $meter->readings()->count());
    }

    public function test_correcting_a_serial_number_picks_up_matching_orphans(): void
    {
        $meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '1234S',
            'name' => 'Literówka w numerze',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->sourceRow(1, '12345', 1000);
        app(ReadingSyncService::class)->syncAll();
        $this->assertSame(1, Reading::orphaned()->count());

        $meter->update(['serial_number' => '12345']);

        $this->assertSame(0, Reading::orphaned()->count());
    }

    public function test_readings_written_with_only_a_serial_number_are_linked_by_the_sync_command(): void
    {
        $meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => '12345',
            'name' => 'Lokal 12',
            'is_active' => true,
            'is_main' => false,
        ]);

        // Wariant, w którym kolektor pisze wprost do readings i zna tylko numer seryjny.
        Reading::create([
            'meter_id' => null,
            'source_table' => 'modbus_electric_readings',
            'source_id' => 900,
            'source_meter_serial' => '12345',
            'consumption' => 1500,
            'reading_date' => '2026-03-01',
        ]);

        $this->artisan('readings:sync')
            ->expectsOutputToContain('Podpięto 1 wcześniej nieprzypisanych odczytów')
            ->assertSuccessful();

        $this->assertSame($meter->id, Reading::where('source_id', 900)->value('meter_id'));
    }

    public function test_readings_imported_without_a_source_table_are_linked_by_serial_number(): void
    {
        Reading::create([
            'source_table' => '',
            'source_meter_serial' => 'CB00703',
            'consumption' => 1234,
            'reading_date' => '2026-09-01',
        ]);

        $meter = Meter::create([
            'type' => MeterType::Electric,
            'serial_number' => 'CB00703',
            'name' => 'Lokal 12 — Prąd',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->assertSame(0, Reading::orphaned()->count());
        $this->assertSame($meter->id, Reading::where('source_meter_serial', 'CB00703')->value('meter_id'));
    }

    public function test_an_ambiguous_serial_number_without_a_source_table_stays_unlinked(): void
    {
        foreach ([MeterType::Electric, MeterType::Water] as $type) {
            Meter::create([
                'type' => $type,
                'serial_number' => 'CB00703',
                'name' => 'Licznik '.$type->label(),
                'is_active' => true,
                'is_main' => false,
            ]);
        }

        Reading::create([
            'source_table' => '',
            'source_meter_serial' => 'CB00703',
            'consumption' => 1234,
            'reading_date' => '2026-09-01',
        ]);

        $this->assertSame(0, app(ReadingSyncService::class)->linkOrphans());
        $this->assertSame(1, Reading::orphaned()->count());
    }

    public function test_a_serial_number_is_only_matched_within_the_same_medium(): void
    {
        $this->sourceRow(1, '55555', 100);
        app(ReadingSyncService::class)->syncAll();

        Meter::create([
            'type' => MeterType::Water,
            'serial_number' => '55555',
            'name' => 'Licznik wody o tym samym numerze',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->assertSame(1, Reading::orphaned()->count());
    }

    private function sourceRow(int $id, string $meter, float $consumption): void
    {
        DB::connection('iascomm_test')->table('modbus_electric_readings')->insert([
            'ID' => $id,
            'METER' => $meter,
            'NAME' => 'Obszar testowy',
            'HEX' => dechex((int) $consumption),
            'CONSUMPTION' => $consumption,
            'DATE' => '2026-02-01',
            'TIMESTAMP' => '2026-02-01 00:05:00',
        ]);
    }
}
