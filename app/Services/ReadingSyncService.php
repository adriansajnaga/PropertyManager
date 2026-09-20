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
     * Podpina nieprzypisane odczyty do liczników po numerze seryjnym — np. gdy
     * licznik dodano w aplikacji już po tym, jak spłynęły jego odczyty. Rodzaj
     * licznika wynika z tabeli źródłowej, bo ten sam numer może mieć licznik wody i prądu.
     */
    public function linkOrphans(?Meter $only = null): int
    {
        $linked = 0;

        foreach (config('pm.sync.tables') as $sourceTable => $type) {
            if ($only !== null && $only->type->value !== $type) {
                continue;
            }

            $meters = Meter::query()
                ->where('type', $type)
                ->when($only, fn ($q) => $q->whereKey($only->id))
                ->whereIn('serial_number', Reading::orphaned()
                    ->where('source_table', $sourceTable)
                    ->select('source_meter_serial'))
                ->get();

            foreach ($meters as $meter) {
                $linked += Reading::orphaned()
                    ->where('source_table', $sourceTable)
                    ->where('source_meter_serial', $meter->serial_number)
                    ->update(['meter_id' => $meter->id]);
            }
        }

        return $linked + $this->linkOrphansWithUnknownSource($only);
    }

    /**
     * Odczyty wpisane do bazy bez rozpoznawalnej tabeli źródłowej (np. ręczny import)
     * nie mówią, jakiego medium dotyczą. Wiążemy je tylko wtedy, gdy numer seryjny
     * wskazuje dokładnie jeden licznik — przy dwóch takich numerach byłoby to zgadywanie.
     */
    private function linkOrphansWithUnknownSource(?Meter $only): int
    {
        $knownSources = array_merge(array_keys(config('pm.sync.tables')), [Reading::SOURCE_MANUAL]);
        $linked = 0;

        $serials = Reading::orphaned()
            ->whereNotIn('source_table', $knownSources)
            ->whereNotNull('source_meter_serial')
            ->when($only, fn ($q) => $q->where('source_meter_serial', $only->serial_number))
            ->distinct()
            ->pluck('source_meter_serial');

        foreach ($serials as $serial) {
            $meters = Meter::query()->where('serial_number', $serial)->get();

            if ($meters->count() !== 1) {
                continue;
            }

            $linked += Reading::orphaned()
                ->whereNotIn('source_table', $knownSources)
                ->where('source_meter_serial', $serial)
                ->update(['meter_id' => $meters->first()->id]);
        }

        return $linked;
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
