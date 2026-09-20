<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('serial_number');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_main')->default(false);
            $table->timestamps();

            $table->unique(['type', 'serial_number']);
            $table->index('serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meters');
    }
};
