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
         Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('source_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            
            $table->enum('type', [
                'deposit', 'withdrawal', 'transfer', 'exchange', 'fee', 'refund', 'reward'
            ]);
            
            $table->bigInteger('amount')->unsigned();
            $table->bigInteger('exchange_amount')->nullable()->unsigned();
            
            $table->bigInteger('fee_amount')->default(0)->unsigned();
            $table->foreignId('fee_currency_id')->nullable()->constrained('currencies');
            
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            
            $table->string('reference')->nullable()->unique();
            $table->string('external_reference')->nullable();
            
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
            $table->string('payment_method')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Shortened index names
            $table->index(['user_id', 'type', 'status'], 'trans_user_type_status_idx');
            $table->index(['source_wallet_id', 'destination_wallet_id'], 'trans_wallets_idx');
            $table->index('reference', 'trans_ref_idx');
            $table->index('external_reference', 'trans_ext_ref_idx');
            $table->index('created_at', 'trans_created_idx');
        });
        
        //  Schema::create('transactions', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignId('user_id')->constrained();
        //     $table->foreignId('source_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
        //     $table->foreignId('destination_wallet_id')->nullable()->constrained('wallets')->nullOnDelete();
            
        //     $table->string('type');
        //     // Transaction classification
        //     // $table->enum('type', [
        //     //     'deposit',      // Money added from external source
        //     //     'withdrawal',   // Money removed to external source
        //     //     'transfer',     // Between users or own wallets
        //     //     'exchange',     // Currency conversion within same user
        //     //     'fee',          // Platform fee
        //     //     'refund',       // Money returned
        //     //     'reward',       // Bonus/cashback
        //     // ]);
            
        //     // Amount in source currency (smallest unit)
        //     $table->bigInteger('amount')->unsigned();
            
        //     // For exchange transactions: amount in target currency
        //     $table->bigInteger('exchange_amount')->nullable()->unsigned();
            
        //     // Fee information
        //     $table->bigInteger('fee_amount')->default(0)->unsigned();
        //     $table->foreignId('fee_currency_id')->nullable()->constrained('currencies');
            
        //     // Exchange rate used (if applicable)
        //     $table->decimal('exchange_rate', 12, 6)->nullable();
        //     $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            
        //     // Reference tracking
        //     $table->string('reference')->nullable()->unique(); // Payment ID, Order ID
        //     $table->string('external_reference')->nullable(); // Stripe PI, bank ref
            
        //     // Metadata
        //     $table->enum('status', ['pending', 'completed', 'failed', 'cancelled', 'refunded'])->default('pending');
        //     $table->string('payment_method')->nullable(); // stripe, bank_transfer, wise
        //     $table->text('description')->nullable();
        //     $table->json('metadata')->nullable();
            
        //     // Timestamps
        //     $table->timestamp('completed_at')->nullable();
        //     $table->timestamps();
            
        //     // Indexes
        //     $table->index(['user_id', 'type', 'status']);
        //     $table->index(['source_wallet_id', 'destination_wallet_id']);
        //     $table->index('reference');
        //     $table->index('external_reference');
        //     $table->index('created_at');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
