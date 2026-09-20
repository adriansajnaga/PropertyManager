<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settlement_lines', function (Blueprint $table) {
            // start_reading/end_reading to rzeczywiste odczyty (informacja na rozliczeniu),
            // a *_state to stan licznika na pierwszy dzień miesiąca, z którego liczone jest zużycie.
            $table->decimal('start_state', 14, 4)->nullable()->after('end_reading');
            $table->decimal('end_state', 14, 4)->nullable()->after('start_state');
            $table->boolean('start_interpolated')->default(false)->after('end_state');
            $table->boolean('end_interpolated')->default(false)->after('start_interpolated');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settlement_lines', function (Blueprint $table) {
            $table->dropColumn(['start_state', 'end_state', 'start_interpolated', 'end_interpolated']);
        });
    }
};
