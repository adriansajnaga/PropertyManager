<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number');
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            // Faktura za czynsz powstaje z naliczenia i wraca do niego numerem oraz terminem.
            $table->foreignId('rent_charge_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->date('issued_on');
            $table->date('sold_on');
            $table->date('due_on');
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('total_net', 12, 2);
            $table->decimal('total_vat', 12, 2);
            $table->decimal('total_gross', 12, 2);
            $table->string('status')->default('draft');
            $table->string('ksef_number')->nullable();
            $table->string('ksef_reference')->nullable();
            $table->timestamp('ksef_sent_at')->nullable();
            $table->text('ksef_error')->nullable();
            $table->longText('xml')->nullable();
            $table->timestamps();

            $table->unique('number');
            $table->index('issued_on');
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('name');
            $table->string('unit')->default('szt.');
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('unit_price_net', 12, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('net', 12, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('gross', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
