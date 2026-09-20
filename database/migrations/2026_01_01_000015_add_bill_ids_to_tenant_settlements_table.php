<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settlements', function (Blueprint $table) {
            $table->foreignId('water_bill_id')->nullable()->after('tenant_id')
                ->constrained('utility_bills')->nullOnDelete();
            $table->foreignId('electric_bill_id')->nullable()->after('water_bill_id')
                ->constrained('utility_bills')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settlements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('water_bill_id');
            $table->dropConstrainedForeignId('electric_bill_id');
        });
    }
};
