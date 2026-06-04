<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $table = 'transactions';

    protected $fillable = [
        'user_id',
        'source_wallet_id',
        'destination_wallet_id',
        'type',
        'amount',
        'exchange_amount',
        'fee_amount',
        'fee_currency_id',
        'exchange_rate',
        'exchange_rate_id',
        'reference',
        'external_reference',
        'status',
        'payment_method',
        'description',
        'metadata',
        'completed_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'exchange_amount' => 'integer',
        'fee_amount' => 'integer',
        'exchange_rate' => 'decimal:6',
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sourceWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'source_wallet_id');
    }

    public function destinationWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'destination_wallet_id');
    }

    public function feeCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'fee_currency_id');
    }

    public function exchangeRate(): BelongsTo
    {
        return $this->belongsTo(ExchangeRate::class);
    }

    /**
     * Helper methods
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $reason = null): void
    {
        $this->update([
            'status' => 'failed',
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }

    /**
     * Factory methods
     */
    public static function createDeposit(
        User $user,
        Wallet $destinationWallet,
        int $amountInSmallestUnit,
        string $reference,
        string $paymentMethod = 'stripe'
    ): self {
        return self::create([
            'user_id' => $user->id,
            'destination_wallet_id' => $destinationWallet->id,
            'type' => 'deposit',
            'amount' => $amountInSmallestUnit,
            'reference' => $reference,
            'payment_method' => $paymentMethod,
            'status' => 'completed',
            'completed_at' => now(),
            'description' => "Deposit of {$destinationWallet->currency->code} {$destinationWallet->currency->fromSmallestUnit($amountInSmallestUnit)}",
        ]);
    }

    public static function createTransfer(
        Wallet $sourceWallet,
        Wallet $destinationWallet,
        int $amountInSmallestUnit
    ): self {
        $exchangeRate = null;
        $exchangeAmount = null;
        
        // If currencies differ, calculate exchange
        if ($sourceWallet->currency_id !== $destinationWallet->currency_id) {
            $rate = ExchangeRate::getRate($sourceWallet->currency, $destinationWallet->currency);
            $exchangeAmount = (int) round($amountInSmallestUnit * $rate);
            $exchangeRate = $rate;
        }
        
        return self::create([
            'user_id' => $sourceWallet->user_id,
            'source_wallet_id' => $sourceWallet->id,
            'destination_wallet_id' => $destinationWallet->id,
            'type' => 'transfer',
            'amount' => $amountInSmallestUnit,
            'exchange_amount' => $exchangeAmount,
            'exchange_rate' => $exchangeRate,
            'status' => 'completed',
            'completed_at' => now(),
            'description' => "Transfer to {$destinationWallet->currency->code} wallet",
        ]);
    }
}