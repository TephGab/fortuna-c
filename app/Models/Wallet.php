<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id', 'currency_id', 'balance', 'is_default', 'name'
    ];

    protected $casts = [
        'balance' => 'integer',
        'is_default' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function outgoingTransactions()
    {
        return $this->hasMany(Transaction::class, 'source_wallet_id');
    }

    public function incomingTransactions()
    {
        return $this->hasMany(Transaction::class, 'destination_wallet_id');
    }

    /**
     * Balance operations
     */
    public function increaseBalance(int $amount): void
    {
        $this->increment('balance', $amount);
    }

    public function decreaseBalance(int $amount): void
    {
        $this->decrement('balance', $amount);
    }

    public function hasSufficientBalance(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Accessors
     */
    public function getFormattedBalanceAttribute(): string
    {
        return $this->currency->format($this->balance);
    }

    public function getBalanceFloatAttribute(): float
    {
        return $this->currency->fromSmallestUnit($this->balance);
    }
}