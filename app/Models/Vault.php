<?php

namespace App\Models;

use App\Helpers\MoneyHelper;
use App\Models\VaultTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vault extends Model
{
    protected $table = 'vaults';

    protected $fillable = [
        'user_id',
        'wallet_id',
        'name',
        'icon',
        'type',
        'status',
        'balance',
        'withdrawable_balance',
        'original_balance',
        'interest_earned',
        'interest_rate',
        'locked_until',
        'matures_at',
        'description',
        'settings',
    ];

    protected $casts = [
        'balance' => 'integer',
        'withdrawable_balance' => 'integer',
        'original_balance' => 'integer',
        'interest_earned' => 'integer',
        'interest_rate' => 'decimal:2',
        'locked_until' => 'datetime',
        'matures_at' => 'datetime',
        'settings' => 'array',
    ];

    // ============================================================================
    // VAULT TYPE DEFINITIONS
    // ============================================================================

    public const TYPES = [
        'flexible' => [
            'name' => 'Flexible Vault',
            'lock_days' => 0,
            'interest_rate' => 0,
            'penalty' => 0,
            'icon' => '🔄',
            'color' => 'gray',
            'description' => 'No lock period, instant access',
        ],
        'locked_30' => [
            'name' => '30-Day Vault',
            'lock_days' => 30,
            'interest_rate' => 2.0,
            'penalty' => 0.5,
            'icon' => '📅',
            'color' => 'blue',
            'description' => 'Lock funds for 30 days to earn 2% APY',
        ],
        'locked_90' => [
            'name' => '90-Day Vault',
            'lock_days' => 90,
            'interest_rate' => 3.5,
            'penalty' => 1.0,
            'icon' => '🏦',
            'color' => 'green',
            'description' => 'Lock funds for 90 days to earn 3.5% APY',
        ],
        'locked_180' => [
            'name' => '180-Day Vault',
            'lock_days' => 180,
            'interest_rate' => 5.0,
            'penalty' => 1.5,
            'icon' => '⭐',
            'color' => 'purple',
            'description' => 'Lock funds for 180 days to earn 5% APY',
        ],
        'locked_365' => [
            'name' => '1-Year Vault',
            'lock_days' => 365,
            'interest_rate' => 7.0,
            'penalty' => 2.0,
            'icon' => '💎',
            'color' => 'amber',
            'description' => 'Lock funds for 1 year to earn 7% APY',
        ],
    ];

    // Status constants
    public const STATUS_ACTIVE = 'active';
    public const STATUS_LOCKED = 'locked';
    public const STATUS_MATURED = 'matured';
    public const STATUS_CLOSED = 'closed';

    // ============================================================================
    // RELATIONSHIPS
    // ============================================================================
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function transactions()
    {
        return $this->hasMany(VaultTransaction::class)->orderBy('created_at', 'desc');
    }

    // ============================================================================
    // SCOPES
    // ============================================================================
    
    public function scopeActive($query)
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function scopeReadyForMaturity($query)
    {
        return $query->where('type', '!=', 'flexible')
                     ->where('status', self::STATUS_LOCKED)
                     ->where('matures_at', '<=', now());
    }

    // ============================================================================
    // FORMATTED ATTRIBUTES (For Vue - No Logic in Frontend!)
    // ============================================================================
    
    /**
     * Get currency code through wallet relationship
     */
    public function getCurrencyCodeAttribute(): string
    {
        return $this->wallet->currency->code;
    }

    /**
     * Get formatted balance (e.g., "$1,234.56")
     */
    public function getFormattedBalanceAttribute(): string
    {
        return MoneyHelper::format($this->balance, $this->currency_code);
    }

    /**
     * Get balance as float (e.g., 1234.56)
     */
    public function getBalanceFloatAttribute(): float
    {
        return MoneyHelper::fromSmallestUnit($this->balance, $this->currency_code);
    }

    /**
     * Get formatted interest earned
     */
    public function getFormattedInterestEarnedAttribute(): string
    {
        return MoneyHelper::format($this->interest_earned, $this->currency_code);
    }

    /**
     * Get total value (balance + interest)
     */
    public function getTotalValueAttribute(): int
    {
        return $this->balance + $this->interest_earned;
    }

    /**
     * Get formatted total value
     */
    public function getFormattedTotalValueAttribute(): string
    {
        return MoneyHelper::format($this->total_value, $this->currency_code);
    }

    // ============================================================================
    // UI ATTRIBUTES (All logic here, not in Vue)
    // ============================================================================
    
    /**
     * Get vault type configuration
     */
    public function getTypeConfigAttribute(): array
    {
        return self::TYPES[$this->type] ?? self::TYPES['flexible'];
    }
    
    /**
     * Get days remaining until unlock
     */
    public function getDaysRemainingAttribute(): ?int
    {
        return $this->getDaysRemaining();
    }
    
    /**
     * Get human-readable days remaining text
     */
    public function getDaysRemainingTextAttribute(): ?string
    {
        $days = $this->getDaysRemaining();
        
        if ($days === null) return null;
        if ($days === 0) return __('Unlocks today!');
        if ($days === 1) return __('1 day remaining');
        return __(':days days remaining', ['days' => $days]);
    }
    
    /**
     * Get progress percentage (0-100)
     */
    public function getProgressPercentageAttribute(): ?int
    {
        return $this->getProgressPercentage();
    }
    
    /**
     * Get CSS class for vault type badge
     */
    public function getTypeColorClassAttribute(): string
    {
        return match($this->type) {
            'flexible' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
            'locked_30' => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            'locked_90' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'locked_180' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            'locked_365' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
            default => 'bg-gray-100 text-gray-700',
        };
    }

    /**
     * Get CSS class for progress bar
     */
    public function getProgressBarColorClassAttribute(): string
    {
        return match($this->type) {
            'locked_30' => 'bg-blue-500',
            'locked_90' => 'bg-green-500',
            'locked_180' => 'bg-purple-500',
            'locked_365' => 'bg-amber-500',
            default => 'bg-gray-500',
        };
    }

    /**
     * Get status badge data (text, color, icon)
     */
    public function getStatusBadgeAttribute(): array
    {
        if ($this->status === 'locked') {
            return [
                'text' => __('Locked'),
                'color' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
                'icon' => 'Lock'
            ];
        }
        
        if ($this->status === 'matured') {
            return [
                'text' => __('Matured'),
                'color' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
                'icon' => 'Sparkles'
            ];
        }
        
        if ($this->type === 'flexible') {
            return [
                'text' => __('Flexible'),
                'color' => 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
                'icon' => 'Unlock'
            ];
        }
        
        return [
            'text' => __('Active'),
            'color' => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            'icon' => 'TrendingUp'
        ];
    }

    // ============================================================================
    // BUSINESS LOGIC METHODS
    // ============================================================================
    
    /**
     * Check if vault is locked
     */
    public function isLocked(): bool
    {
        if ($this->type === 'flexible') return false;
        return $this->locked_until && $this->locked_until->isFuture();
    }

    /**
     * Check if vault has matured
     */
    public function isMatured(): bool
    {
        if ($this->type === 'flexible') return false;
        return $this->matures_at && $this->matures_at->isPast();
    }

    /**
     * Get days remaining until unlock
     */
    public function getDaysRemaining(): ?int
    {
        if (!$this->locked_until || $this->isMatured()) return null;
        return now()->diffInDays($this->locked_until, false);
    }

    /**
     * Calculate early withdrawal penalty
     */
    public function calculatePenalty(int $withdrawAmount): int
    {
        if ($this->type === 'flexible' || $this->isMatured()) {
            return 0;
        }
        
        $typeConfig = self::TYPES[$this->type];
        $penaltyPercentage = $typeConfig['penalty'];
        
        return (int) round($withdrawAmount * ($penaltyPercentage / 100));
    }

    /**
     * Check if early withdrawal is allowed
     */
    public function canEarlyWithdraw(): bool
    {
        if ($this->type === 'flexible') return true;
        if ($this->isMatured()) return true;
        return false;
    }

    /**
     * Process vault maturity
     */
    public function processMaturity(): bool
    {
        if (!$this->isMatured() || $this->status !== self::STATUS_LOCKED) {
            return false;
        }
        
        $interestFloat = ($this->original_balance / 100) * $this->interest_rate;
        $interestAmount = (int) round($interestFloat);
        
        $this->interest_earned += $interestAmount;
        $this->status = self::STATUS_MATURED;
        $this->save();
        
        return true;
    }

    /**
     * Get vault type configuration
     */
    public function getTypeConfig(): array
    {
        return self::TYPES[$this->type];
    }

    /**
     * Get progress percentage (0-100)
     */
    public function getProgressPercentage(): ?int
    {
        if ($this->type === 'flexible' || !$this->locked_until) {
            return null;
        }
        
        $totalDays = self::TYPES[$this->type]['lock_days'];
        $elapsedDays = $totalDays - $this->getDaysRemaining();
        
        if ($elapsedDays < 0) return 0;
        if ($elapsedDays > $totalDays) return 100;
        
        return (int) round(($elapsedDays / $totalDays) * 100);
    }
}