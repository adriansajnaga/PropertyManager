<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->string('model')->nullable()->after('name');
        });

        Schema::table('tenant_settlement_lines', function (Blueprint $table) {
            $table->string('meter_model')->nullable()->after('meter_serial');
        });
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('model');
        });

        Schema::table('tenant_settlement_lines', function (Blueprint $table) {
            $table->dropColumn('meter_model');
        });
    }
};
