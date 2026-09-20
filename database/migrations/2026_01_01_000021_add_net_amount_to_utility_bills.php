<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            // Kwota z faktury — na rozliczeniu pokazujemy ją obok zużycia i ceny jednostkowej.
            $table->decimal('net_amount', 12, 2)->nullable()->after('consumption_value');
        });
    }

    public function down(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            $table->dropColumn('net_amount');
        });
    }
};
