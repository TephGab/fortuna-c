<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TransferController extends Controller
{
    /**
     * Cache lock key prefix for preventing concurrent transfers
     * This prevents race conditions where a user might submit multiple transfers at once
     */
    private const TRANSFER_LOCK_KEY = 'transfer_processing_';
    
    /**
     * Lock duration in seconds - prevents infinite locks if process fails
     */
    private const LOCK_DURATION = 30;
    
    /**
     * Quote expiration time in seconds - rate is locked for this duration
     */
    private const QUOTE_EXPIRATION = 60;
    
    /**
     * Transfer fee percentage (0.5% of amount)
     */
    private const TRANSFER_FEE_PERCENTAGE = 0.5;
    
    /**
     * Minimum fee amount in USD - ensures we don't charge too little for small transfers
     */
    private const MIN_FEE = 0.50;

    /**
     * Display the send money form with all necessary data
     * 
     * @return \Inertia\Response
     */
    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }

        // Get user's wallets with their currencies and formatted balances
        $wallets = $user->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'currency_code' => $wallet->currency->code,
                'currency_symbol' => $wallet->currency->symbol,
                'balance' => MoneyHelper::fromSmallestUnit($wallet->balance, $wallet->currency->code),
                'formatted_balance' => MoneyHelper::format($wallet->balance, $wallet->currency->code),
                'is_default' => $wallet->is_default,
                'decimal_places' => $wallet->currency->decimal_places,
            ];
        });

        // Get recent recipients (users the current user has sent money to before)
        $recentRecipients = $this->getRecentRecipients($user);

        // Get all active currencies for reference (used in the frontend)
        $currencies = Currency::where('is_active', true)->get()->map(function ($currency) {
            return [
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'name' => $currency->name,
            ];
        });

        return Inertia::render('transfers/Index', [
            'wallets' => $wallets,
            'recentRecipients' => $recentRecipients,
            'currencies' => $currencies,
            'fee_percentage' => self::TRANSFER_FEE_PERCENTAGE,
            'min_fee' => self::MIN_FEE,
        ]);
    }

    /**
     * Find and return recipient details by email
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecipient(Request $request)
    {
        // Validate email input
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $currentUser = auth()->user();

        // Prevent users from sending money to themselves
        if ($currentUser->email === $request->email) {
            return response()->json(['error' => 'You cannot send money to yourself'], 400);
        }

        // Find recipient by email
        $recipient = User::where('email', $request->email)->first();

        if (!$recipient) {
            return response()->json(['error' => 'User not found. Please check the email address.'], 404);
        }

        // Get all recipient wallets (they may have multiple currencies)
        $recipientWallets = $recipient->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'currency_code' => $wallet->currency->code,
                'currency_symbol' => $wallet->currency->symbol,
                'is_default' => $wallet->is_default,
            ];
        });

        return response()->json([
            'id' => $recipient->id,
            'name' => $recipient->name,
            'email' => $recipient->email,
            'wallets' => $recipientWallets,
            'default_currency' => $recipientWallets->firstWhere('is_default', true) ?? $recipientWallets->first(),
        ]);
    }

    /**
     * Calculate exchange rate, fees, and final amounts for the transfer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateTransfer(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:100000',
            'source_wallet_id' => 'required|exists:wallets,id',
            'target_currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        $sourceWallet = Wallet::with('currency')->find($request->source_wallet_id);

        // Security: Verify wallet belongs to authenticated user
        if ($sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid wallet selection'], 403);
        }

        $amount = (float) $request->amount;
        $sourceCurrency = $sourceWallet->currency;
        $targetCurrency = Currency::where('code', $request->target_currency)->first();

        if (!$targetCurrency) {
            return response()->json(['error' => 'Target currency not found'], 404);
        }

        // Check if user has sufficient balance
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($amount, $sourceCurrency->code);
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            return response()->json(['error' => 'Insufficient balance in selected wallet'], 400);
        }

        // Calculate exchange rate if currencies are different
        $isCrossCurrency = $sourceCurrency->id !== $targetCurrency->id;
        $rate = 1;
        $convertedAmount = $amount;

        if ($isCrossCurrency) {
            $rate = ExchangeRate::getRate($sourceCurrency, $targetCurrency);
            if (!$rate) {
                return response()->json(['error' => 'Exchange rate temporarily unavailable. Please try again.'], 404);
            }
            $convertedAmount = round($amount * $rate, $targetCurrency->decimal_places);
        }

        // Calculate transfer fee (percentage with minimum)
        $feeAmount = max(self::MIN_FEE, $amount * (self::TRANSFER_FEE_PERCENTAGE / 100));
        $totalAmount = $amount + $feeAmount;

        // Calculate remaining balance after transaction
        $remainingBalance = round(
            MoneyHelper::fromSmallestUnit($sourceWallet->balance, $sourceCurrency->code) - $totalAmount,
            $sourceCurrency->decimal_places
        );

        return response()->json([
            'amount' => $amount,
            'source_currency' => $sourceCurrency->code,
            'source_symbol' => $sourceCurrency->symbol,
            'target_currency' => $targetCurrency->code,
            'target_symbol' => $targetCurrency->symbol,
            'converted_amount' => $convertedAmount,
            'rate' => $rate,
            'is_cross_currency' => $isCrossCurrency,
            'fee' => round($feeAmount, 2),
            'total' => round($totalAmount, 2),
            'fee_percentage' => self::TRANSFER_FEE_PERCENTAGE,
            'min_fee' => self::MIN_FEE,
            'remaining_balance' => $remainingBalance,
        ]);
    }

    /**
     * Create a locked quote for the transfer
     * This reserves the exchange rate for a short period (60 seconds)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createQuote(Request $request)
    {
        // Validate all required data
        $validator = Validator::make($request->all(), [
            'recipient_id' => 'required|exists:users,id',
            'source_wallet_id' => 'required|exists:wallets,id',
            'recipient_wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric|min:1|max:100000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        
        // Validate source wallet belongs to user
        $sourceWallet = Wallet::with('currency')->find($request->source_wallet_id);
        if ($sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid source wallet'], 403);
        }

        // Validate recipient wallet belongs to recipient
        $recipientWallet = Wallet::with('currency')->find($request->recipient_wallet_id);
        $recipient = User::find($request->recipient_id);

        if ($recipientWallet->user_id !== $recipient->id) {
            return response()->json(['error' => 'Invalid recipient wallet'], 403);
        }

        $amount = (float) $request->amount;
        $sourceCurrency = $sourceWallet->currency;
        $targetCurrency = $recipientWallet->currency;

        // Double-check balance (since this could have changed since calculation)
        $amountInSmallestUnit = MoneyHelper::toSmallestUnit($amount, $sourceCurrency->code);
        if ($sourceWallet->balance < $amountInSmallestUnit) {
            return response()->json(['error' => 'Insufficient balance. Please refresh and try again.'], 400);
        }

        // Calculate exchange rate (fresh rate from database)
        $isCrossCurrency = $sourceCurrency->id !== $targetCurrency->id;
        $rate = 1;
        $convertedAmount = $amount;

        if ($isCrossCurrency) {
            $rate = ExchangeRate::getRate($sourceCurrency, $targetCurrency);
            if (!$rate) {
                return response()->json(['error' => 'Exchange rate temporarily unavailable'], 404);
            }
            $convertedAmount = round($amount * $rate, $targetCurrency->decimal_places);
        }

        // Calculate fee
        $feeAmount = max(self::MIN_FEE, $amount * (self::TRANSFER_FEE_PERCENTAGE / 100));
        $totalAmount = $amount + $feeAmount;

        // Generate unique quote ID
        $quoteId = uniqid('quote_', true);

        // Store quote in cache with expiration
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
            'fee' => $feeAmount,
            'fee_in_smallest_unit' => MoneyHelper::toSmallestUnit($feeAmount, $sourceCurrency->code),
            'total' => $totalAmount,
            'total_in_smallest_unit' => MoneyHelper::toSmallestUnit($totalAmount, $sourceCurrency->code),
            'is_cross_currency' => $isCrossCurrency,
            'source_currency' => $sourceCurrency->code,
            'target_currency' => $targetCurrency->code,
        ], self::QUOTE_EXPIRATION);

        Log::info('Transfer quote created', [
            'user_id' => $user->id,
            'quote_id' => $quoteId,
            'amount' => $amount,
            'recipient_id' => $recipient->id,
        ]);

        return response()->json([
            'quote_id' => $quoteId,
            'expires_in' => self::QUOTE_EXPIRATION,
        ]);
    }

    /**
     * Execute the transfer - THIS IS THE MAIN TRANSACTION METHOD
     * Uses database transactions and cache locks for data integrity
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function executeTransfer(Request $request)
    {
        // Validate quote ID
        $validator = Validator::make($request->all(), [
            'quote_id' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = auth()->user();
        
        // Retrieve the quote from cache
        $quote = Cache::get("transfer_quote_{$request->quote_id}");

        if (!$quote) {
            return response()->json(['error' => 'Quote expired. Please start over.'], 400);
        }

        // Verify the quote belongs to the current user
        $sourceWallet = Wallet::find($quote['source_wallet_id']);
        if (!$sourceWallet || $sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid quote'], 403);
        }

        // ========== ACQUIRE LOCK TO PREVENT RACE CONDITIONS ==========
        // This prevents multiple concurrent transfers from the same user
        $lockKey = self::TRANSFER_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);

        if (!$lock->get()) {
            Log::warning('Transfer lock could not be acquired', [
                'user_id' => $user->id,
                'quote_id' => $request->quote_id,
            ]);
            return response()->json(['error' => 'A transaction is already in progress. Please wait.'], 409);
        }

        // ========== START DATABASE TRANSACTION ==========
        // Everything inside this block either succeeds completely or rolls back entirely
        DB::beginTransaction();

        try {
            // Refresh wallet balance with pessimistic lock
            // This prevents any other process from modifying the same wallet
            $sourceWallet = Wallet::where('id', $quote['source_wallet_id'])
                ->lockForUpdate()
                ->first();

            // Double-check balance hasn't changed since quote was created
            $totalRequired = $quote['amount_in_smallest_unit'] + $quote['fee_in_smallest_unit'];
            if ($sourceWallet->balance < $totalRequired) {
                throw new \Exception('Insufficient balance. Please refresh and try again.');
            }

            // Get recipient wallet with lock
            $recipientWallet = Wallet::where('id', $quote['recipient_wallet_id'])
                ->lockForUpdate()
                ->first();

            if (!$recipientWallet) {
                throw new \Exception('Recipient wallet not found');
            }

            $recipient = User::find($quote['recipient_id']);
            if (!$recipient) {
                throw new \Exception('Recipient not found');
            }

            // Generate a unique reference for this transfer
            $baseReference = 'trans_' . uniqid() . '_' . time();

            // ========== DEBIT SENDER ==========
            // Subtract the transfer amount
            $sourceWallet->decreaseBalance($quote['amount_in_smallest_unit']);
            // Subtract the fee
            $sourceWallet->decreaseBalance($quote['fee_in_smallest_unit']);

            // ========== CREDIT RECIPIENT ==========
            $recipientWallet->increaseBalance($quote['converted_amount_in_smallest_unit']);

            // ========== CREATE TRANSACTION RECORDS FOR SENDER ==========
            Transaction::create([
                'user_id' => $user->id,
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $recipientWallet->id,
                'type' => 'transfer',
                'amount' => $quote['amount_in_smallest_unit'],
                'exchange_amount' => $quote['converted_amount_in_smallest_unit'],
                'exchange_rate' => $quote['rate'],
                'fee_amount' => $quote['fee_in_smallest_unit'],
                'status' => 'completed',
                'reference' => $baseReference . '_sent',
                'description' => "Transfer sent to {$recipient->name} ({$recipient->email})",
                'completed_at' => now(),
                'metadata' => [
                    'recipient_id' => $recipient->id,
                    'recipient_name' => $recipient->name,
                    'recipient_email' => $recipient->email,
                    'recipient_currency' => $quote['target_currency'],
                    'recipient_amount' => $quote['converted_amount'],
                    'transfer_fee' => $quote['fee'],
                    'exchange_rate_used' => $quote['rate'],
                ],
            ]);

            // ========== CREATE TRANSACTION RECORDS FOR RECIPIENT ==========
            Transaction::create([
                'user_id' => $recipient->id,
                'source_wallet_id' => $sourceWallet->id,
                'destination_wallet_id' => $recipientWallet->id,
                'type' => 'transfer',
                'amount' => $quote['converted_amount_in_smallest_unit'],
                'exchange_amount' => $quote['amount_in_smallest_unit'],
                'exchange_rate' => $quote['rate'],
                'fee_amount' => 0,
                'status' => 'completed',
                'reference' => $baseReference . '_received',
                'description' => "Transfer received from {$user->name} ({$user->email})",
                'completed_at' => now(),
                'metadata' => [
                    'sender_id' => $user->id,
                    'sender_name' => $user->name,
                    'sender_email' => $user->email,
                    'sender_currency' => $quote['source_currency'],
                    'sender_amount' => $quote['amount'],
                    'exchange_rate_used' => $quote['rate'],
                ],
            ]);

            // ========== COMMIT THE TRANSACTION ==========
            DB::commit();
            
            // Release the lock
            $lock->release();
            
            // Remove the quote from cache
            Cache::forget("transfer_quote_{$request->quote_id}");

            // Log successful transfer
            Log::info('Transfer completed successfully', [
                'from_user' => $user->id,
                'from_user_email' => $user->email,
                'to_user' => $recipient->id,
                'to_user_email' => $recipient->email,
                'amount' => $quote['amount'],
                'currency' => $quote['source_currency'],
                'converted_amount' => $quote['converted_amount'],
                'target_currency' => $quote['target_currency'],
                'fee' => $quote['fee'],
                'reference' => $baseReference,
            ]);

            // Return success response with redirect URL
            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'redirect_url' => route('transfers.success', [
                    'reference' => $baseReference,
                    'amount' => $quote['amount'],
                    'currency' => $quote['source_currency'],
                    'recipient_name' => $recipient->name,
                    'recipient_email' => $recipient->email,
                    'recipient_amount' => $quote['converted_amount'],
                    'recipient_currency' => $quote['target_currency'],
                    'fee' => $quote['fee'],
                ]),
            ]);

        } catch (\Exception $e) {
            // ========== ROLLBACK EVERYTHING ON ERROR ==========
            DB::rollBack();
            $lock->release();
            
            // Log the error for debugging
            Log::error('Transfer failed', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'quote_id' => $request->quote_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return user-friendly error message
            return response()->json([
                'error' => $e->getMessage() ?: 'Transfer failed. Please try again.',
            ], 500);
        }
    }

    /**
     * Display the success page after a successful transfer
     * 
     * @param Request $request
     * @return \Inertia\Response
     */
    public function success(Request $request)
    {
        return Inertia::render('transfers/Success', [
            'type' => 'transfer',
            'amount' => (float) $request->amount,
            'currency' => $request->currency,
            'currency_symbol' => $this->getCurrencySymbol($request->currency),
            'recipient_name' => $request->recipient_name,
            'recipient_email' => $request->recipient_email,
            'recipient_currency' => $request->recipient_currency,
            'recipient_amount' => (float) $request->recipient_amount,
            'reference' => $request->reference,
            'fee' => (float) $request->fee,
            'date' => now()->toISOString(),
            'status' => 'Completed',
        ]);
    }

    /**
     * Get recent recipients for the user (cached for performance)
     * 
     * @param User $user
     * @return array
     */
    private function getRecentRecipients($user): array
    {
        // Get unique recipients from completed transfers
        $sentTransfers = Transaction::where('user_id', $user->id)
            ->where('type', 'transfer')
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        $recipients = [];
        foreach ($sentTransfers as $transfer) {
            $metadata = $transfer->metadata;
            if ($metadata && isset($metadata['recipient_id'])) {
                $recipientId = $metadata['recipient_id'];
                if (!isset($recipients[$recipientId])) {
                    $recipient = User::find($recipientId);
                    if ($recipient) {
                        $recipients[$recipientId] = [
                            'id' => $recipient->id,
                            'name' => $recipient->name,
                            'email' => $recipient->email,
                            'avatar' => strtoupper(substr($recipient->name, 0, 2)),
                        ];
                    }
                }
            }
        }

        // Return only the 5 most recent unique recipients
        return array_values(array_slice($recipients, 0, 5));
    }

    /**
     * Get currency symbol from currency code
     * 
     * @param string $currencyCode
     * @return string
     */
    private function getCurrencySymbol(string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        return $currency ? $currency->symbol : '$';
    }
}