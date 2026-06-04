<?php

namespace App\Helpers;

use App\Models\Currency;
use Illuminate\Support\Facades\Log;

class MoneyHelper
{
    /**
     * Convert from smallest unit to base unit
     */
    public static function fromSmallestUnit(int $smallestUnit, string $currencyCode): float
    {
        $currency = Currency::where('code', $currencyCode)->first();
        // FIXED: Use 'decimal_places' instead of 'decimal_digits'
        $decimals = $currency ? $currency->decimal_places : 2;
        return $smallestUnit / pow(10, $decimals);
    }

    /**
     * Convert amount to smallest unit
     */
    public static function toSmallestUnit(float $amount, string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();
        // FIXED: Use 'decimal_places' instead of 'decimal_digits'
        $decimals = $currency ? $currency->decimal_places : 2;
        return (int) round($amount * pow(10, $decimals));
    }

    /**
     * Format amount for display
     */
    public static function format(int $smallestUnit, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        
        if (!$currency) {
            $amount = $smallestUnit / 100;
            return '$ ' . number_format($amount, 2);
        }
        
        $amount = self::fromSmallestUnit($smallestUnit, $currencyCode);
        // FIXED: Use 'decimal_places' instead of 'decimal_digits'
        $decimals = $currency->decimal_places;
        
        if ($decimals === 0) {
            return $currency->symbol . ' ' . number_format($amount, 0);
        }
        
        return $currency->symbol . ' ' . number_format($amount, $decimals);
    }
    
    /**
     * Get currency symbol
     */
    public static function getSymbol(string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        return $currency ? $currency->symbol : '$';
    }
    
    /**
     * Get currency decimal places
     */
    public static function getDecimalPlaces(string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();
        return $currency ? $currency->decimal_places : 2;
    }
}