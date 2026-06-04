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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('currency_id')->constrained('currencies');
            $table->bigInteger('balance')->default(0);
            $table->boolean('is_default')->default(false);
            $table->string('name')->nullable();
            $table->timestamps();
            
             // Shortened index names
            $table->unique(['user_id', 'currency_id'], 'wallets_user_currency_unique');
            $table->index(['user_id', 'is_default'], 'wallets_user_default_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
