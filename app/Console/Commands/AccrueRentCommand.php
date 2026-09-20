<?php

namespace App\Console\Commands;

use App\Services\RentAccrualService;
use Illuminate\Console\Command;

class AccrueRentCommand extends Command
{
    protected $signature = 'pm:rent-accrue';

    protected $description = 'Nalicza czynsz wynajętym lokalom do bieżącego miesiąca włącznie';

    public function handle(RentAccrualService $accrual): int
    {
        $created = $accrual->run();

        $this->info($created === 0
            ? 'Wszystkie lokale mają aktualne naliczenia czynszu.'
            : "Naliczono czynsz: {$created} nowych pozycji.");

        return self::SUCCESS;
    }
}
