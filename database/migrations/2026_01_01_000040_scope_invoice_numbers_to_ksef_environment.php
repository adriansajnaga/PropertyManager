<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Numer faktury należy do serii konkretnego środowiska KSeF: ta sama „1/9/2026"
 * może istnieć w testowym i w produkcyjnym. Podobnie jedno naliczenie czynszu
 * może mieć dokument ćwiczebny i prawdziwy — dlatego oba unikaty rozluźniamy,
 * a pilnowaniem jednego dokumentu na naliczenie zajmuje się aplikacja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique('invoices_document_type_number_unique');
            $table->unique(['document_type', 'number', 'ksef_environment']);

            $table->dropUnique('invoices_rent_charge_id_unique');
            $table->index('rent_charge_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['document_type', 'number', 'ksef_environment']);
            $table->unique(['document_type', 'number']);

            $table->dropIndex(['rent_charge_id']);
            $table->unique('rent_charge_id');
        });
    }
};
