<?php

namespace App\Services\Transfer;

use App\Models\Wallet;
use App\Helpers\MoneyHelper;

class BalanceCheckService
{
    /**
     * Check if source wallet has sufficient balance
     */
    public function check(Wallet $sourceWallet, float $amount): void
    {
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($amount, $sourceWallet->currency->code);
        
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            throw new \Exception('Insufficient balance in selected wallet');
        }
    }

    /**
     * Check if source wallet has sufficient balance for a quote
     */
    public function checkForQuote(Wallet $sourceWallet, int $amountInSmallestUnit): void
    {
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            throw new \Exception('Insufficient balance. Please refresh and try again.');
        }
    }
}