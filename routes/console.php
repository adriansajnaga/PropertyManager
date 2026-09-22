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

// Kolektor zapisuje odczyty wprost do tabeli, znając tylko numer seryjny licznika.
// To zadanie dowiązuje je do liczników; bez harmonogramu robi to wejście na listę odczytów.
Schedule::command('pm:readings-link')
    ->everyFifteenMinutes()
    ->withoutOverlapping();
