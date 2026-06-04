<?php

namespace App\Helpers;

use App\Models\Currency;

class MoneyHelper
{
    public static function toSmallestUnit(float $amount, string $currencyCode): int
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $decimals = $currency ? $currency->decimal_digits : 2;
        return (int) round($amount * pow(10, $decimals));
    }

    public static function fromSmallestUnit(int $smallestUnit, string $currencyCode): float
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $decimals = $currency ? $currency->decimal_digits : 2;
        return $smallestUnit / pow(10, $decimals);
    }

    public static function format(int $smallestUnit, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $amount = self::fromSmallestUnit($smallestUnit, $currencyCode);
        return ($currency?->symbol ?? '$') . ' ' . number_format($amount, $currency?->decimal_digits ?? 2);
    }
}