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
        Schema::create('vaults', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('wallet_id')->constrained()->onDelete('cascade');
            $table->string('name', 100);
            $table->string('icon', 10)->default('💰');
            $table->enum('type', ['flexible', 'locked_30', 'locked_90', 'locked_180', 'locked_365']);
            $table->enum('status', ['active', 'locked', 'matured', 'closed'])->default('active');
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('withdrawable_balance')->default(0); // NEW - What user can take now
            $table->bigInteger('original_balance')->default(0);
            $table->bigInteger('interest_earned')->default(0);
            $table->decimal('interest_rate', 5, 2)->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('matures_at')->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('locked_until');
            $table->index('matures_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vaults');
    }
};
