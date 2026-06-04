<?php

namespace App\Models;

use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'code', 'name', 'symbol', 'symbol_native', 'decimal_places', 'is_active', 'sort_order'
    ];

    protected $casts = [
        'decimal_places' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Convert from smallest unit (cents) to display amount
     */
    public function fromSmallestUnit(int $smallestUnit): float
    {
        return $smallestUnit / pow(10, $this->decimal_places);
    }

    /**
     * Convert to smallest unit for storage
     */
    public function toSmallestUnit(float $amount): int
    {
        return (int) round($amount * pow(10, $this->decimal_places));
    }

    /**
     * Format amount for display
     */
    public function format(int $smallestUnit): string
    {
        $amount = $this->fromSmallestUnit($smallestUnit);
        
        if ($this->decimal_places === 0) {
            return $this->symbol . ' ' . number_format($amount, 0);
        }
        
        return $this->symbol . ' ' . number_format($amount, $this->decimal_places);
    }

    /**
     * Relationships
     */
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    public function asBaseExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_id');
    }

    public function asTargetExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'target_currency_id');
    }

     /**
     * Get exchange rates where this is the from currency
     */
    public function fromExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    /**
     * Get exchange rates where this is the to currency
     */
    public function toExchangeRates()
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }
}