<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->date('month');
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('draft')->index();
            $table->string('invoice_number')->nullable();
            $table->date('due_on')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            // Jeden czynsz na lokal i miesiąc — naliczanie automatyczne polega na tym,
            // że ponowne uruchomienie niczego nie duplikuje.
            $table->unique(['unit_id', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rent_charges');
    }
};
