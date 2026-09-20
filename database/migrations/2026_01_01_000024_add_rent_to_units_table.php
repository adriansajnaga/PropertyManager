<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->decimal('rent_amount', 10, 2)->nullable()->after('area');
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('rent_amount');
            $table->date('deposit_paid_on')->nullable()->after('deposit_amount');

            // Do którego miesiąca czynsz został już naliczony — naliczanie idzie
            // tylko do przodu, żeby skasowany szkic nie wracał przy każdym wejściu na listę.
            $table->date('rent_accrued_through')->nullable()->after('deposit_paid_on');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['rent_amount', 'deposit_amount', 'deposit_paid_on', 'rent_accrued_through']);
        });
    }
};
