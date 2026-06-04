<?php

namespace App\Models;

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
}