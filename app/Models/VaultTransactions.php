<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\MoneyHelper;

class VaultTransaction extends Model
{
    protected $table = 'vault_transactions';

    protected $fillable = [
        'vault_id',
        'user_id',
        'type',
        'amount',
        'balance_after',
        'currency',
        'description',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'integer',
        'balance_after' => 'integer',
        'metadata' => 'array',
    ];

    // Transaction type constants
    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_WITHDRAWAL = 'withdrawal';
    public const TYPE_INTEREST = 'interest';
    public const TYPE_PENALTY = 'penalty';
    public const TYPE_TRANSFER_IN = 'transfer_in';
    public const TYPE_TRANSFER_OUT = 'transfer_out';
    public const TYPE_MATURITY = 'maturity';

    // ============================================================================
    // RELATIONSHIPS
    // ============================================================================

    public function vault(): BelongsTo
    {
        return $this->belongsTo(Vault::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ============================================================================
    // FORMATTED ATTRIBUTES
    // ============================================================================

    public function getFormattedAmountAttribute(): string
    {
        return MoneyHelper::format($this->amount, $this->currency);
    }

    public function getFormattedAmountWithSignAttribute(): string
    {
        $formatted = $this->formatted_amount;
        
        return match ($this->type) {
            self::TYPE_DEPOSIT, self::TYPE_INTEREST, self::TYPE_TRANSFER_IN => "+{$formatted}",
            self::TYPE_WITHDRAWAL, self::TYPE_PENALTY, self::TYPE_TRANSFER_OUT => "-{$formatted}",
            default => $formatted,
        };
    }

    public function getFormattedBalanceAfterAttribute(): string
    {
        return MoneyHelper::format($this->balance_after, $this->currency);
    }

    // ============================================================================
    // UI HELPERS
    // ============================================================================

    public function getIconAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT => '📥',
            self::TYPE_WITHDRAWAL => '📤',
            self::TYPE_INTEREST => '📈',
            self::TYPE_PENALTY => '⚠️',
            self::TYPE_TRANSFER_IN => '🔄',
            self::TYPE_TRANSFER_OUT => '🔄',
            self::TYPE_MATURITY => '🎉',
            default => '💰',
        };
    }

    public function getColorClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT, self::TYPE_INTEREST, self::TYPE_TRANSFER_IN => 'text-emerald-600',
            self::TYPE_WITHDRAWAL, self::TYPE_PENALTY, self::TYPE_TRANSFER_OUT => 'text-red-600',
            self::TYPE_MATURITY => 'text-purple-600',
            default => 'text-gray-600',
        };
    }

    public function getBgColorClassAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT, self::TYPE_INTEREST, self::TYPE_TRANSFER_IN => 'bg-emerald-50',
            self::TYPE_WITHDRAWAL, self::TYPE_PENALTY, self::TYPE_TRANSFER_OUT => 'bg-red-50',
            self::TYPE_MATURITY => 'bg-purple-50',
            default => 'bg-gray-50',
        };
    }

    public function getTypeNameAttribute(): string
    {
        return match ($this->type) {
            self::TYPE_DEPOSIT => 'Deposit',
            self::TYPE_WITHDRAWAL => 'Withdrawal',
            self::TYPE_INTEREST => 'Interest Earned',
            self::TYPE_PENALTY => 'Early Withdrawal Penalty',
            self::TYPE_TRANSFER_IN => 'Transfer Received',
            self::TYPE_TRANSFER_OUT => 'Transfer Sent',
            self::TYPE_MATURITY => 'Vault Matured',
            default => ucfirst($this->type),
        };
    }

    // ============================================================================
    // HELPER METHODS
    // ============================================================================

    public function isInflow(): bool
    {
        return in_array($this->type, [
            self::TYPE_DEPOSIT,
            self::TYPE_INTEREST,
            self::TYPE_TRANSFER_IN,
        ]);
    }

    public function isOutflow(): bool
    {
        return in_array($this->type, [
            self::TYPE_WITHDRAWAL,
            self::TYPE_PENALTY,
            self::TYPE_TRANSFER_OUT,
        ]);
    }

    public function getNetEffectAttribute(): int
    {
        if ($this->isInflow()) {
            return $this->amount;
        }
        
        if ($this->isOutflow()) {
            return -$this->amount;
        }
        
        return 0;
    }
}