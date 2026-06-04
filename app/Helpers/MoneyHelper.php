<?php

namespace App\Helpers;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

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
        $decimals = self::getDecimalPlaces($currencyCode);
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
        $decimals = self::getDecimalPlaces($currencyCode);
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
        $currencyData = self::getCurrencyData($currencyCode);
        
        if (!$currencyData) {
            $amount = $smallestUnit / 100;
            return '$ ' . number_format($amount, 2);
        }
        
        $amount = self::fromSmallestUnit($smallestUnit, $currencyCode);
        $decimals = $currencyData['decimal_places'];
        
        if ($decimals === 0) {
            return $currencyData['symbol'] . ' ' . number_format($amount, 0);
        }
        
        return $currencyData['symbol'] . ' ' . number_format($amount, $decimals);
    }
    
    /**
     * Get currency symbol (cached)
     */
    public static function getSymbol(string $currencyCode): string
    {
        $cacheKey = "currency_symbol_{$currencyCode}";
        
        return Cache::remember($cacheKey, 86400, function () use ($currencyCode) {
            $currency = Currency::where('code', $currencyCode)->first();
            return $currency ? $currency->symbol : '$';
        });
    }
    
    /**
     * Get currency decimal places (cached)
     */
    public static function getDecimalPlaces(string $currencyCode): int
    {
        $cacheKey = "currency_decimal_places_{$currencyCode}";
        
        return Cache::remember($cacheKey, 86400, function () use ($currencyCode) {
            $currency = Currency::where('code', $currencyCode)->first();
            return $currency ? $currency->decimal_places : 2;
        });
    }
    
    /**
     * Get currency data as array (cached as array, not model)
     * This avoids unserialization issues with Eloquent models
     */
    private static function getCurrencyData(string $currencyCode): ?array
    {
        $cacheKey = "currency_data_{$currencyCode}";
        
        return Cache::remember($cacheKey, 86400, function () use ($currencyCode) {
            $currency = Currency::where('code', $currencyCode)->first();
            
            if (!$currency) {
                return null;
            }
            
            // Return array instead of model to avoid unserialization issues
            return [
                'id' => $currency->id,
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'symbol_native' => $currency->symbol_native,
                'decimal_places' => $currency->decimal_places,
                'is_active' => $currency->is_active,
                'sort_order' => $currency->sort_order,
            ];
        });
    }
    
    /**
     * Clear all currency caches
     */
    public static function clearCache(): void
    {
        $currencies = Currency::all();
        foreach ($currencies as $currency) {
            Cache::forget("currency_data_{$currency->code}");
            Cache::forget("currency_symbol_{$currency->code}");
            Cache::forget("currency_decimal_places_{$currency->code}");
        }
    }
}