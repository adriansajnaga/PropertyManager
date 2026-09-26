<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faktura pamięta, do którego środowiska KSeF trafiła. Bez tego po przełączeniu
 * ustawień na produkcję link weryfikacyjny starej faktury prowadziłby pod adres
 * produkcyjny, gdzie tego dokumentu nie ma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('ksef_environment')->nullable()->after('ksef_reference');
        });

        // Wszystko, co dotąd poszło do KSeF, wysłaliśmy z bieżącego ustawienia.
        $current = DB::table('ksef_settings')->value('environment') ?? 'test';

        DB::table('invoices')->whereNotNull('ksef_number')->update(['ksef_environment' => $current]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('ksef_environment');
        });
    }
};
