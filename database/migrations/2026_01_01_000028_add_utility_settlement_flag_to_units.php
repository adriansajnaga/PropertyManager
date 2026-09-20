<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // Lokal z własnymi licznikami rozlicza media bezpośrednio z dostawcami.
            $table->boolean('settles_utilities')->default(true)->after('rent_accrued_through');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('settles_utilities');
        });
    }
};
