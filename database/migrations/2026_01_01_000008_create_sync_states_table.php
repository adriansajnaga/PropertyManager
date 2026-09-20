<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_states', function (Blueprint $table) {
            $table->string('source_table')->primary();
            $table->unsignedBigInteger('last_synced_id')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_states');
    }
};
