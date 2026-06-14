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
        Schema::create('vault_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained();
            $table->enum('type', [
                'deposit', 
                'withdrawal', 
                'interest', 
                'penalty', 
                'transfer_in', 
                'transfer_out',
                'maturity'
            ]);
            $table->bigInteger('amount')->default(0);
            $table->bigInteger('balance_after')->default(0);
            $table->string('currency', 3);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['vault_id', 'type']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
        
        // Schema::create('vault_transactions', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('vault_id')->constrained()->onDelete('cascade');
        //     $table->foreignId('user_id')->constrained();
        //     $table->enum('type', ['deposit', 'withdrawal', 'interest', 'penalty', 'transfer_in', 'transfer_out']);
        //     $table->bigInteger('amount');
        //     $table->bigInteger('balance_after');
        //     $table->string('currency', 3);
        //     $table->text('description')->nullable();
        //     $table->json('metadata')->nullable();
        //     $table->timestamps();
            
        //     $table->index(['vault_id', 'type']);
        //     $table->index('created_at');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_transactions');
    }
};
