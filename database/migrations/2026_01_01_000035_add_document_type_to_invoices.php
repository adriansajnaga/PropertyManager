<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Przy zawieszonej działalności nie wystawia się faktur VAT, tylko rachunki imienne.
 * Rachunek zostaje wyłącznie w aplikacji — do KSeF nie idzie i nie ma stawki podatku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('document_type')->default('invoice')->after('number');
        });

        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->string('receipt_issuer_name')->nullable()->after('line_description');
            $table->string('receipt_address_l1')->nullable()->after('receipt_issuer_name');
            $table->string('receipt_address_l2')->nullable()->after('receipt_address_l1');
            $table->string('receipt_identifier')->nullable()->after('receipt_address_l2');
            $table->string('receipt_note')->nullable()->after('receipt_identifier');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });

        Schema::table('invoice_settings', function (Blueprint $table) {
            $table->dropColumn([
                'receipt_issuer_name', 'receipt_address_l1', 'receipt_address_l2',
                'receipt_identifier', 'receipt_note',
            ]);
        });
    }
};
