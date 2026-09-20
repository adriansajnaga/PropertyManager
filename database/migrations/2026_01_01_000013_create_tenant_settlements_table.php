<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete();
            $table->date('month');
            $table->string('status')->default('draft');
            $table->decimal('vat_rate', 5, 4);
            $table->decimal('total_net', 12, 2);
            $table->decimal('total_gross', 12, 2);
            $table->string('pdf_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'month']);
            $table->index('month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settlements');
    }
};
