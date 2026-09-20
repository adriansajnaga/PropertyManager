<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_table');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_meter_serial')->nullable();
            $table->string('source_meter_name')->nullable();
            $table->string('raw_hex')->nullable();
            $table->decimal('consumption', 14, 4);
            $table->date('reading_date');
            $table->timestamp('reading_timestamp')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['source_table', 'source_id']);
            $table->index(['meter_id', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
