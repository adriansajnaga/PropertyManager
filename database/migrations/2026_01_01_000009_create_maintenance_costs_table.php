<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('annual_cost', 12, 2);
            $table->unsignedSmallInteger('year');
            $table->timestamps();

            $table->index(['property_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_costs');
    }
};
