<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heat_settlements', function (Blueprint $table) {
            // Odczyty kotłowni zamrożone razem z rozliczeniem — karta rozliczenia
            // ma pokazywać to samo co podgląd przed zapisem.
            $table->string('boiler_meter_serial')->nullable()->after('electric_bill_id');
            $table->string('boiler_meter_name')->nullable()->after('boiler_meter_serial');
            $table->decimal('boiler_start_reading', 14, 4)->nullable()->after('boiler_meter_name');
            $table->decimal('boiler_end_reading', 14, 4)->nullable()->after('boiler_start_reading');
        });
    }

    public function down(): void
    {
        Schema::table('heat_settlements', function (Blueprint $table) {
            $table->dropColumn(['boiler_meter_serial', 'boiler_meter_name', 'boiler_start_reading', 'boiler_end_reading']);
        });
    }
};
