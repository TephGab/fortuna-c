<?php

namespace App\Services\Deposit;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Models\Transaction;
use App\Helpers\MoneyHelper;
use Laravel\Cashier\Cashier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class StripePaymentService
{
    private const STRIPE_FEE_PERCENTAGE = 2.9;

    /**
     * Create a Stripe PaymentIntent
     */
    public function createPaymentIntent(User $user, float $amountInDollars, Currency $currency): array
    {
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

        // Calculate total with fee
        $feeAmount = $amountInDollars * (self::STRIPE_FEE_PERCENTAGE / 100);
        $totalAmount = $amountInDollars + $feeAmount;
        $totalInCents = (int) round($totalAmount * 100);

        // Create Stripe PaymentIntent
        $paymentIntent = $user->pay($totalInCents);

        // Create pending transaction
        $transaction = Transaction::create([
            'user_id' => $user->id,
            'type' => 'deposit',
            'destination_wallet_id' => $defaultWallet->id,
            'amount' => $amountInSmallestUnit,
            'status' => 'pending',
            'reference' => $paymentIntent->id,
            'payment_method' => 'stripe',
            'description' => "Deposit of {$amountInDollars} {$currency->code} pending confirmation",
            'metadata' => [
                'fee_percentage' => self::STRIPE_FEE_PERCENTAGE,
                'fee_amount' => $feeAmount,
                'original_amount' => $amountInDollars,
                'currency_id' => $currency->id,
            ],
        ]);

        Log::info('PaymentIntent created', [
            'user_id' => $user->id,
            'amount' => $amountInDollars,
            'payment_intent_id' => $paymentIntent->id,
            'transaction_id' => $transaction->id,
        ]);

        return [
            'clientSecret' => $paymentIntent->client_secret,
            'paymentIntentId' => $paymentIntent->id,
            'amount' => $amountInDollars,
            'currency' => $currency->code,
        ];
    }

    /**
     * Confirm a Stripe payment and update wallet
     */
    public function confirmPayment(User $user, string $paymentIntentId, float $amountInDollars, Currency $currency): array
    {
        $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

        // Verify payment with Stripe
        $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);

        if ($paymentIntent->status !== 'succeeded') {
            Transaction::where('reference', $paymentIntentId)
                ->where('status', 'pending')
                ->update(['status' => 'failed']);
            
            throw new \Exception('Payment not successful');
        }

        DB::beginTransaction();

        try {
            $pendingTransaction = Transaction::where('reference', $paymentIntentId)
                ->where('status', 'pending')
                ->first();

            $targetWalletId = $pendingTransaction?->destination_wallet_id;

            if (!$targetWalletId) {
                $defaultWallet = $user->wallets()->where('is_default', true)->first();
                if (!$defaultWallet) {
                    $defaultWallet = Wallet::create([
                        'user_id' => $user->id,
                        'currency_id' => $currency->id,
                        'balance' => 0,
                        'locked_balance' => 0,
                        'is_default' => true,
                        'name' => 'Main Account',
                    ]);
                }
                $targetWalletId = $defaultWallet->id;
            }

            $wallet = Wallet::where('id', $targetWalletId)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'currency_id' => $currency->id,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'is_default' => false,
                    'name' => "{$currency->code} Account",
                ]);
            }

            $wallet->increaseBalance($amountInSmallestUnit);

            if ($pendingTransaction) {
                $pendingTransaction->update([
                    'status' => 'completed',
                    'amount' => $amountInSmallestUnit,
                    'completed_at' => now(),
                    'description' => "Deposit of {$amountInDollars} {$currency->code} completed",
                    'metadata' => [
                        'wallet_id' => $wallet->id,
                        'new_balance' => $wallet->balance,
                        'available_balance' => $wallet->available_balance,
                    ],
                ]);
            }

            DB::commit();

            Log::info('Deposit completed', [
                'user_id' => $user->id,
                'amount' => $amountInDollars,
                'wallet_id' => $wallet->id,
                'new_balance' => $wallet->balance,
            ]);

            return ['success' => true];

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Rollback a Stripe payment (refund)
     */
    public function rollbackPayment(User $user, string $paymentIntentId): array
    {
        $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);

        if ($paymentIntent->status === 'succeeded') {
            $existingRefunds = Cashier::stripe()->refunds->all(['payment_intent' => $paymentIntentId]);

            if (count($existingRefunds->data) > 0) {
                return ['success' => true, 'already_refunded' => true];
            }

            $refund = Cashier::stripe()->refunds->create([
                'payment_intent' => $paymentIntentId,
                'reason' => 'requested_by_customer',
            ]);

            Transaction::where('reference', $paymentIntentId)->update(['status' => 'refunded']);

            Log::info('Payment refunded', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refund->id,
            ]);

            return ['success' => true, 'refunded' => true];
        }

        return ['success' => true, 'refunded' => false];
    }
}