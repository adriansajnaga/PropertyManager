<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('email_verified_at');
            $table->boolean('is_admin')->default(false)->after('approved_at');
        });

        // Konta założone przed wprowadzeniem akceptacji zachowują dostęp,
        // a najstarsze z nich zostaje administratorem — inaczej nie byłoby komu zatwierdzać.
        DB::table('users')->update(['approved_at' => now()]);
        DB::table('users')->orderBy('id')->limit(1)->update(['is_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'is_admin']);
        });
    }
};
