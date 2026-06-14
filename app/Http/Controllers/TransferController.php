<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use App\Helpers\MoneyHelper;
use App\Services\Transfer\TransferFeeService;
use App\Services\Transfer\ExchangeRateService;
use App\Services\Transfer\RecipientService;
use App\Services\Transfer\BalanceCheckService;
use App\Services\Transfer\TransferLockService;
use App\Services\Transfer\TransferQuoteService;
use App\Services\Transfer\TransferExecutionService;
use Illuminate\Support\Facades\Log;

class TransferController extends Controller
{
    protected TransferFeeService $feeService;
    protected ExchangeRateService $exchangeRateService;
    protected RecipientService $recipientService;
    protected BalanceCheckService $balanceCheckService;
    protected TransferLockService $lockService;
    protected TransferQuoteService $quoteService;
    protected TransferExecutionService $executionService;

    public function __construct(
        TransferFeeService $feeService,
        ExchangeRateService $exchangeRateService,
        RecipientService $recipientService,
        BalanceCheckService $balanceCheckService,
        TransferLockService $lockService,
        TransferQuoteService $quoteService,
        TransferExecutionService $executionService
    ) {
        $this->feeService = $feeService;
        $this->exchangeRateService = $exchangeRateService;
        $this->recipientService = $recipientService;
        $this->balanceCheckService = $balanceCheckService;
        $this->lockService = $lockService;
        $this->quoteService = $quoteService;
        $this->executionService = $executionService;
    }

    /**
     * Display the send money form with all necessary data
     */
    public function index()
    {
        $user = auth()->user();

        if (!$user) {
            return redirect()->route('login');
        }

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

        $recentRecipients = $this->recipientService->getRecentRecipients($user);

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
            'fee_percentage' => 0.5,
            'min_fee' => 0.50,
        ]);
    }

    /**
     * Find and return recipient details by email
     */
    public function getRecipient(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        try {
            $currentUser = auth()->user();
            $recipient = $this->recipientService->findByEmail($request->email, $currentUser);
            return response()->json($recipient);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Calculate exchange rate, fees, and final amounts for the transfer
     */
    public function calculateTransfer(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1|max:100000',
            'source_wallet_id' => 'required|exists:wallets,id',
            'target_currency' => 'required|string|size:3',
        ]);

        $user = auth()->user();
        $sourceWallet = Wallet::with('currency')->find($request->source_wallet_id);

        if ($sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid wallet selection'], 403);
        }

        $amount = (float) $request->amount;
        $targetCurrency = Currency::where('code', $request->target_currency)->first();

        if (!$targetCurrency) {
            return response()->json(['error' => 'Target currency not found'], 404);
        }

        try {
            // Check balance
            $this->balanceCheckService->check($sourceWallet, $amount);

            // Calculate exchange rate
            $exchangeData = $this->exchangeRateService->calculate($sourceWallet, $targetCurrency, $amount);

            // Calculate fee
            $feeData = $this->feeService->calculate($amount);

            // Calculate remaining balance
            $remainingBalance = round(
                MoneyHelper::fromSmallestUnit($sourceWallet->balance, $sourceWallet->currency->code) - $feeData['total'],
                $sourceWallet->currency->decimal_places
            );

            return response()->json([
                'amount' => $amount,
                'source_currency' => $sourceWallet->currency->code,
                'source_symbol' => $sourceWallet->currency->symbol,
                'target_currency' => $targetCurrency->code,
                'target_symbol' => $targetCurrency->symbol,
                'converted_amount' => $exchangeData['converted_amount'],
                'rate' => $exchangeData['rate'],
                'is_cross_currency' => $exchangeData['is_cross_currency'],
                'fee' => $feeData['fee'],
                'total' => $feeData['total'],
                'fee_percentage' => $feeData['percentage'],
                'min_fee' => $feeData['min_fee'],
                'remaining_balance' => $remainingBalance,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Create a locked quote for the transfer
     */
    public function createQuote(Request $request)
    {
        $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'source_wallet_id' => 'required|exists:wallets,id',
            'recipient_wallet_id' => 'required|exists:wallets,id',
            'amount' => 'required|numeric|min:1|max:100000',
        ]);

        $user = auth()->user();

        $sourceWallet = Wallet::with('currency')->find($request->source_wallet_id);
        if ($sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid source wallet'], 403);
        }

        $recipientWallet = Wallet::with('currency')->find($request->recipient_wallet_id);
        $recipient = User::find($request->recipient_id);

        if ($recipientWallet->user_id !== $recipient->id) {
            return response()->json(['error' => 'Invalid recipient wallet'], 403);
        }

        $amount = (float) $request->amount;

        try {
            // Check balance for quote
            $amountInSmallestUnit = MoneyHelper::toSmallestUnit($amount, $sourceWallet->currency->code);
            $this->balanceCheckService->checkForQuote($sourceWallet, $amountInSmallestUnit);

            // Calculate exchange rate
            $exchangeData = $this->exchangeRateService->calculate(
                $sourceWallet,
                $recipientWallet->currency,
                $amount
            );

            // Calculate fee
            $feeData = $this->feeService->calculate($amount);

            // Create quote
            $quoteId = $this->quoteService->create(
                $recipient,
                $sourceWallet,
                $recipientWallet,
                $amount,
                $exchangeData['converted_amount'],
                $exchangeData['rate'],
                $feeData
            );

            Log::info('Transfer quote created', [
                'user_id' => $user->id,
                'quote_id' => $quoteId,
                'amount' => $amount,
                'recipient_id' => $recipient->id,
            ]);

            return response()->json([
                'quote_id' => $quoteId,
                'expires_in' => 60,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Execute the transfer
     */
    public function executeTransfer(Request $request)
    {
        $request->validate([
            'quote_id' => 'required|string',
        ]);

        $user = auth()->user();
        $quote = $this->quoteService->get($request->quote_id);

        if (!$quote) {
            return response()->json(['error' => 'Quote expired. Please start over.'], 400);
        }

        $sourceWallet = Wallet::find($quote['source_wallet_id']);
        if (!$sourceWallet || $sourceWallet->user_id !== $user->id) {
            return response()->json(['error' => 'Invalid quote'], 403);
        }

        $lock = $this->lockService->acquire($user);

        try {
            $result = $this->executionService->execute($quote, $user);
            $this->lockService->release($lock);
            $this->quoteService->delete($request->quote_id);

            return response()->json([
                'success' => true,
                'message' => 'Transfer completed successfully',
                'redirect_url' => route('transfers.success', [
                    'reference' => $result['reference'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'recipient_name' => $result['recipient_name'],
                    'recipient_email' => $result['recipient_email'],
                    'recipient_amount' => $result['converted_amount'],
                    'recipient_currency' => $result['target_currency'],
                    'fee' => $result['fee'],
                ]),
            ]);
        } catch (\Exception $e) {
            $this->lockService->release($lock);
            Log::error('Transfer failed', [
                'user_id' => $user->id,
                'quote_id' => $request->quote_id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the success page after a successful transfer
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
     * Get currency symbol from currency code
     */
    private function getCurrencySymbol(string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        return $currency ? $currency->symbol : '$';
    }
}
