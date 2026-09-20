<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->boolean('is_boiler_supply')->default(false)->after('is_main');
        });

        // Liczniki rozpoznawane dotąd po nazwie z config/pm.php dostają znacznik.
        DB::table('meters')
            ->where('type', 'electric')
            ->where('name', config('pm.boiler_meter_name'))
            ->update(['is_boiler_supply' => true]);
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropColumn('is_boiler_supply');
        });
    }
};
