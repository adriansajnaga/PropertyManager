<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wodomierze są mechaniczne, a odczyt zdalny robi nakładka radiowa (np. Apator
 * AT-WMBUS-16-2). Nakładka bywa używana wcześniej gdzie indziej, więc jej licznik
 * startuje od zastanej wartości — stan wodomierza to odczyt nakładki powiększony
 * o stałą różnicę (ujemną, gdy nakładka „przyniosła" ze sobą zużycie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->boolean('is_module')->default(false)->after('is_boiler_supply');
            $table->foreignId('module_for_meter_id')->nullable()->after('is_module')
                ->constrained('meters')->nullOnDelete();
            $table->decimal('module_offset', 14, 4)->default(0)->after('module_for_meter_id');
        });
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('module_for_meter_id');
            $table->dropColumn(['is_module', 'module_offset']);
        });
    }
};
