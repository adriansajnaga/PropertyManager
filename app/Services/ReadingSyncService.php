<?php

namespace App\Services;

use App\Enums\MeterType;
use App\Models\Meter;
use App\Models\Reading;
use App\Models\SyncState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReadingSyncService
{
    public function syncAll(): array
    {
        $this->meterCache = [];
        $summary = [];

        foreach (config('pm.sync.tables') as $sourceTable => $meterType) {
            $summary[$sourceTable] = $this->syncTable($sourceTable, MeterType::from($meterType));
        }

        return $summary;
    }

    public function syncTable(string $sourceTable, MeterType $meterType): array
    {
        $state = SyncState::firstOrCreate(
            ['source_table' => $sourceTable],
            ['last_synced_id' => 0],
        );

        $state->update(['last_run_at' => now()]);

        $synced = 0;
        $orphaned = 0;
        $batchSize = (int) config('pm.sync.batch_size');

        try {
            while (true) {
                $rows = DB::connection(config('pm.sync.connection'))
                    ->table($sourceTable)
                    ->where('ID', '>', $state->last_synced_id)
                    ->orderBy('ID')
                    ->limit($batchSize)
                    ->get();

                if ($rows->isEmpty()) {
                    break;
                }

                $lastId = $state->last_synced_id;

                DB::transaction(function () use ($rows, $sourceTable, $meterType, &$synced, &$orphaned, &$lastId) {
                    foreach ($rows as $row) {
                        $meter = $this->matchMeter((string) $row->METER, $meterType);

                        Reading::updateOrCreate(
                            [
                                'source_table' => $sourceTable,
                                'source_id' => $row->ID,
                            ],
                            [
                                'meter_id' => $meter?->id,
                                'source_meter_serial' => $row->METER,
                                'source_meter_name' => $row->NAME ?? null,
                                'raw_hex' => $row->HEX ?? null,
                                'consumption' => $this->resolveConsumption($row),
                                'reading_date' => $row->DATE,
                                'reading_timestamp' => $row->TIMESTAMP ?? null,
                                'synced_at' => now(),
                            ],
                        );

                        $meter === null ? $orphaned++ : $synced++;
                        $lastId = (int) $row->ID;
                    }
                });

                $state->update(['last_synced_id' => $lastId]);
            }

            $state->update([
                'last_success_at' => now(),
                'last_error' => null,
            ]);
        } catch (Throwable $e) {
            // Nie ruszamy last_synced_id — kolejny przebieg powtórzy nieudany batch.
            $state->update(['last_error' => $e->getMessage()]);

            Log::warning("Synchronizacja odczytów nieudana [{$sourceTable}]: {$e->getMessage()}");

            return ['synced' => $synced, 'orphaned' => $orphaned, 'error' => $e->getMessage()];
        }

        return ['synced' => $synced, 'orphaned' => $orphaned, 'error' => null];
    }

    /** Pamięć podręczna jednego przebiegu — statyczna przeżywałaby zmiany liczników w procesie. */
    private array $meterCache = [];

    private function matchMeter(string $serial, MeterType $type): ?Meter
    {
        $key = $type->value.'|'.$serial;

        if (! array_key_exists($key, $this->meterCache)) {
            $this->meterCache[$key] = Meter::query()
                ->where('serial_number', $serial)
                ->where('type', $type)
                ->first();
        }

        return $this->meterCache[$key];
    }

    /**
     * Kolektor zapisuje przeliczoną wartość w CONSUMPTION, ale starsze wiersze
     * bywają wypełnione tylko kolumną ENERGY — obsługujemy oba warianty.
     */
    private function resolveConsumption(object $row): float
    {
        foreach (['CONSUMPTION', 'ENERGY', 'VALUE'] as $column) {
            if (isset($row->{$column}) && $row->{$column} !== null && $row->{$column} !== '') {
                return (float) $row->{$column};
            }
        }

        return 0.0;
    }

    /**
     * Podpina nieprzypisane odczyty do liczników po numerze seryjnym — kolektor zna
     * tylko numer licznika, nigdy identyfikatora z bazy aplikacji. Wywołujemy to
     * po synchronizacji, po zapisie licznika i przy wejściu na listę odczytów,
     * bo odczyty bywają wpisywane do tabeli wprost, z pominięciem aplikacji.
     */
    public function linkOrphans(?Meter $only = null): int
    {
        $orphans = Reading::orphaned()
            ->whereNotNull('source_meter_serial')
            ->select('source_table', 'source_meter_serial')
            ->distinct()
            ->get();

        if ($orphans->isEmpty()) {
            return 0;
        }

        $metersBySerial = ($only !== null ? collect([$only]) : Meter::all())
            ->groupBy(fn (Meter $meter) => self::normalizeSerial($meter->serial_number));

        $linked = 0;

        foreach ($orphans as $orphan) {
            $candidates = $metersBySerial->get(self::normalizeSerial($orphan->source_meter_serial), collect());

            // Gdy źródło mówi, o jakie medium chodzi, zawężamy do niego wybór —
            // ten sam numer może nosić licznik wody i licznik prądu.
            $medium = self::mediumOf($orphan->source_table);

            if ($medium !== null) {
                $candidates = $candidates->filter(fn (Meter $meter) => $meter->type->value === $medium);
            }

            // Dwa liczniki o tym samym numerze to zgadywanie — zostawiamy odczyt nieprzypisany.
            if ($candidates->count() !== 1) {
                continue;
            }

            $linked += Reading::orphaned()
                ->where('source_table', $orphan->source_table)
                ->where('source_meter_serial', $orphan->source_meter_serial)
                ->update(['meter_id' => $candidates->first()->id]);
        }

        return $linked;
    }

    /**
     * Medium odczytu wynika ze źródła: kolektor może podać nazwę tabeli
     * (`modbus_electric_readings`) albo wprost medium (`electric`). Gdy nie mówi nic,
     * zwracamy null i o liczniku decyduje sam numer seryjny.
     */
    private static function mediumOf(?string $source): ?string
    {
        $source = trim((string) $source);
        $media = array_map(fn (MeterType $type) => $type->value, MeterType::cases());

        return config('pm.sync.tables')[$source]
            ?? (in_array($source, $media, true) ? $source : null);
    }

    /** Numery bywają zapisane ze spacją albo małymi literami — to wciąż ten sam licznik. */
    private static function normalizeSerial(?string $serial): string
    {
        return mb_strtoupper(trim((string) $serial));
    }

    public function orphanedCount(): int
    {
        return Reading::orphaned()->count();
    }

    public function staleStates()
    {
        return SyncState::all()->filter(fn (SyncState $state) => $state->isStale());
    }
}
