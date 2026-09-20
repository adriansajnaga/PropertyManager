<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_settlements', function (Blueprint $table) {
            $table->id();
            $table->date('month');
            $table->foreignId('electric_bill_id')->constrained('utility_bills')->restrictOnDelete();
            $table->decimal('boiler_kwh_consumed', 14, 4);
            $table->decimal('total_gj_consumed', 14, 4);
            $table->decimal('price_per_gj', 12, 4);
            $table->timestamps();

            $table->unique('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_settlements');
    }
};
