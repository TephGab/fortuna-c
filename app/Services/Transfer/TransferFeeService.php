<?php

namespace App\Services\Transfer;

class TransferFeeService
{
    private const TRANSFER_FEE_PERCENTAGE = 0.5;
    private const MIN_FEE = 0.50;

    /**
     * Calculate transfer fee
     */
    public function calculate(float $amount): array
    {
        $feeAmount = max(self::MIN_FEE, $amount * (self::TRANSFER_FEE_PERCENTAGE / 100));
        $totalAmount = $amount + $feeAmount;

        return [
            'fee' => round($feeAmount, 2),
            'total' => round($totalAmount, 2),
            'percentage' => self::TRANSFER_FEE_PERCENTAGE,
            'min_fee' => self::MIN_FEE,
        ];
    }
}