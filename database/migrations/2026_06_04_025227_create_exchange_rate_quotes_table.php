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
        Schema::create('exchange_rate_quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_id')->unique();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('from_currency_id')->constrained('currencies');
            $table->foreignId('to_currency_id')->constrained('currencies');
            $table->foreignId('exchange_rate_id')->constrained('exchange_rates');
            $table->decimal('rate', 12, 6);
            $table->bigInteger('amount_from')->unsigned();
            $table->bigInteger('amount_to')->unsigned();
            $table->bigInteger('fee')->default(0);
            $table->enum('status', ['pending', 'locked', 'expired', 'used'])->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            // Shortened index names
            $table->index(['user_id', 'status'], 'quotes_user_status_idx');
            $table->index('expires_at', 'quotes_expires_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_quotes');
    }
};
