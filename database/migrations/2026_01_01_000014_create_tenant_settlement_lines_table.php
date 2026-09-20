<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settlement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_settlement_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('label');
            $table->string('meter_serial')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('start_reading', 14, 4)->nullable();
            $table->decimal('end_reading', 14, 4)->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('consumption', 14, 4)->nullable();
            $table->string('consumption_unit')->nullable();
            $table->decimal('unit_price', 12, 4)->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settlement_lines');
    }
};
