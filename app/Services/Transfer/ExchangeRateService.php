<?php

namespace App\Services\Transfer;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Wallet;

class ExchangeRateService
{
    /**
     * Calculate exchange rate and converted amount
     */
    public function calculate(
        Wallet $sourceWallet,
        Currency $targetCurrency,
        float $amount
    ): array {
        $sourceCurrency = $sourceWallet->currency;
        $isCrossCurrency = $sourceCurrency->id !== $targetCurrency->id;
        
        $rate = 1;
        $convertedAmount = $amount;

        if ($isCrossCurrency) {
            $rate = ExchangeRate::getRate($sourceCurrency, $targetCurrency);
            if (!$rate) {
                throw new \Exception('Exchange rate temporarily unavailable. Please try again.');
            }
            $convertedAmount = round($amount * $rate, $targetCurrency->decimal_places);
        }

        return [
            'rate' => $rate,
            'converted_amount' => $convertedAmount,
            'is_cross_currency' => $isCrossCurrency,
        ];
    }
}