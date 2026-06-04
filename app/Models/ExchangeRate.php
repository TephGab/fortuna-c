<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class ExchangeRate extends Model
{
    protected $fillable = [
        'base_currency_id',
        'target_currency_id',
        'rate',
        'previous_rate',
        'change_percentage',
        'fetched_at',
    ];

    protected $casts = [
        'rate' => 'decimal:6',
        'previous_rate' => 'decimal:6',
        'change_percentage' => 'decimal:4',
        'fetched_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function baseCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'base_currency_id');
    }

    public function targetCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'target_currency_id');
    }

    /**
     * Get exchange rate between two currencies
     */
    public static function getRate(Currency $from, Currency $to): ?float
    {
        if ($from->id === $to->id) {
            return 1.0;
        }
        
        $cacheKey = "exchange_rate_{$from->code}_{$to->code}";
        
        return Cache::remember($cacheKey, 300, function () use ($from, $to) {
            $rate = self::where('base_currency_id', $from->id)
                ->where('target_currency_id', $to->id)
                ->first();
                
            return $rate ? (float) $rate->rate : null;
        });
    }

    /**
     * Format rate for display
     */
    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate, 6);
    }
}