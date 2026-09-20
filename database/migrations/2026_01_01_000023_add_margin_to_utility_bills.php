<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            // net_price zostaje ceną obowiązującą w rozliczeniach (już z marżą),
            // dzięki czemu reszta aplikacji nie musi w ogóle wiedzieć o marży.
            $table->decimal('base_net_price', 12, 4)->nullable()->after('net_price');
            $table->decimal('margin_percent', 5, 2)->default(0)->after('base_net_price');
        });

        DB::table('utility_bills')->update(['base_net_price' => DB::raw('net_price')]);
    }

    public function down(): void
    {
        Schema::table('utility_bills', function (Blueprint $table) {
            $table->dropColumn(['base_net_price', 'margin_percent']);
        });
    }
};
