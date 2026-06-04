<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class Wallet extends Model
{
    protected $table = 'wallets';

    protected $fillable = [
        'user_id', 'currency_id', 'balance', 'locked_balance', 'is_default', 'name'
    ];

    protected $casts = [
        'balance' => 'integer',
        'locked_balance' => 'integer',
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
     * Get the available balance (total balance minus locked balance)
     * This is the real amount the user can spend
     */
    public function getAvailableBalanceAttribute(): int
    {
        return $this->balance - $this->locked_balance;
    }

    /**
     * Get formatted available balance
     */
    public function getFormattedAvailableBalanceAttribute(): string
    {
        return $this->currency->format($this->getAvailableBalanceAttribute());
    }

    /**
     * Get the total balance (including locked)
     */
    public function getTotalBalanceAttribute(): int
    {
        return $this->balance;
    }

    /**
     * Get formatted total balance
     */
    public function getFormattedTotalBalanceAttribute(): string
    {
        return $this->currency->format($this->balance);
    }

    /**
     * Get locked balance formatted
     */
    public function getFormattedLockedBalanceAttribute(): string
    {
        return $this->currency->format($this->locked_balance);
    }

    /**
     * Increase wallet balance (add funds)
     */
    public function increaseBalance(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            $this->increment('balance', $amount);
        });
    }

    /**
     * Decrease wallet balance (remove funds)
     * Throws exception if insufficient available balance
     */
    public function decreaseBalance(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            if ($this->getAvailableBalanceAttribute() < $amount) {
                throw new \Exception('Insufficient available balance');
            }
            
            $this->decrement('balance', $amount);
        });
    }

    /**
     * Lock an amount for a pending transaction (e.g., transfer, payment)
     * This reduces available balance without actually deducting yet
     */
    public function lockBalance(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            $availableBalance = $this->getAvailableBalanceAttribute();
            
            if ($availableBalance < $amount) {
                throw new \Exception('Insufficient available balance to lock');
            }
            
            $this->increment('locked_balance', $amount);
        });
    }

    /**
     * Unlock a locked amount (when transaction is cancelled/expires)
     */
    public function unlockBalance(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            if ($this->locked_balance < $amount) {
                throw new \Exception('Cannot unlock more than locked balance');
            }
            
            $this->decrement('locked_balance', $amount);
        });
    }

    /**
     * Complete a locked transaction (move locked amount to actual deduction)
     * This converts the lock into an actual balance deduction
     */
    public function completeLockedTransaction(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            if ($this->locked_balance < $amount) {
                throw new \Exception('Insufficient locked balance');
            }
            
            // Decrease locked balance
            $this->decrement('locked_balance', $amount);
            
            // Note: The actual balance is NOT decreased here because it was
            // already decreased when the lock was created? Let's be careful.
            // Actually, for outgoing transactions, we want to decrease balance
            // and lock simultaneously. Let me refactor:
        });
    }

    /**
     * Reserve funds for a transaction (decrease balance and increase lock)
     * Used for outgoing transactions like transfers
     */
    public function reserveFunds(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            $availableBalance = $this->getAvailableBalanceAttribute();
            
            if ($availableBalance < $amount) {
                throw new \Exception('Insufficient available balance');
            }
            
            // Decrease actual balance and increase locked balance
            // This way the funds are deducted but locked until transaction completes
            $this->decrement('balance', $amount);
            $this->increment('locked_balance', $amount);
        });
    }

    /**
     * Release reserved funds (when transaction fails/cancels)
     * Returns funds from locked back to available balance
     */
    public function releaseReservedFunds(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            if ($this->locked_balance < $amount) {
                throw new \Exception('Cannot release more than locked balance');
            }
            
            // Increase balance back and decrease locked balance
            $this->increment('balance', $amount);
            $this->decrement('locked_balance', $amount);
        });
    }

    /**
     * Confirm reserved funds (when transaction completes successfully)
     * Just removes the lock - balance is already deducted
     */
    public function confirmReservedFunds(int $amount): void
    {
        DB::transaction(function () use ($amount) {
            $this->refresh();
            
            if ($this->locked_balance < $amount) {
                throw new \Exception('Cannot confirm more than locked balance');
            }
            
            // Just remove the lock - balance already deducted
            $this->decrement('locked_balance', $amount);
        });
    }

    /**
     * Check if user has sufficient available balance
     */
    public function hasSufficientBalance(int $amount): bool
    {
        return $this->getAvailableBalanceAttribute() >= $amount;
    }

    /**
     * Check if user has sufficient total balance (including locked)
     */
    public function hasSufficientTotalBalance(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get the balance as float (for display)
     */
    public function getBalanceFloatAttribute(): float
    {
        return $this->currency->fromSmallestUnit($this->balance);
    }

    /**
     * Get available balance as float
     */
    public function getAvailableBalanceFloatAttribute(): float
    {
        return $this->currency->fromSmallestUnit($this->getAvailableBalanceAttribute());
    }

    /**
     * Get locked balance as float
     */
    public function getLockedBalanceFloatAttribute(): float
    {
        return $this->currency->fromSmallestUnit($this->locked_balance);
    }
}

// namespace App\Models;

// use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\Relations\BelongsTo;

// class Wallet extends Model
// {
//     protected $table = 'wallets';

//     protected $fillable = [
//         'user_id', 'currency_id', 'balance', 'is_default', 'name'
//     ];

//     protected $casts = [
//         'balance' => 'integer',
//         'is_default' => 'boolean',
//     ];

//     /**
//      * Relationships
//      */
//     public function user(): BelongsTo
//     {
//         return $this->belongsTo(User::class);
//     }

//     public function currency(): BelongsTo
//     {
//         return $this->belongsTo(Currency::class);
//     }

//     public function outgoingTransactions()
//     {
//         return $this->hasMany(Transaction::class, 'source_wallet_id');
//     }

//     public function incomingTransactions()
//     {
//         return $this->hasMany(Transaction::class, 'destination_wallet_id');
//     }

//     /**
//      * Balance operations
//      */
//     public function increaseBalance(int $amount): void
//     {
//         $this->increment('balance', $amount);
//     }

//     public function decreaseBalance(int $amount): void
//     {
//         $this->decrement('balance', $amount);
//     }

//     public function hasSufficientBalance(int $amount): bool
//     {
//         return $this->balance >= $amount;
//     }

//     /**
//      * Accessors
//      */
//     public function getFormattedBalanceAttribute(): string
//     {
//         return $this->currency->format($this->balance);
//     }

//     public function getBalanceFloatAttribute(): float
//     {
//         return $this->currency->fromSmallestUnit($this->balance);
//     }
// }