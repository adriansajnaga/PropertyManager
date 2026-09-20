<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unit_meter_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->timestamps();

            $table->index(['unit_id', 'valid_from', 'valid_to']);
            $table->index(['meter_id', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unit_meter_assignments');
    }
};
