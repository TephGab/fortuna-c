<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Cashier\Cashier;
use Stripe\PaymentIntent;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use App\Helpers\MoneyHelper;

class DepositController extends Controller
{
    // ==================== STRIPE CONFIGURATION ====================

    private const PROCESSING_LOCK_KEY = 'deposit_processing_';
    private const LOCK_DURATION = 30;
    private const RATE_LIMIT_KEY = 'deposit_rate_limit_';
    private const RATE_LIMIT_COOLDOWN = 20;
    private const STRIPE_FEE_PERCENTAGE = 2.9;

    // ==================== PAYPAL CONFIGURATION ====================
    private const PAYPAL_FEE_PERCENTAGE = 3.5;

    // ==================== SHARED PAGE ROUTES ====================

    public function index()
    {
        return Inertia::render('deposits/Index');
    }

    public function card()
    {
        return Inertia::render('deposits/Card');
    }

    public function authorizedAgent()
    {
        return Inertia::render('deposits/AuthorizedAgent');
    }

    public function paypal()
    {
        return Inertia::render('deposits/PayPal');
    }

    public function success(Request $request)
    {
        $amount = $request->get('amount', 0);
        $sessionId = $request->get('session_id');

        if ($amount <= 0) {
            return redirect()->route('dashboard')
                ->with('error', 'Invalid deposit information');
        }

        $user = $request->user();
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
        $formattedAmount = $currency->format($amount);

        return Inertia::render('deposits/Success', [
            'amount' => $amount,
            'formatted_amount' => $formattedAmount,
            'session_id' => $sessionId,
        ]);
    }

    public function cancel(Request $request)
    {
        return redirect()->route('deposits.card')
            ->with('error', 'Deposit was cancelled. Please try again.');
    }

    // ==================== STRIPE METHODS ====================

    public function createPaymentIntent(Request $request)
    {
        $user = $request->user();

        // Rate limiting
        $rateLimitKey = self::RATE_LIMIT_KEY . $user->id;

        if (Cache::has($rateLimitKey)) {
            return response()->json([
                'message' => 'Please wait a moment before trying again.',
                'retry_after' => self::RATE_LIMIT_COOLDOWN,
            ], 429);
        }

        Cache::put($rateLimitKey, true, self::RATE_LIMIT_COOLDOWN);

        // Validation
        $request->validate([
            'amount' => 'required|numeric|min:10|max:5000',
            'currency' => 'sometimes|string|size:3',
        ]);

        $amountInDollars = $request->amount;

        // Get user's default wallet
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $targetCurrency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
        $amountInSmallestUnit = $targetCurrency->toSmallestUnit($amountInDollars);

        // Check for pending deposit
        $pendingDeposit = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if ($pendingDeposit) {
            return response()->json([
                'message' => 'You already have a pending deposit. Please wait for it to complete.',
            ], 409);
        }

        // Calculate total with fee
        $feeAmount = $amountInDollars * (self::STRIPE_FEE_PERCENTAGE / 100);
        $totalAmount = $amountInDollars + $feeAmount;
        $totalInCents = (int) round($totalAmount * 100);

        // Acquire lock
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            return response()->json([
                'message' => 'A transaction is already in progress. Please try again.',
            ], 409);
        }

        try {
            // Create Stripe PaymentIntent
            $paymentIntent = $user->pay($totalInCents);

            // Create pending transaction
            Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'destination_wallet_id' => $defaultWallet->id,
                'amount' => $amountInSmallestUnit,
                'status' => 'pending',
                'reference' => $paymentIntent->id,
                'payment_method' => 'stripe',
                'description' => "Deposit of {$amountInDollars} {$targetCurrency->code} pending confirmation",
                'metadata' => [
                    'fee_percentage' => self::STRIPE_FEE_PERCENTAGE,
                    'fee_amount' => $feeAmount,
                    'original_amount' => $amountInDollars,
                    'currency_id' => $targetCurrency->id,
                ],
            ]);

            Log::info('PaymentIntent created', [
                'user_id' => $user->id,
                'amount' => $amountInDollars,
                'payment_intent_id' => $paymentIntent->id,
            ]);

            $lock->release();

            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
                'amount' => $amountInDollars,
                'currency' => $targetCurrency->code,
            ]);
        } catch (\Exception $e) {
            $lock->release();

            Log::error('Failed to create PaymentIntent', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create payment. Please try again.',
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        // Validation
        $request->validate([
            'payment_intent_id' => 'required|string',
            'amount' => 'required|numeric|min:10|max:5000',
            'currency' => 'sometimes|string|size:3',
        ]);

        $user = $request->user();
        $paymentIntentId = $request->payment_intent_id;
        $amountInDollars = $request->amount;
        $currencyCode = $request->currency ?? 'USD';

        // Get currency
        $currency = Currency::where('code', $currencyCode)->first() ?? Currency::where('code', 'USD')->first();
        $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

        // Idempotency check
        $existingTransaction = Transaction::where('reference', $paymentIntentId)
            ->whereIn('status', ['completed', 'refunded'])
            ->first();

        if ($existingTransaction) {
            return response()->json([
                'success' => true,
                'already_processed' => true,
                'message' => 'This payment has already been processed.',
            ]);
        }

        // Acquire lock
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            return response()->json([
                'error' => 'A transaction is already in progress. Please try again.',
            ], 409);
        }

        try {
            // Verify payment with Stripe
            $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status !== 'succeeded') {
                $lock->release();

                Transaction::where('reference', $paymentIntentId)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);

                return response()->json(['error' => 'Payment not successful'], 400);
            }

            // Double-check for race condition
            if (Transaction::where('reference', $paymentIntentId)->where('status', 'completed')->exists()) {
                $lock->release();
                return response()->json(['success' => true, 'already_processed' => true]);
            }

            DB::beginTransaction();

            try {
                // Get pending transaction
                $pendingTransaction = Transaction::where('reference', $paymentIntentId)
                    ->where('status', 'pending')
                    ->first();

                // Get target wallet
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

                // Lock and update wallet
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

                // Add funds to wallet
                $wallet->increaseBalance($amountInSmallestUnit);

                // Update transaction
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
                $lock->release();

                Log::info('Deposit completed', [
                    'user_id' => $user->id,
                    'amount' => $amountInDollars,
                    'wallet_id' => $wallet->id,
                    'new_balance' => $wallet->balance,
                ]);

                return response()->json(['success' => true]);
            } catch (\Exception $e) {
                DB::rollBack();
                $lock->release();

                Log::error('Failed to update wallet', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'error' => 'Payment confirmed but failed to update wallet. Please contact support.',
                ], 500);
            }
        } catch (\Exception $e) {
            $lock->release();

            Log::error('Failed to confirm payment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to verify payment. Please contact support.',
            ], 500);
        }
    }

    public function rollbackPayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $user = $request->user();
        $paymentIntentId = $request->payment_intent_id;

        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            return response()->json(['error' => 'A transaction is already in progress.'], 409);
        }

        try {
            $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);

            if ($paymentIntent->status === 'succeeded') {
                $existingRefunds = Cashier::stripe()->refunds->all(['payment_intent' => $paymentIntentId]);

                if (count($existingRefunds->data) > 0) {
                    $lock->release();
                    return response()->json(['success' => true, 'already_refunded' => true]);
                }

                $refund = Cashier::stripe()->refunds->create([
                    'payment_intent' => $paymentIntentId,
                    'reason' => 'requested_by_customer',
                ]);

                Transaction::where('reference', $paymentIntentId)->update(['status' => 'refunded']);

                $lock->release();

                return response()->json(['success' => true, 'refunded' => true]);
            }

            $lock->release();
            return response()->json(['success' => true, 'refunded' => false]);
        } catch (\Exception $e) {
            $lock->release();

            Log::error('Failed to rollback payment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to process rollback. Please contact support.',
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;

            $userId = $session['metadata']['user_id'] ?? null;
            $amountInDollars = $session['metadata']['amount'] ?? 0;
            $sessionId = $session['id'];

            if ($userId && $amountInDollars > 0) {
                $this->processSuccessfulDeposit($userId, $amountInDollars, $sessionId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function processSuccessfulDeposit(int $userId, float $amountInDollars, string $sessionId): void
    {
        // Check if already processed
        if (Transaction::where('reference', $sessionId)->where('status', 'completed')->exists()) {
            return;
        }

        $lockKey = self::PROCESSING_LOCK_KEY . $userId;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $lock->release();
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

            Log::info('Webhook deposit processed', [
                'user_id' => $user->id,
                'amount' => $amountInDollars,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $lock->release();

            Log::error('Webhook deposit failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== PAYPAL METHODS ====================

    public function createPayPalOrder(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:10|max:5000',
        ]);

        $user = $request->user();
        $amount = $request->amount;

        // Get user's default wallet and currency
        $defaultWallet = $user->wallets()->where('is_default', true)->first();
        $targetCurrency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
        $amountInSmallestUnit = $targetCurrency->toSmallestUnit($amount);

        // Rate limiting
        $rateLimitKey = self::RATE_LIMIT_KEY . $user->id;
        if (Cache::has($rateLimitKey)) {
            return response()->json([
                'message' => 'Please wait a moment before trying again.',
                'retry_after' => self::RATE_LIMIT_COOLDOWN,
            ], 429);
        }
        Cache::put($rateLimitKey, true, self::RATE_LIMIT_COOLDOWN);

        // Check for pending deposit
        $pendingDeposit = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if ($pendingDeposit) {
            return response()->json([
                'message' => 'You already have a pending deposit. Please wait for it to complete.',
            ], 409);
        }

        // Calculate total with fee
        $feeAmount = $amount * (self::PAYPAL_FEE_PERCENTAGE / 100);
        $totalAmount = $amount + $feeAmount;

        // Create pending transaction
        $transaction = Transaction::create([
            'user_id'        => $user->id,
            'type'           => 'deposit',
            'destination_wallet_id' => $defaultWallet->id,
            'amount'         => $amountInSmallestUnit,
            'status'         => 'pending',
            'payment_method' => 'paypal',
            'description'    => "PayPal deposit of {$amount} {$targetCurrency->code} pending",
            'metadata'       => [
                'fee_percentage'   => self::PAYPAL_FEE_PERCENTAGE,
                'fee_amount'       => $feeAmount,
                'original_amount'  => $amount,
                'currency_id'      => $targetCurrency->id,
            ],
        ]);

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $token = $provider->getAccessToken();
            $provider->setAccessToken($token);

            // Simplified order – no items, just the total amount
            // $order = $provider->createOrder([
            //     "intent" => "CAPTURE",
            //     "application_context" => [
            //         "return_url" => route('deposits.paypal.success'),
            //         "cancel_url" => route('deposits.paypal.cancel'),
            //         "brand_name" => config('app.name'),
            //         "landing_page" => "BILLING",
            //         "user_action" => "PAY_NOW",
            //     ],
            //     "purchase_units" => [
            //         [
            //             "reference_id" => (string) $transaction->id,
            //             "description" => "Wallet Deposit",
            //             "amount" => [
            //                 "currency_code" => $targetCurrency->code,
            //                 "value" => number_format($totalAmount, 2, '.', ''),
            //             ],
            //         ],
            //     ],
            // ]);

            $order = $provider->createOrder([
    "intent" => "CAPTURE",
    "application_context" => [
        "return_url" => route('deposits.paypal.success'),
        "cancel_url" => route('deposits.paypal.cancel'),
    ],
    "purchase_units" => [[
        "amount" => [
            "currency_code" => "USD",
            "value" => "100.00",
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
                    return response()->json(['approval_url' => $link['href']]);
                }
            }

            return response()->json(['error' => 'Approval URL not found'], 500);
        } catch (\Exception $e) {
            Log::error('PayPal order creation failed', [
                'user_id' => $user->id,
                'amount'  => $amount,
                'error'   => $e->getMessage(),
            ]);
            $transaction->update(['status' => 'failed']);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // /**
    //  * Create a PayPal order and return the approval URL.
    //  */
    // public function createPayPalOrder(Request $request)
    // {
    //     $request->validate([
    //         'amount' => 'required|numeric|min:10|max:5000',
    //     ]);

    //     $user = $request->user();
    //     $amount = $request->amount;

    //     // Get user's default wallet and its currency
    //     $defaultWallet = $user->wallets()->where('is_default', true)->first();
    //     $targetCurrency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
    //     $amountInSmallestUnit = $targetCurrency->toSmallestUnit($amount);

    //     // Rate limiting (same as Stripe)
    //     $rateLimitKey = self::RATE_LIMIT_KEY . $user->id;
    //     if (Cache::has($rateLimitKey)) {
    //         return response()->json([
    //             'message' => 'Please wait a moment before trying again.',
    //             'retry_after' => self::RATE_LIMIT_COOLDOWN,
    //         ], 429);
    //     }
    //     Cache::put($rateLimitKey, true, self::RATE_LIMIT_COOLDOWN);

    //     // Check for pending deposit
    //     $pendingDeposit = Transaction::where('user_id', $user->id)
    //         ->where('type', 'deposit')
    //         ->where('status', 'pending')
    //         ->where('created_at', '>', now()->subMinutes(10))
    //         ->first();

    //     if ($pendingDeposit) {
    //         return response()->json([
    //             'message' => 'You already have a pending deposit. Please wait for it to complete.',
    //         ], 409);
    //     }

    //     // Calculate total with fee
    //     $feeAmount = $amount * (self::PAYPAL_FEE_PERCENTAGE / 100);
    //     $totalAmount = $amount + $feeAmount;

    //     // Create a pending transaction record
    //     $transaction = Transaction::create([
    //         'user_id'        => $user->id,
    //         'type'           => 'deposit',
    //         'destination_wallet_id' => $defaultWallet->id,
    //         'amount'         => $amountInSmallestUnit,
    //         'status'         => 'pending',
    //         'payment_method' => 'paypal',
    //         'description'    => "PayPal deposit of {$amount} {$targetCurrency->code} pending",
    //         'metadata'       => [
    //             'fee_percentage'   => self::PAYPAL_FEE_PERCENTAGE,
    //             'fee_amount'       => $feeAmount,
    //             'original_amount'  => $amount,
    //             'currency_id'      => $targetCurrency->id,
    //         ],
    //     ]);

    //     try {
    //         $provider = new PayPalClient;
    //         $provider->setApiCredentials(config('paypal'));
    //         $token = $provider->getAccessToken();
    //         $provider->setAccessToken($token);

    //         $order = $provider->createOrder([
    //             "intent" => "CAPTURE",
    //             "application_context" => [
    //                 "return_url" => route('deposits.paypal.success'),
    //                 "cancel_url" => route('deposits.paypal.cancel'),
    //                 "brand_name" => config('app.name'),
    //                 "landing_page" => "BILLING",
    //                 "user_action" => "PAY_NOW",
    //             ],
    //             "purchase_units" => [
    //                 [
    //                     "reference_id" => (string) $transaction->id,
    //                     "description" => "Wallet Deposit",
    //                     "amount" => [
    //                         "currency_code" => $targetCurrency->code,
    //                         "value" => number_format($totalAmount, 2, '.', ''),
    //                     ],
    //                     "items" => [
    //                         [
    //                             "name" => "Wallet Deposit",
    //                             "quantity" => 1,
    //                             "unit_amount" => [
    //                                 "currency_code" => $targetCurrency->code,
    //                                 "value" => number_format($amount, 2, '.', ''),
    //                             ],
    //                         ],
    //                     ],
    //                 ],
    //             ],
    //         ]);

    //         // Update transaction with PayPal order ID
    //         $transaction->update([
    //             'reference' => $order['id'],
    //             'metadata' => array_merge($transaction->metadata ?? [], ['paypal_order_id' => $order['id']]),
    //         ]);

    //         foreach ($order['links'] as $link) {
    //             if ($link['rel'] === 'approve') {
    //                 return response()->json(['approval_url' => $link['href']]);
    //             }
    //         }

    //         return response()->json(['error' => 'Approval URL not found'], 500);

    //     } catch (\Exception $e) {
    //         Log::error('PayPal order creation failed', [
    //             'user_id' => $user->id,
    //             'amount'  => $amount,
    //             'error'   => $e->getMessage(),
    //         ]);
    //         $transaction->update(['status' => 'failed']);
    //         return response()->json(['error' => $e->getMessage()], 500);
    //     }
    // }

    /**
     * Handle successful PayPal payment (redirect from PayPal).
     */
    public function handlePayPalSuccess(Request $request)
    {
        $token = $request->query('token');
        $payerId = $request->query('PayerID');

        if (!$token) {
            return redirect()->route('deposits.paypal')->with('error', 'Invalid payment token');
        }

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $tokenResponse = $provider->getAccessToken();
            $provider->setAccessToken($tokenResponse);

            $result = $provider->capturePaymentOrder($token);

            if ($result['status'] === 'COMPLETED') {
                $orderId = $result['id'];
                $transaction = Transaction::where('reference', $orderId)
                    ->where('status', 'pending')
                    ->first();

                if (!$transaction) {
                    return redirect()->route('dashboard')->with('error', 'Transaction not found');
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
                        'status'       => 'completed',
                        'completed_at' => now(),
                        'description'  => "PayPal deposit of {$amountInDollars} {$currency->code} completed",
                        'metadata'     => array_merge($transaction->metadata ?? [], [
                            'paypal_order_status' => $result['status'],
                            'paypal_payer_id'     => $payerId,
                        ]),
                    ]);

                    DB::commit();

                    return Inertia::render('deposits/Success', [
                        'type'     => 'deposit',
                        'amount'   => $amountInDollars,
                        'currency' => $currency->code,
                        'currency_symbol' => $currency->symbol,
                        'reference' => $transaction->reference,
                        'fee'      => $transaction->metadata['fee_amount'] ?? 0,
                        'date'     => now()->toISOString(),
                        'status'   => 'Completed',
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error('Failed to complete PayPal deposit', [
                        'transaction_id' => $transaction->id,
                        'error'          => $e->getMessage(),
                    ]);
                    return redirect()->route('dashboard')->with('error', 'Payment captured but failed to update wallet');
                }
            }

            return redirect()->route('deposits.paypal')->with('error', 'Payment capture failed');
        } catch (\Exception $e) {
            Log::error('PayPal success handling failed', [
                'error' => $e->getMessage(),
                'token' => $token,
            ]);
            return redirect()->route('deposits.paypal')->with('error', 'Payment processing failed');
        }
    }

    /**
     * Handle cancelled PayPal payment.
     */
    public function handlePayPalCancel(Request $request)
    {
        return redirect()->route('deposits.paypal')->with('error', 'Payment was cancelled');
    }
}


// namespace App\Http\Controllers;

// use Illuminate\Http\Request;
// use Inertia\Inertia;
// use Laravel\Cashier\Cashier;
// use Stripe\PaymentIntent;
// use Stripe\Exception\SignatureVerificationException;
// use Stripe\Webhook;
// use App\Models\User;
// use App\Models\Wallet;
// use App\Models\Transaction;
// use App\Models\Currency;
// use App\Models\ExchangeRate;
// use Illuminate\Support\Facades\Log;
// use Illuminate\Support\Facades\Cache;
// use Illuminate\Support\Facades\DB;

// class DepositController extends Controller
// {
//     /**
//      * Cache key prefix for processing locks
//      * Prevents race conditions and duplicate deposits
//      */
//     private const PROCESSING_LOCK_KEY = 'deposit_processing_';
    
//     /**
//      * Lock duration in seconds
//      */
//     private const LOCK_DURATION = 30;
    
//     /**
//      * Rate limit key prefix
//      */
//     private const RATE_LIMIT_KEY = 'deposit_rate_limit_';
    
//     /**
//      * Rate limit cooldown in seconds
//      */
//     private const RATE_LIMIT_COOLDOWN = 20;

//     /**
//      * Stripe fee percentage
//      */
//     private const STRIPE_FEE_PERCENTAGE = 2.9;

//     /**
//      * Display the deposit options page
//      */
//     public function index()
//     {
//         return Inertia::render('deposits/Index');
//     }

//     /**
//      * Display the credit/debit card deposit page
//      */
//     public function card()
//     {
//         return Inertia::render('deposits/Card');
//     }

//     /**
//      * Display the authorized agent page
//      */
//     public function authorizedAgent()
//     {
//         return Inertia::render('deposits/AuthorizedAgent');
//     }

//     /**
//      * Display the success page after a successful deposit
//      */
//     public function success(Request $request)
//     {
//         $amount = $request->get('amount', 0);
//         $sessionId = $request->get('session_id');
        
//         if ($amount <= 0) {
//             return redirect()->route('dashboard')
//                 ->with('error', 'Invalid deposit information');
//         }
        
//         $user = $request->user();
//         $defaultWallet = $user->wallets()->where('is_default', true)->first();
//         $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
//         $formattedAmount = $currency->format($amount);
        
//         return Inertia::render('deposits/Success', [
//             'amount' => $amount,
//             'formatted_amount' => $formattedAmount,
//             'session_id' => $sessionId,
//         ]);
//     }

//     /**
//      * Handle cancelled deposit
//      */
//     public function cancel(Request $request)
//     {
//         return redirect()->route('deposits.card')
//             ->with('error', 'Deposit was cancelled. Please try again.');
//     }

//     /**
//      * Create a PaymentIntent for the deposit
//      */
//     public function createPaymentIntent(Request $request)
//     {
//         $user = $request->user();
        
//         // Rate limiting
//         $rateLimitKey = self::RATE_LIMIT_KEY . $user->id;
        
//         if (Cache::has($rateLimitKey)) {
//             return response()->json([
//                 'message' => 'Please wait a moment before trying again.',
//                 'retry_after' => self::RATE_LIMIT_COOLDOWN,
//             ], 429);
//         }
        
//         Cache::put($rateLimitKey, true, self::RATE_LIMIT_COOLDOWN);
        
//         // Validation
//         $request->validate([
//             'amount' => 'required|numeric|min:10|max:5000',
//             'currency' => 'sometimes|string|size:3',
//         ]);

//         $amountInDollars = $request->amount;
        
//         // Get user's default wallet
//         $defaultWallet = $user->wallets()->where('is_default', true)->first();
//         $targetCurrency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
//         $amountInSmallestUnit = $targetCurrency->toSmallestUnit($amountInDollars);
        
//         // Check for pending deposit
//         $pendingDeposit = Transaction::where('user_id', $user->id)
//             ->where('type', 'deposit')
//             ->where('status', 'pending')
//             ->where('created_at', '>', now()->subMinutes(10))
//             ->first();
            
//         if ($pendingDeposit) {
//             return response()->json([
//                 'message' => 'You already have a pending deposit. Please wait for it to complete.',
//             ], 409);
//         }

//         // Calculate total with fee
//         $feeAmount = $amountInDollars * (self::STRIPE_FEE_PERCENTAGE / 100);
//         $totalAmount = $amountInDollars + $feeAmount;
//         $totalInCents = (int) round($totalAmount * 100);

//         // Acquire lock
//         $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
//         $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
//         if (!$lock->get()) {
//             return response()->json([
//                 'message' => 'A transaction is already in progress. Please try again.',
//             ], 409);
//         }
        
//         try {
//             // Create Stripe PaymentIntent
//             $paymentIntent = $user->pay($totalInCents);
            
//             // Create pending transaction
//             Transaction::create([
//                 'user_id' => $user->id,
//                 'type' => 'deposit',
//                 'destination_wallet_id' => $defaultWallet->id,
//                 'amount' => $amountInSmallestUnit,
//                 'status' => 'pending',
//                 'reference' => $paymentIntent->id,
//                 'payment_method' => 'stripe',
//                 'description' => "Deposit of {$amountInDollars} {$targetCurrency->code} pending confirmation",
//                 'metadata' => [
//                     'fee_percentage' => self::STRIPE_FEE_PERCENTAGE,
//                     'fee_amount' => $feeAmount,
//                     'original_amount' => $amountInDollars,
//                     'currency_id' => $targetCurrency->id,
//                 ],
//             ]);
            
//             Log::info('PaymentIntent created', [
//                 'user_id' => $user->id,
//                 'amount' => $amountInDollars,
//                 'payment_intent_id' => $paymentIntent->id,
//             ]);
            
//             $lock->release();
            
//             return response()->json([
//                 'clientSecret' => $paymentIntent->client_secret,
//                 'paymentIntentId' => $paymentIntent->id,
//                 'amount' => $amountInDollars,
//                 'currency' => $targetCurrency->code,
//             ]);
            
//         } catch (\Exception $e) {
//             $lock->release();
            
//             Log::error('Failed to create PaymentIntent', [
//                 'user_id' => $user->id,
//                 'error' => $e->getMessage(),
//             ]);
            
//             return response()->json([
//                 'message' => 'Failed to create payment. Please try again.',
//             ], 500);
//         }
//     }

//     /**
//      * Confirm successful payment and update user's wallet
//      */
//     public function confirmPayment(Request $request)
//     {
//         // Validation
//         $request->validate([
//             'payment_intent_id' => 'required|string',
//             'amount' => 'required|numeric|min:10|max:5000',
//             'currency' => 'sometimes|string|size:3',
//         ]);

//         $user = $request->user();
//         $paymentIntentId = $request->payment_intent_id;
//         $amountInDollars = $request->amount;
//         $currencyCode = $request->currency ?? 'USD';
        
//         // Get currency
//         $currency = Currency::where('code', $currencyCode)->first() ?? Currency::where('code', 'USD')->first();
//         $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

//         // Idempotency check
//         $existingTransaction = Transaction::where('reference', $paymentIntentId)
//             ->whereIn('status', ['completed', 'refunded'])
//             ->first();
            
//         if ($existingTransaction) {
//             return response()->json([
//                 'success' => true,
//                 'already_processed' => true,
//                 'message' => 'This payment has already been processed.',
//             ]);
//         }

//         // Acquire lock
//         $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
//         $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
//         if (!$lock->get()) {
//             return response()->json([
//                 'error' => 'A transaction is already in progress. Please try again.',
//             ], 409);
//         }

//         try {
//             // Verify payment with Stripe
//             $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
            
//             if ($paymentIntent->status !== 'succeeded') {
//                 $lock->release();
                
//                 Transaction::where('reference', $paymentIntentId)
//                     ->where('status', 'pending')
//                     ->update(['status' => 'failed']);
                
//                 return response()->json(['error' => 'Payment not successful'], 400);
//             }
            
//             // Double-check for race condition
//             if (Transaction::where('reference', $paymentIntentId)->where('status', 'completed')->exists()) {
//                 $lock->release();
//                 return response()->json(['success' => true, 'already_processed' => true]);
//             }
            
//             DB::beginTransaction();
            
//             try {
//                 // Get pending transaction
//                 $pendingTransaction = Transaction::where('reference', $paymentIntentId)
//                     ->where('status', 'pending')
//                     ->first();
                
//                 // Get target wallet
//                 $targetWalletId = $pendingTransaction?->destination_wallet_id;
                
//                 if (!$targetWalletId) {
//                     $defaultWallet = $user->wallets()->where('is_default', true)->first();
//                     if (!$defaultWallet) {
//                         $defaultWallet = Wallet::create([
//                             'user_id' => $user->id,
//                             'currency_id' => $currency->id,
//                             'balance' => 0,
//                             'locked_balance' => 0,
//                             'is_default' => true,
//                             'name' => 'Main Account',
//                         ]);
//                     }
//                     $targetWalletId = $defaultWallet->id;
//                 }
                
//                 // Lock and update wallet
//                 $wallet = Wallet::where('id', $targetWalletId)->lockForUpdate()->first();
                
//                 if (!$wallet) {
//                     $wallet = Wallet::create([
//                         'user_id' => $user->id,
//                         'currency_id' => $currency->id,
//                         'balance' => 0,
//                         'locked_balance' => 0,
//                         'is_default' => false,
//                         'name' => "{$currency->code} Account",
//                     ]);
//                 }
                
//                 // Add funds to wallet
//                 $wallet->increaseBalance($amountInSmallestUnit);
                
//                 // Update transaction
//                 if ($pendingTransaction) {
//                     $pendingTransaction->update([
//                         'status' => 'completed',
//                         'amount' => $amountInSmallestUnit,
//                         'completed_at' => now(),
//                         'description' => "Deposit of {$amountInDollars} {$currency->code} completed",
//                         'metadata' => [
//                             'wallet_id' => $wallet->id,
//                             'new_balance' => $wallet->balance,
//                             'available_balance' => $wallet->available_balance,
//                         ],
//                     ]);
//                 }
                
//                 DB::commit();
//                 $lock->release();
                
//                 Log::info('Deposit completed', [
//                     'user_id' => $user->id,
//                     'amount' => $amountInDollars,
//                     'wallet_id' => $wallet->id,
//                     'new_balance' => $wallet->balance,
//                 ]);
                
//                 return response()->json(['success' => true]);
                
//             } catch (\Exception $e) {
//                 DB::rollBack();
//                 $lock->release();
                
//                 Log::error('Failed to update wallet', [
//                     'user_id' => $user->id,
//                     'error' => $e->getMessage(),
//                 ]);
                
//                 return response()->json([
//                     'error' => 'Payment confirmed but failed to update wallet. Please contact support.',
//                 ], 500);
//             }
            
//         } catch (\Exception $e) {
//             $lock->release();
            
//             Log::error('Failed to confirm payment', [
//                 'user_id' => $user->id,
//                 'error' => $e->getMessage(),
//             ]);
            
//             return response()->json([
//                 'error' => 'Failed to verify payment. Please contact support.',
//             ], 500);
//         }
//     }

//     /**
//      * Rollback a failed payment (attempt to refund)
//      */
//     public function rollbackPayment(Request $request)
//     {
//         $request->validate([
//             'payment_intent_id' => 'required|string',
//         ]);

//         $user = $request->user();
//         $paymentIntentId = $request->payment_intent_id;

//         $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
//         $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
//         if (!$lock->get()) {
//             return response()->json(['error' => 'A transaction is already in progress.'], 409);
//         }

//         try {
//             $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
            
//             if ($paymentIntent->status === 'succeeded') {
//                 $existingRefunds = Cashier::stripe()->refunds->all(['payment_intent' => $paymentIntentId]);
                
//                 if (count($existingRefunds->data) > 0) {
//                     $lock->release();
//                     return response()->json(['success' => true, 'already_refunded' => true]);
//                 }
                
//                 $refund = Cashier::stripe()->refunds->create([
//                     'payment_intent' => $paymentIntentId,
//                     'reason' => 'requested_by_customer',
//                 ]);
                
//                 Transaction::where('reference', $paymentIntentId)->update(['status' => 'refunded']);
                
//                 $lock->release();
                
//                 return response()->json(['success' => true, 'refunded' => true]);
//             }
            
//             $lock->release();
//             return response()->json(['success' => true, 'refunded' => false]);
            
//         } catch (\Exception $e) {
//             $lock->release();
            
//             Log::error('Failed to rollback payment', [
//                 'user_id' => $user->id,
//                 'error' => $e->getMessage(),
//             ]);
            
//             return response()->json([
//                 'error' => 'Failed to process rollback. Please contact support.',
//             ], 500);
//         }
//     }

//     /**
//      * Handle Stripe Webhook
//      */
//     public function handleWebhook(Request $request)
//     {
//         $payload = $request->getContent();
//         $sigHeader = $request->header('Stripe-Signature');
//         $webhookSecret = config('cashier.webhook.secret');

//         try {
//             $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
//         } catch (SignatureVerificationException $e) {
//             return response()->json(['error' => 'Invalid signature'], 400);
//         }

//         if ($event->type === 'checkout.session.completed') {
//             $session = $event->data->object;
            
//             $userId = $session['metadata']['user_id'] ?? null;
//             $amountInDollars = $session['metadata']['amount'] ?? 0;
//             $sessionId = $session['id'];

//             if ($userId && $amountInDollars > 0) {
//                 $this->processSuccessfulDeposit($userId, $amountInDollars, $sessionId);
//             }
//         }

//         return response()->json(['status' => 'success']);
//     }

//     /**
//      * Process a successful deposit via webhook
//      */
//     private function processSuccessfulDeposit(int $userId, float $amountInDollars, string $sessionId): void
//     {
//         // Check if already processed
//         if (Transaction::where('reference', $sessionId)->where('status', 'completed')->exists()) {
//             return;
//         }

//         $lockKey = self::PROCESSING_LOCK_KEY . $userId;
//         $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
//         if (!$lock->get()) {
//             return;
//         }

//         $user = User::find($userId);
//         if (!$user) {
//             $lock->release();
//             return;
//         }

//         $defaultWallet = $user->wallets()->where('is_default', true)->first();
//         $currency = $defaultWallet?->currency ?? Currency::where('code', 'USD')->first();
//         $amountInSmallestUnit = $currency->toSmallestUnit($amountInDollars);

//         DB::beginTransaction();

//         try {
//             $wallet = Wallet::where('user_id', $user->id)
//                 ->where('is_default', true)
//                 ->lockForUpdate()
//                 ->first();
            
//             if (!$wallet) {
//                 $wallet = Wallet::create([
//                     'user_id' => $user->id,
//                     'currency_id' => $currency->id,
//                     'balance' => 0,
//                     'locked_balance' => 0,
//                     'is_default' => true,
//                     'name' => 'Main Account',
//                 ]);
//             }

//             $wallet->increaseBalance($amountInSmallestUnit);

//             Transaction::create([
//                 'user_id' => $user->id,
//                 'type' => 'deposit',
//                 'destination_wallet_id' => $wallet->id,
//                 'amount' => $amountInSmallestUnit,
//                 'status' => 'completed',
//                 'reference' => $sessionId,
//                 'payment_method' => 'stripe',
//                 'description' => "Deposit of {$amountInDollars} {$currency->code} via Webhook",
//                 'completed_at' => now(),
//             ]);

//             DB::commit();
//             $lock->release();

//             Log::info('Webhook deposit processed', [
//                 'user_id' => $user->id,
//                 'amount' => $amountInDollars,
//             ]);

//         } catch (\Exception $e) {
//             DB::rollBack();
//             $lock->release();
            
//             Log::error('Webhook deposit failed', [
//                 'user_id' => $user->id,
//                 'error' => $e->getMessage(),
//             ]);
//         }
//     }
// }