<?php

namespace App\Console\Commands;

use App\Services\ReadingSyncService;
use Illuminate\Console\Command;

class LinkReadings extends Command
{
    protected $signature = 'pm:readings-link';

    protected $description = 'Przypisuje nieprzypisane odczyty do liczników po numerze seryjnym';

    public function handle(ReadingSyncService $sync): int
    {
        $linked = $sync->linkOrphans();
        $left = $sync->orphanedCount();

        $this->info($linked === 0
            ? 'Brak odczytów do przypisania.'
            : "Przypisano odczytów: {$linked}.");

        if ($left > 0) {
            $this->warn("Bez licznika zostaje: {$left} — numer seryjny nie pasuje do żadnego licznika albo pasuje do kilku.");
        }

        return self::SUCCESS;
    }
}
