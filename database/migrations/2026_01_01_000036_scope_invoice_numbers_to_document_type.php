<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faktury i rachunki mają osobne serie numerów, więc ten sam numer może wystąpić
 * raz jako faktura i raz jako rachunek. Unikalność obowiązuje w obrębie rodzaju.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_number_unique');
            $table->unique(['document_type', 'number']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['document_type', 'number']);
            $table->unique('number');
        });
    }
};
