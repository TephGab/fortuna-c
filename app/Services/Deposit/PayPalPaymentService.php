<?php

namespace App\Services\Deposit;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\Transaction;
use App\Helpers\MoneyHelper;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayPalPaymentService
{
    private const PAYPAL_FEE_PERCENTAGE = 3.5;

    /**
     * Create a PayPal order
     */
    public function createOrder(User $user, float $amount, Currency $currency): array
    {
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $amountInSmallestUnit = $currency->toSmallestUnit($amount);

        // Calculate total with fee
        $feeAmount = $amount * (self::PAYPAL_FEE_PERCENTAGE / 100);
        $totalAmount = $amount + $feeAmount;

        // Create pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'destination_wallet_id' => $defaultWallet->id,
            'amount' => $amountInSmallestUnit,
            'status' => 'pending',
            'payment_method' => 'paypal',
            'description' => "PayPal deposit of {$amount} {$currency->code} pending",
            'metadata' => [
                'fee_percentage' => self::PAYPAL_FEE_PERCENTAGE,
                'fee_amount' => $feeAmount,
                'original_amount' => $amount,
                'currency_id' => $currency->id,
            ],
        ]);

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $token = $provider->getAccessToken();
            $provider->setAccessToken($token);

            $order = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('deposits.paypal.success'),
                    "cancel_url" => route('deposits.paypal.cancel'),
                ],
                "purchase_units" => [[
                    "reference_id" => (string) $transaction->id,
                    "amount" => [
                        "currency_code" => "USD",
                        "value" => number_format($totalAmount, 2, '.', ''),
                    ],
                ]],
            ]);

            // Update transaction with PayPal order ID
            $transaction->update([
                'reference' => $order['id'],
                'metadata' => array_merge($transaction->metadata ?? [], ['paypal_order_id' => $order['id']]),
            ]);

            foreach ($order['links'] as $link) {
                if ($link['rel'] === 'approve') {
                    return [
                        'approval_url' => $link['href'],
                        'transaction_id' => $transaction->id,
                    ];
                }
            }

            throw new \Exception('Approval URL not found');

        } catch (\Exception $e) {
            $transaction->update(['status' => 'failed']);
            throw $e;
        }
    }

    /**
     * Capture a PayPal order after approval
     */
    public function captureOrder(string $token, string $payerId): array
    {
        $provider = new PayPalClient;
        $provider->setApiCredentials(config('paypal'));
        $tokenResponse = $provider->getAccessToken();
        $provider->setAccessToken($tokenResponse);

        $result = $provider->capturePaymentOrder($token);

        if ($result['status'] !== 'COMPLETED') {
            throw new \Exception('Payment capture failed');
        }

        return $result;
    }

    /**
     * Process a captured PayPal order
     */
    public function processCapturedOrder(string $orderId, string $payerId): array
    {
        $transaction = Transaction::where('reference', $orderId)
            ->where('status', 'pending')
            ->first();

        if (!$transaction) {
            throw new \Exception('Transaction not found');
        }

        $user = $transaction->user;
        $wallet = Wallet::find($transaction->destination_wallet_id);
        $currency = $wallet->currency;
        $amountInSmallestUnit = $transaction->amount;
        $amountInDollars = MoneyHelper::fromSmallestUnit($amountInSmallestUnit, $currency->code);

        DB::beginTransaction();

        try {
            // Update wallet balance
            $wallet->increaseBalance($amountInSmallestUnit);

            // Mark transaction as completed
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
                'description' => "PayPal deposit of {$amountInDollars} {$currency->code} completed",
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'paypal_order_status' => 'COMPLETED',
                    'paypal_payer_id' => $payerId,
                ]),
            ]);

            DB::commit();

            return [
                'success' => true,
                'transaction' => $transaction,
                'amount' => $amountInDollars,
                'currency' => $currency,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}