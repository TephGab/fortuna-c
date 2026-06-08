<?php

namespace App\Services\Deposit;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Currency;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    private const PROCESSING_LOCK_KEY = 'deposit_processing_';
    private const LOCK_DURATION = 30;

    /**
     * Process a successful deposit from webhook
     */
    public function processSuccessfulDeposit(int $userId, float $amountInDollars, string $sessionId): void
    {
        // Check if already processed
        if (Transaction::where('reference', $sessionId)->where('status', 'completed')->exists()) {
            Log::info('Webhook deposit already processed', [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
            return;
        }

        $lockKey = self::PROCESSING_LOCK_KEY . $userId;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            Log::warning('Could not acquire lock for webhook deposit', [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
            return;
        }

        $user = User::find($userId);

        if (!$user) {
            $lock->release();
            Log::error('User not found for webhook deposit', [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
            return;
        }

        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
        $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

        DB::beginTransaction();

        try {
            $wallet = Wallet::where('user_id', $user->id)
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'currency_id' => $currency->id,
                    'balance' => 0,
                    'locked_balance' => 0,
                    'is_default' => true,
                    'name' => 'Main Account',
                ]);
            }

            $wallet->increaseBalance($amountInSmallestUnit);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'destination_wallet_id' => $wallet->id,
                'amount' => $amountInSmallestUnit,
                'status' => 'completed',
                'reference' => $sessionId,
                'payment_method' => 'stripe',
                'description' => "Deposit of {$amountInDollars} {$currency->code} via Webhook",
                'completed_at' => now(),
            ]);

            DB::commit();
            $lock->release();

            Log::info('Webhook deposit processed successfully', [
                'user_id' => $user->id,
                'amount' => $amountInDollars,
                'session_id' => $sessionId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $lock->release();

            Log::error('Webhook deposit failed', [
                'user_id' => $user->id,
                'amount' => $amountInDollars,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}