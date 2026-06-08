<?php

namespace App\Services\Transfer;

use App\Models\User;
use App\Models\Wallet;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\Cache;

class TransferQuoteService
{
    private const QUOTE_EXPIRATION = 60;

    /**
     * Create a transfer quote
     */
    public function create(
        User $recipient,
        Wallet $sourceWallet,
        Wallet $recipientWallet,
        float $amount,
        float $convertedAmount,
        float $rate,
        array $feeInfo
    ): string {
        $sourceCurrency = $sourceWallet->currency;
        $targetCurrency = $recipientWallet->currency;
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($amount, $sourceCurrency->code);

        $quoteId = uniqid('quote_', true);

        Cache::put("transfer_quote_{$quoteId}", [
            'recipient_id' => $recipient->id,
            'recipient_name' => $recipient->name,
            'recipient_email' => $recipient->email,
            'recipient_wallet_id' => $recipientWallet->id,
            'source_wallet_id' => $sourceWallet->id,
            'amount' => $amount,
            'amount_in_smallest_unit' => $amountInSmallestUnit,
            'converted_amount' => $convertedAmount,
            'converted_amount_in_smallest_unit' => MoneyHelper::toSmallestUnit($convertedAmount, $targetCurrency->code),
            'rate' => $rate,
            'fee' => $feeInfo['fee'],
            'fee_in_smallest_unit' => MoneyHelper::toSmallestUnit($feeInfo['fee'], $sourceCurrency->code),
            'total' => $feeInfo['total'],
            'total_in_smallest_unit' => MoneyHelper::toSmallestUnit($feeInfo['total'], $sourceCurrency->code),
            'is_cross_currency' => $sourceCurrency->id !== $targetCurrency->id,
            'source_currency' => $sourceCurrency->code,
            'target_currency' => $targetCurrency->code,
        ], self::QUOTE_EXPIRATION);

        return $quoteId;
    }

    /**
     * Get a transfer quote from cache
     */
    public function get(string $quoteId): ?array
    {
        return Cache::get("transfer_quote_{$quoteId}");
    }

    /**
     * Delete a transfer quote
     */
    public function delete(string $quoteId): void
    {
        Cache::forget("transfer_quote_{$quoteId}");
    }
}