<?php

namespace App\Console\Commands;

use App\Services\ModuleReadingConverter;
use App\Services\ReadingSyncService;
use Illuminate\Console\Command;

class LinkReadings extends Command
{
    protected $signature = 'pm:readings-link';

    protected $description = 'Przypisuje odczyty do liczników po numerze seryjnym i przelicza odczyty nakładek';

    public function handle(ReadingSyncService $sync, ModuleReadingConverter $modules): int
    {
        $linked = $sync->linkOrphans();
        $left = $sync->orphanedCount();
        $converted = $modules->convertAll();

        if ($converted > 0) {
            $this->info("Przeliczono odczyty nakładek na stan liczników: {$converted}.");
        }

        $this->info($linked === 0
            ? 'Brak odczytów do przypisania.'
            : "Przypisano odczytów: {$linked}.");

        if ($left > 0) {
            $this->warn("Bez licznika zostaje: {$left} — numer seryjny nie pasuje do żadnego licznika albo pasuje do kilku.");
        }

        return self::SUCCESS;
    }
}
