<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('readings:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

// Naliczenie czynszu pierwszego dnia miesiąca. Bez harmonogramu i tak zadziała —
// listę czynszów uzupełnia to samo naliczenie przy każdym otwarciu.
Schedule::command('pm:rent-accrue')
    ->monthlyOn(1, '00:10')
    ->withoutOverlapping();
