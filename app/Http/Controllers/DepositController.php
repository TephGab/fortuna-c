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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DepositController extends Controller
{
    /**
     * Cache key for processing lock
     * Prevents duplicate deposits while a transaction is in progress
     */
    private const PROCESSING_LOCK_KEY = 'deposit_processing_';
    private const LOCK_DURATION = 30; // seconds

    /**
     * Display the deposit options page
     * 
     * @return \Inertia\Response
     */
    public function index()
    {
        return Inertia::render('deposits/Index');
    }

    /**
     * Display the credit/debit card deposit page
     * 
     * @return \Inertia\Response
     */
    public function card()
    {
        return Inertia::render('deposits/Card');
    }

    /**
     * Display the success page after a successful deposit
     * 
     * @param Request $request
     * @return \Inertia\Response|\Illuminate\Http\RedirectResponse
     */
    public function success(Request $request)
    {
        $amount = $request->get('amount', 0);
        $sessionId = $request->get('session_id');
        
        if ($amount <= 0) {
            return redirect()->route('dashboard')->with('error', 'Invalid deposit information');
        }
        
        return Inertia::render('deposits/Success', [
            'amount' => $amount,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Handle cancelled deposit
     * 
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel(Request $request)
    {
        return redirect()->route('deposits.card')->with('error', 'Deposit was cancelled. Please try again.');
    }

    /**
     * Create a PaymentIntent for the deposit
     * Includes rate limiting and duplicate prevention
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createPaymentIntent(Request $request)
    {
        // Rate limiting: Prevent too many requests in a short time
        $user = $request->user();
        $rateLimitKey = 'deposit_rate_limit_' . $user->id;
        
        if (Cache::has($rateLimitKey)) {
            Log::warning('Rate limit exceeded for deposit', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
            
            return response()->json([
                'message' => 'Please wait a moment before trying again.',
            ], 429); // Too Many Requests
        }
        
        // Set rate limit: maximum 3 attempts per minute
        Cache::put($rateLimitKey, true, 20); // 20 seconds cooldown
        
        // Validate the deposit amount
        $request->validate(['amount' => 'required|numeric|min:10|max:5000',]);

        $amount = $request->amount;

        
        // Check if user already has a pending deposit
        $pendingDeposit = Transaction::where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();
            
        if ($pendingDeposit) {
            Log::warning('User has pending deposit', [
                'user_id' => $user->id,
                'pending_transaction_id' => $pendingDeposit->id,
            ]);
            
            return response()->json([
                'message' => 'You already have a pending deposit. Please wait for it to complete.',
            ], 409); // Conflict
        }

        // Calculate total with 2.9% Stripe fee
        $totalAmount = $amount * 1.029;
        $totalInCents = (int) round($totalAmount * 100);

        // Use a lock to prevent race conditions
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
        if (!$lock->get()) {
            Log::warning('Could not acquire lock for deposit', [
                'user_id' => $user->id,
            ]);
            
            return response()->json(['message' => 'A transaction is already in progress. Please try again.',], 409);
        }
        
        try {
            // Create a PaymentIntent using Laravel Cashier
            $paymentIntent = $user->pay($totalInCents);
            
            // Create a pending transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $amount,
                'status' => 'pending',
                'reference' => $paymentIntent->id,
                'payment_method' => 'stripe',
                'description' => 'Deposit via Credit/Debit Card - Pending'
            ]);
            
            Log::info('PaymentIntent created', [
                'user_id' => $user->id,
                'amount' => $amount,
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id,
            ]);
            
            $lock->release();
            
            return response()->json([
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id,
                'amount' => $amount,
            ]);
            
        } catch (\Exception $e) {
            $lock->release();
            
            Log::error('Failed to create PaymentIntent', [
                'user_id' => $user->id,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to create payment. Please try again.',
            ], 500);
        }
    }

    /**
     * Confirm successful payment and update user's wallet
     * Includes idempotency protection to prevent duplicate processing
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
            'amount' => 'required|numeric|min:10|max:5000',
        ]);

        $user = $request->user();
        $paymentIntentId = $request->payment_intent_id;
        $amount = $request->amount;

        // Check if this payment has already been processed (idempotency)
        $existingTransaction = Transaction::where('reference', $paymentIntentId)
            ->where('status', 'completed')
            ->first();
            
        if ($existingTransaction) {
            Log::warning('Duplicate payment confirmation attempted', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntentId,
                'existing_transaction_id' => $existingTransaction->id,
            ]);
            
            return response()->json([
                'success' => true,
                'already_processed' => true,
                'message' => 'This payment has already been processed.',
            ]);
        }

        // Acquire lock to prevent race conditions
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
        if (!$lock->get()) {
            Log::warning('Could not acquire lock for confirmation', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntentId,
            ]);
            
            return response()->json([
                'error' => 'A transaction is already in progress. Please try again.',
            ], 409);
        }

        try {
            // Retrieve the PaymentIntent from Stripe to verify it succeeded
            $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
            
            // Verify payment was successful
            if ($paymentIntent->status !== 'succeeded') {
                $lock->release();
                
                // Update pending transaction to failed if exists
                Transaction::where('reference', $paymentIntentId)
                    ->where('status', 'pending')
                    ->update(['status' => 'failed']);
                
                Log::warning('Payment not successful', [
                    'user_id' => $user->id,
                    'payment_intent_id' => $paymentIntentId,
                    'status' => $paymentIntent->status,
                ]);
                
                return response()->json([
                    'error' => 'Payment not successful'
                ], 400);
            }
            
            // Double-check if transaction was already completed (race condition safety)
            $alreadyCompleted = Transaction::where('reference', $paymentIntentId)
                ->where('status', 'completed')
                ->exists();
                
            if ($alreadyCompleted) {
                $lock->release();
                
                return response()->json(['success' => true, 'already_processed' => true]);
            }
            
            // Use database transaction with pessimistic locking
            DB::beginTransaction();
            
            try {
                // Lock the wallet row for update to prevent concurrent modifications
                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
                
                if (!$wallet) {
                    $wallet = Wallet::create([
                        'user_id' => $user->id,
                        'balance' => 0
                    ]);
                }
                
                // Update wallet balance
                $wallet->incrementBalance($amount);
                
                // Update pending transaction to completed
                $transaction = Transaction::where('reference', $paymentIntentId)
                    ->where('status', 'pending')
                    ->first();
                    
                if ($transaction) {
                    $transaction->update([
                        'status' => 'completed',
                        'description' => 'Deposit via Credit/Debit Card - Completed'
                    ]);
                } else {
                    // Create transaction record if pending doesn't exist (shouldn't happen)
                    Transaction::create([
                        'user_id' => $user->id,
                        'type' => 'deposit',
                        'amount' => $amount,
                        'status' => 'completed',
                        'reference' => $paymentIntentId,
                        'payment_method' => 'stripe',
                        'description' => 'Deposit via Credit/Debit Card'
                    ]);
                }
                
                DB::commit();
                $lock->release();
                
                Log::info('Deposit completed successfully', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'payment_intent_id' => $paymentIntentId,
                    'new_balance' => $wallet->balance,
                ]);
                
                return response()->json(['success' => true]);
                
            } catch (\Exception $e) {
                DB::rollBack();
                $lock->release();
                
                Log::error('Failed to update wallet after payment', [
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'payment_intent_id' => $paymentIntentId,
                    'error' => $e->getMessage(),
                ]);
                
                return response()->json([
                    'error' => 'Payment confirmed but failed to update wallet. Please contact support.'
                ], 500);
            }
            
        } catch (\Exception $e) {
            $lock->release();
            
            Log::error('Failed to confirm payment', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Failed to verify payment. Please contact support.'
            ], 500);
        }
    }

    /**
     * Rollback a failed payment (attempt to refund)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function rollbackPayment(Request $request)
    {
        $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        $user = $request->user();
        $paymentIntentId = $request->payment_intent_id;

        // Acquire lock for rollback
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
        if (!$lock->get()) {
            return response()->json([
                'error' => 'A transaction is already in progress.',
            ], 409);
        }

        try {
            $paymentIntent = Cashier::stripe()->paymentIntents->retrieve($paymentIntentId);
            
            if ($paymentIntent->status === 'succeeded') {
                // Check if already refunded
                $existingRefunds = Cashier::stripe()->refunds->all([
                    'payment_intent' => $paymentIntentId,
                ]);
                
                if (count($existingRefunds->data) > 0) {
                    $lock->release();
                    return response()->json(['success' => true, 'already_refunded' => true]);
                }
                
                // Create a refund
                $refund = Cashier::stripe()->refunds->create([
                    'payment_intent' => $paymentIntentId,
                    'reason' => 'requested_by_customer',
                ]);
                
                // Update transaction status
                Transaction::where('reference', $paymentIntentId)
                    ->update(['status' => 'refunded']);
                
                $lock->release();
                
                Log::info('Payment refunded', [
                    'user_id' => $user->id,
                    'payment_intent_id' => $paymentIntentId,
                    'refund_id' => $refund->id,
                ]);
                
                return response()->json(['success' => true, 'refunded' => true]);
            }
            
            $lock->release();
            return response()->json(['success' => true, 'refunded' => false]);
            
        } catch (\Exception $e) {
            $lock->release();
            
            Log::error('Failed to rollback payment', [
                'user_id' => $user->id,
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'error' => 'Failed to process rollback. Please contact support.'
            ], 500);
        }
    }

    /**
     * Handle Stripe Webhook
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('cashier.webhook.secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (SignatureVerificationException $e) {
            Log::warning('Invalid webhook signature', [
                'error' => $e->getMessage(),
            ]);
            
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Handle checkout.session.completed event
        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            
            $userId = $session['metadata']['user_id'] ?? null;
            $amount = $session['metadata']['amount'] ?? 0;
            $sessionId = $session['id'];

            if ($userId && $amount > 0) {
                $this->processSuccessfulDeposit($userId, $amount, $sessionId);
            }
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Process a successful deposit (update wallet and create transaction)
     * Uses the same protection mechanisms as confirmPayment
     * 
     * @param int $userId
     * @param float $amount
     * @param string $sessionId
     * @return void
     */
    private function processSuccessfulDeposit($userId, $amount, $sessionId)
    {
        // Check if already processed
        $existingTransaction = Transaction::where('reference', $sessionId)
            ->where('status', 'completed')
            ->first();
            
        if ($existingTransaction) {
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
            Log::warning('User not found for webhook deposit', [
                'user_id' => $userId,
                'session_id' => $sessionId,
            ]);
            return;
        }

        DB::beginTransaction();

        try {
            // Lock the wallet row
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();
            
            if (!$wallet) {
                $wallet = Wallet::create([
                    'user_id' => $user->id,
                    'balance' => 0
                ]);
            }

            $wallet->incrementBalance($amount);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'deposit',
                'amount' => $amount,
                'status' => 'completed',
                'reference' => $sessionId,
                'payment_method' => 'stripe',
                'description' => 'Deposit via Credit/Debit Card (Webhook)'
            ]);

            DB::commit();
            $lock->release();

            Log::info('Webhook deposit processed successfully', [
                'user_id' => $user->id,
                'amount' => $amount,
                'session_id' => $sessionId,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            $lock->release();
            
            Log::error('Webhook deposit failed', [
                'user_id' => $user->id,
                'amount' => $amount,
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}