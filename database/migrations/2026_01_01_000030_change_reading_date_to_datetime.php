<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolektor zapisuje moment odczytu z dokładnością do minuty, a nie tylko dzień.
 * Kolumna trzyma więc datę z godziną; rozliczenia dalej pracują na samych datach
 * (whereDate), więc granice miesięcy zachowują się jak dotąd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->dateTime('reading_date')->change();
        });
    }

    public function down(): void
    {
        Schema::table('readings', function (Blueprint $table) {
            $table->date('reading_date')->change();
        });
    }
};
