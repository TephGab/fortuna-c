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
            
            // $table->enum('type', [
            //     'deposit', 'withdrawal', 'transfer', 'exchange', 'fee', 'refund', 'reward'
            // ]);

            $table->string('type');
            
            $table->bigInteger('amount')->unsigned();
            $table->bigInteger('exchange_amount')->nullable()->unsigned();
            
            $table->bigInteger('fee_amount')->default(0)->unsigned();
            $table->foreignId('fee_currency_id')->nullable()->constrained('currencies');
            
            $table->decimal('exchange_rate', 12, 6)->nullable();
            $table->foreignId('exchange_rate_id')->nullable()->constrained('exchange_rates');
            
            $table->string('reference')->nullable();
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
