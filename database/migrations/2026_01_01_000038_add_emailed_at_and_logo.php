<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lista dokumentów ma pokazywać nie tylko to, czy dokument jest w KSeF,
 * ale też czy dotarł do najemcy. Logo trafia na wizualizację PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('ksef_error');
            $table->string('emailed_to')->nullable()->after('emailed_at');
        });

        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('seller_regon');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['emailed_at', 'emailed_to']);
        });

        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
