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
            ->map(function ($wallet) use ($currencies) {
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
        $usdCurrency = $currencies['USD'] ?? null;
        
        foreach ($wallets as $wallet) {
            if ($wallet['currency_code'] === 'USD') {
                $totalBalanceInUSD += $wallet['balance'];
            } elseif ($usdCurrency) {
                // Simple exchange rate for non-USD currencies
                $fromCurrency = Currency::where('code', $wallet['currency_code'])->first();
                if ($fromCurrency) {
                    $rate = ExchangeRate::getRate($fromCurrency, $usdCurrency);
                    $totalBalanceInUSD += $wallet['balance'] * ($rate ?? 1);
                }
            }
        }
        
        // Get recent transactions
        $recentTransactions = Transaction::with(['sourceWallet.currency', 'destinationWallet.currency'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($transaction) {
                $type = 'sent';
                $amount = 0;
                $currency = null;
                $isIncoming = false;
                
                if ($transaction->destination_wallet_id && $transaction->destination_wallet) {
                    $type = 'received';
                    $currency = $transaction->destination_wallet->currency;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $isIncoming = true;
                } elseif ($transaction->source_wallet_id && $transaction->source_wallet) {
                    $type = 'sent';
                    $currency = $transaction->source_wallet->currency;
                    $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency?->code ?? 'USD');
                    $isIncoming = false;
                }
                
                // Format date nicely
                $date = $transaction->created_at;
                $now = now();
                $diffInDays = $date->diffInDays($now);
                
                if ($diffInDays === 0) {
                    $dateDisplay = 'Today';
                } elseif ($diffInDays === 1) {
                    $dateDisplay = 'Yesterday';
                } elseif ($diffInDays < 7) {
                    $dateDisplay = $date->format('l');
                } else {
                    $dateDisplay = $date->format('M j, Y');
                }
                
                // Transaction display name
                $displayName = $transaction->description;
                if (!$displayName) {
                    if ($transaction->type === 'deposit') {
                        $displayName = 'Deposit';
                    } elseif ($transaction->type === 'transfer') {
                        $displayName = $isIncoming ? 'Transfer Received' : 'Transfer Sent';
                    } else {
                        $displayName = ucfirst($transaction->type);
                    }
                }
                
                $currencySymbol = $currency?->symbol ?? '$';
                // FIXED: Use 'decimal_places' not 'decimal_digits'
                $decimalPlaces = $currency?->decimal_places ?? 2;
                $sign = $type === 'received' ? '+' : '-';
                $amountDisplay = $sign . $currencySymbol . number_format($amount, $decimalPlaces);
                
                return [
                    'id' => $transaction->id,
                    'type' => $type,
                    'amount' => $amount,
                    'amount_display' => $amountDisplay,
                    'currency_code' => $currency?->code ?? 'USD',
                    'currency_symbol' => $currencySymbol,
                    'name' => $displayName,
                    'description' => $transaction->description,
                    'date' => $dateDisplay,
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