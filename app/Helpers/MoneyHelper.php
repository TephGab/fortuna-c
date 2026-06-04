<?php

namespace App\Helpers;

use App\Models\Currency;

class MoneyHelper
{
    /**
     * Convert amount to smallest unit (cents, cents, satoshis, etc.)
     * 
     * @param float $amount Amount in base unit (e.g., 10.50)
     * @param string $currencyCode Currency code (USD, EUR, etc.)
     * @return int Amount in smallest unit (e.g., 1050)
     */
    public static function toSmallestUnit(float $amount, string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $decimals = $currency ? $currency->decimal_digits : 2;
        return (int) round($amount * pow(10, $decimals));
    }

    /**
     * Convert from smallest unit to base unit
     * 
     * @param int $smallestUnit Amount in smallest unit (e.g., 1050)
     * @param string $currencyCode Currency code (USD, EUR, etc.)
     * @return float Amount in base unit (e.g., 10.50)
     */
    public static function fromSmallestUnit(int $smallestUnit, string $currencyCode): float
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $decimals = $currency ? $currency->decimal_digits : 2;
        return $smallestUnit / pow(10, $decimals);
    }

    /**
     * Format amount for display
     * 
     * @param int $smallestUnit Amount in smallest unit (e.g., 1050)
     * @param string $currencyCode Currency code (USD, EUR, etc.)
     * @return string Formatted amount (e.g., "$10.50")
     */
    public static function format(int $smallestUnit, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        
        if (!$currency) {
            // Fallback for unknown currency
            $amount = $smallestUnit / 100;
            return '$ ' . number_format($amount, 2);
        }
        
        $amount = self::fromSmallestUnit($smallestUnit, $currencyCode);
        
        if ($currency->decimal_digits === 0) {
            return $currency->symbol . ' ' . number_format($amount, 0);
        }
        
        return $currency->symbol . ' ' . number_format($amount, $currency->decimal_digits);
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
     * Get currency decimal digits
     */
    public static function getDecimalDigits(string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();
        return $currency ? $currency->decimal_digits : 2;
    }
}