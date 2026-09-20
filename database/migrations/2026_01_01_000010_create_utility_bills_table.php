<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('utility_bills', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('invoice_number');
            $table->decimal('net_price', 12, 4);
            $table->decimal('consumption_value', 14, 4)->nullable();
            $table->date('month');
            $table->timestamps();

            $table->index(['type', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('utility_bills');
    }
};
