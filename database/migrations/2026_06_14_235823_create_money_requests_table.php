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
        Schema::create('money_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users');
            $table->bigInteger('amount');
            $table->string('currency_code', 3);
            $table->string('description')->nullable();
            $table->enum('status', ['pending', 'completed', 'expired', 'cancelled'])->default('pending');
            $table->string('request_token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'expires_at']);
            $table->index('request_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('money_requests');
    }
};
