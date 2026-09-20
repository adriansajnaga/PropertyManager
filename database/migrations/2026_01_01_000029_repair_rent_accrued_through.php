<?php

use App\Models\Unit;
use Illuminate\Database\Migrations\Migration;

/**
 * Znacznik naliczenia czynszu przesuwał się nawet w miesiącach, w których lokal nie
 * miał jeszcze najemcy ani stawki — blokowało to pierwsze naliczenie. Od teraz znacznik
 * oznacza ostatni faktycznie naliczony miesiąc, więc ustawiamy go zgodnie z danymi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Unit::query()->whereNotNull('rent_accrued_through')->each(function (Unit $unit) {
            $last = $unit->rentCharges()->max('month');

            $unit->forceFill([
                'rent_accrued_through' => $last ? date('Y-m-01', strtotime((string) $last)) : null,
            ])->save();
        });
    }

    public function down(): void
    {
        // Naprawa danych — nie ma czego cofać.
    }
};
