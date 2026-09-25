<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dostęp do Krajowego Systemu e-Faktur. Token KSeF jest sekretem
 * uwierzytelniającym, więc trzymamy go zaszyfrowanego kluczem aplikacji.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ksef_settings', function (Blueprint $table) {
            $table->id();
            $table->string('environment')->default('test');
            $table->string('nip')->nullable();
            $table->text('token')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ksef_settings');
    }
};
