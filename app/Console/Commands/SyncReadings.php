<?php

namespace App\Console\Commands;

use App\Services\ReadingSyncService;
use Illuminate\Console\Command;

class SyncReadings extends Command
{
    protected $signature = 'readings:sync';

    protected $description = 'Synchronizuje odczyty liczników z bazy iascomm_reader do lokalnej tabeli readings';

    public function handle(ReadingSyncService $service): int
    {
        $summary = $service->syncAll();
        $failed = false;

        foreach ($summary as $table => $result) {
            if ($result['error'] !== null) {
                $failed = true;
                $this->error("{$table}: błąd — {$result['error']}");

                continue;
            }

            $this->info("{$table}: zsynchronizowano {$result['synced']}, nieprzypisanych {$result['orphaned']}");
        }

        $linked = $service->linkOrphans();

        if ($linked > 0) {
            $this->info("Podpięto {$linked} wcześniej nieprzypisanych odczytów do liczników.");
        }

        foreach ($service->staleStates() as $state) {
            $this->warn("Uwaga: {$state->source_table} nie zsynchronizował się poprawnie od ponad ".config('pm.sync.stale_after_hours').' h.');
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
