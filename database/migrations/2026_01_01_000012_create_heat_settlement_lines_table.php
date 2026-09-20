<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heat_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('heat_settlement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('meter_serial')->nullable();
            $table->string('meter_name')->nullable();
            $table->decimal('start_reading', 14, 4)->nullable();
            $table->decimal('end_reading', 14, 4)->nullable();
            $table->decimal('gj_consumed', 14, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heat_settlement_lines');
    }
};
