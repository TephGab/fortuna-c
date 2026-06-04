<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Helpers\MoneyHelper;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Get all currencies for easy lookup
        $currencies = Currency::all()->keyBy('code');
        
        // Get user's wallets with currency info
        $wallets = Wallet::with('currency')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($wallet) {
                $currencyCode = $wallet->currency?->code ?? 'USD';
                $currencySymbol = $wallet->currency?->symbol ?? '$';
                $balance = $wallet->balance ?? 0;
                
                // Convert to display amount
                $balanceConverted = MoneyHelper::fromSmallestUnit($balance, $currencyCode);
                $formattedBalance = MoneyHelper::format($balance, $currencyCode);
                
                return [
                    'id' => $wallet->id,
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $currencySymbol,
                    'balance' => $balanceConverted,
                    'currency_flag' => $this->getFlagEmoji($currencyCode),
                    'is_default' => (bool) $wallet->is_default,
                    'formatted_balance' => $formattedBalance,
                ];
            });
        
        // Get the default wallet
        $defaultWallet = $wallets->firstWhere('is_default', true);
        
        // Calculate total balance in USD
        $totalBalanceInUSD = 0;
        foreach ($wallets as $wallet) {
            if ($wallet['currency_code'] === 'USD') {
                $totalBalanceInUSD += $wallet['balance'];
            }
        }
        
        // Get recent transactions
        $recentTransactions = Transaction::with(['sourceWallet.currency', 'destinationWallet.currency'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) {
                // Initialize variables
                $type = 'sent';
                $amount = 0;
                $currency = null;
                $displayName = '';
                
                // HANDLE DEPOSITS FIRST (most common case for new users)
                if ($transaction->type === 'deposit') {
                    // Deposits are always INCOMING money
                    $type = 'received';
                    $currency = $transaction->destinationWallet->currency ?? null;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $displayName = 'Deposit';
                }
                // Handle transfers between wallets
                elseif ($transaction->type === 'transfer') {
                    // Check if user is the sender or receiver
                    if ($transaction->source_wallet_id && $transaction->sourceWallet) {
                        // User is the sender
                        $type = 'sent';
                        $currency = $transaction->sourceWallet->currency ?? null;
                        $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                        $displayName = 'Transfer Sent';
                    } elseif ($transaction->destination_wallet_id && $transaction->destinationWallet) {
                        // User is the receiver
                        $type = 'received';
                        $currency = $transaction->destinationWallet->currency ?? null;
                        $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                        $displayName = 'Transfer Received';
                    }
                }
                // Handle withdrawals (money leaving the platform)
                elseif ($transaction->type === 'withdrawal') {
                    $type = 'sent';
                    $currency = $transaction->sourceWallet->currency ?? null;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $displayName = 'Withdrawal';
                }
                // Handle fees
                elseif ($transaction->type === 'fee') {
                    $type = 'sent';
                    $currency = $transaction->sourceWallet->currency ?? null;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $displayName = 'Fee';
                }
                // Handle refunds
                elseif ($transaction->type === 'refund') {
                    $type = 'received';
                    $currency = $transaction->destinationWallet->currency ?? null;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $displayName = 'Refund';
                }
                // Default fallback
                else {
                    // Try to determine based on wallet presence
                    if ($transaction->destination_wallet_id && $transaction->destinationWallet) {
                        $type = 'received';
                        $currency = $transaction->destinationWallet->currency ?? null;
                        $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                        $displayName = ucfirst($transaction->type);
                    } elseif ($transaction->source_wallet_id && $transaction->sourceWallet) {
                        $type = 'sent';
                        $currency = $transaction->sourceWallet->currency ?? null;
                        $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                        $displayName = ucfirst($transaction->type);
                    }
                }
                
                // Use custom description if available
                if ($transaction->description && $transaction->type !== 'deposit') {
                    $displayName = $transaction->description;
                }
                
                // Format date for display
                $date = $transaction->created_at;
                $now = now();
                $diffInDays = $date->diffInDays($now);
                
                if ($diffInDays === 0) {
                    $dateDisplay = 'Today';
                } elseif ($diffInDays === 1) {
                    $dateDisplay = 'Yesterday';
                } elseif ($diffInDays < 7) {
                    $dateDisplay = $date->format('l'); // Monday, Tuesday, etc.
                } else {
                    $dateDisplay = $date->format('M j, Y');
                }
                
                // Format the amount with sign
                $currencySymbol = $currency?->symbol ?? '$';
                $decimalPlaces = $currency?->decimal_places ?? 2;
                $sign = $type === 'received' ? '+' : '-';
                $amountDisplay = $sign . $currencySymbol . number_format($amount, $decimalPlaces);
                
                // Debug log (remove after confirming it works)
                \Log::info('Transaction processed', [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'detected_type' => $type,
                    'sign' => $sign,
                    'amount' => $amount,
                    'amount_display' => $amountDisplay,
                ]);
                
                return [
                    'id' => $transaction->id,
                    'type' => $type,
                    'amount' => $amount,
                    'amount_display' => $amountDisplay,
                    'currency_code' => $currency?->code ?? 'USD',
                    'currency_symbol' => $currencySymbol,
                    'name' => $displayName,
                    'description' => $transaction->description,
                    'date_display' => $dateDisplay,
                    'date_raw' => $date->toISOString(),
                    'status' => $transaction->status,
                ];
            });
        
        // Main currency for total balance display
        $mainCurrency = $defaultWallet 
            ? [
                'code' => $defaultWallet['currency_code'], 
                'symbol' => $defaultWallet['currency_symbol'],
                'balance' => $defaultWallet['balance'],
                'formatted_balance' => $defaultWallet['formatted_balance'],
            ]
            : ['code' => 'USD', 'symbol' => '$', 'balance' => 0, 'formatted_balance' => '$0.00'];
        
        return Inertia::render('Dashboard', [
            'wallets' => $wallets,
            'recentTransactions' => $recentTransactions,
            'totalBalance' => $totalBalanceInUSD,
            'mainCurrency' => $mainCurrency,
        ]);
    }
    
    private function getFlagEmoji($currencyCode)
    {
        $countryMap = [
            'USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'JPY' => '🇯🇵',
            'BRL' => '🇧🇷', 'CAD' => '🇨🇦', 'AUD' => '🇦🇺', 'CHF' => '🇨🇭',
            'CNY' => '🇨🇳', 'INR' => '🇮🇳', 'MXN' => '🇲🇽', 'DOP' => '🇩🇴',
            'HTG' => '🇭🇹', 'KRW' => '🇰🇷', 'SGD' => '🇸🇬', 'HKD' => '🇭🇰',
            'NZD' => '🇳🇿', 'THB' => '🇹🇭', 'VND' => '🇻🇳', 'MYR' => '🇲🇾',
        ];
        return $countryMap[$currencyCode] ?? '🌍';
    }
}