<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
         Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('base_currency_id')->constrained('currencies');
            $table->foreignId('target_currency_id')->constrained('currencies');
            $table->decimal('rate', 12, 6);
            $table->decimal('previous_rate', 12, 6)->nullable();
            $table->decimal('change_percentage', 8, 4)->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

             // Shortened index names
            $table->unique(['base_currency_id', 'target_currency_id'], 'ex_rates_unique');
            $table->index(['base_currency_id', 'target_currency_id', 'fetched_at'], 'ex_rates_curr_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
