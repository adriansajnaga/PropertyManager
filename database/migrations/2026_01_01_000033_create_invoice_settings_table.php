<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dane wystawcy i zasady wystawiania faktur. Trzymane w bazie, bo zmieniają się
 * rzadko, ale muszą być edytowalne bez dostępu do plików na serwerze.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_settings', function (Blueprint $table) {
            $table->id();
            $table->string('seller_name')->nullable();
            $table->string('seller_nip')->nullable();
            $table->string('seller_address_l1')->nullable();
            $table->string('seller_address_l2')->nullable();
            $table->string('seller_phone')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('bank_swift')->nullable();
            $table->string('issue_place')->nullable();
            $table->unsignedSmallInteger('payment_days')->default(7);
            $table->decimal('vat_rate', 5, 2)->default(23);
            // Stawki czynszu w kartotekach lokali wpisywane są w kwocie do zapłaty.
            $table->boolean('rent_is_gross')->default(true);
            $table->string('line_description')->default('Czynsz {miesiac} {rok}');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_settings');
    }
};
