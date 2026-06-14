<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        if (!$user) {
            return redirect()->route('login');
        }
        
        // Get user's wallets with currency info
        $wallets = $this->getUserWallets($user);
        
        // Get the default wallet
        $defaultWallet = $wallets->firstWhere('is_default', true);
        
        // Calculate total balance in USD
        $totalBalanceInUSD = $this->calculateTotalBalanceInUSD($wallets);
        
        // Get recent transactions
        $recentTransactions = $this->getRecentTransactions($user);
        
        // Main currency for total balance display
        $mainCurrency = $this->getMainCurrency($defaultWallet);
        
        // Get user's vaults
        $vaults = $user->vaults()
            ->where('status', '!=', 'closed')
            ->latest()
            ->take(2)
            ->get()
            ->map(function($vault) {
                return [
                    'id' => $vault->id,
                    'name' => $vault->name,
                    'icon' => $vault->icon,
                    'type' => $vault->type,
                    'status' => $vault->status,
                    'formatted_balance' => $vault->formatted_balance,
                    'formatted_interest_earned' => $vault->formatted_interest_earned,
                    'interest_rate' => $vault->interest_rate,
                    'progress_percentage' => $vault->progress_percentage,
                    'days_remaining_text' => $vault->days_remaining_text,
                    'type_config' => $vault->type_config,
                ];
            });

        return Inertia::render('Dashboard', [
            'wallets' => $wallets,
            'recentTransactions' => $recentTransactions,
            'vaults' => $vaults,
            'totalBalance' => $totalBalanceInUSD,
            'mainCurrency' => $mainCurrency,
        ]);
    }
    
    /**
     * Get user's wallets with formatted balances
     */
    private function getUserWallets($user)
    {
        return Wallet::with('currency')
            ->where('user_id', $user->id)
            ->get()
            ->map(function ($wallet) {
                $currencyCode = $wallet->currency?->code ?? 'USD';
                $currencySymbol = $wallet->currency?->symbol ?? '$';
                $balance = $wallet->balance ?? 0;
                
                return [
                    'id' => $wallet->id,
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $currencySymbol,
                    'balance' => MoneyHelper::fromSmallestUnit($balance, $currencyCode),
                    'currency_flag' => $this->getFlagEmoji($currencyCode),
                    'is_default' => (bool) $wallet->is_default,
                    'formatted_balance' => MoneyHelper::format($balance, $currencyCode),
                ];
            });
    }
    
    /**
     * Calculate total balance in USD across all wallets
     */
    private function calculateTotalBalanceInUSD($wallets)
    {
        $total = 0;
        foreach ($wallets as $wallet) {
            if ($wallet['currency_code'] === 'USD') {
                $total += $wallet['balance'];
            }
        }
        return $total;
    }
    
    /**
     * Get recent transactions
     */
    private function getRecentTransactions($user)
    {
        return Transaction::with(['sourceWallet.currency', 'destinationWallet.currency', 'sourceWallet.user', 'destinationWallet.user'])
            ->where('user_id', $user->id)
            ->where('status', 'completed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($transaction) use ($user) {
                return $this->formatTransaction($transaction, $user);
            });
    }
    
    /**
     * Format a single transaction for display
     */
    private function formatTransaction($transaction, $user)
    {
        // Check if this is a vault transaction
        if ($this->isVaultTransaction($transaction)) {
            return $this->formatVaultTransaction($transaction, $user);
        }
        
        // Determine user's role by checking wallet ownership
        $isSourceUser = $transaction->sourceWallet && $transaction->sourceWallet->user_id === $user->id;
        $isDestinationUser = $transaction->destinationWallet && $transaction->destinationWallet->user_id === $user->id;
        
        // Get transaction type and amount based on user's role
        $result = $this->determineTransactionType($transaction, $isSourceUser, $isDestinationUser);
        
        // Get display name
        $displayName = $this->getTransactionDisplayName($transaction, $result['type'], $isSourceUser, $isDestinationUser);
        
        // Format date
        $dateDisplay = $this->formatDate($transaction->created_at);
        
        // Format amount with sign
        $currencySymbol = $result['currency']?->symbol ?? '$';
        $decimalPlaces = $result['currency']?->decimal_places ?? 2;
        $sign = $result['type'] === 'received' ? '+' : '-';
        $amountDisplay = $sign . $currencySymbol . number_format($result['amount'], $decimalPlaces);
        
        return [
            'id' => $transaction->id,
            'type' => $result['type'],
            'amount' => $result['amount'],
            'amount_display' => $amountDisplay,
            'currency_code' => $result['currency']?->code ?? 'USD',
            'currency_symbol' => $currencySymbol,
            'name' => $displayName,
            'description' => $transaction->description,
            'date_display' => $dateDisplay,
            'date_raw' => $transaction->created_at->toISOString(),
            'status' => $transaction->status,
        ];
    }
    
    /**
     * Check if transaction is a vault-related transaction
     */
    private function isVaultTransaction($transaction): bool
    {
        $vaultTypes = [
            'vault_deposit', 'vault_withdrawal', 'vault_interest', 
            'vault_penalty', 'vault_transfer_in', 'vault_transfer_out', 'vault_matured'
        ];
        
        return in_array($transaction->type, $vaultTypes);
    }
    
    /**
     * Format vault transaction for display (neutral - no sign for deposits/withdrawals)
     * Since user is moving money between their own wallets/vaults
     */
    private function formatVaultTransaction($transaction, $user): array
    {
        $metadata = $transaction->metadata;
        $vaultName = $metadata['vault_name'] ?? 'Vault';
        $currencyCode = $metadata['source_wallet_currency'] ?? $transaction->metadata['currency'] ?? 'USD';
        
        // Get currency symbol
        $currency = Currency::where('code', $currencyCode)->first();
        $currencySymbol = $currency?->symbol ?? '$';
        
        // Get amount in dollars
        $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currencyCode);
        
        // Default neutral values (no sign)
        $displayName = '';
        $type = 'neutral';
        $sign = '';
        $colorClass = 'text-gray-600 dark:text-gray-400';
        
        switch ($transaction->type) {
            case 'vault_deposit':
                $displayName = "Deposit to vault: {$vaultName}";
                break;
                
            case 'vault_withdrawal':
                $displayName = "Withdrawal from vault: {$vaultName}";
                break;
                
            case 'vault_interest':
                $displayName = "Interest earned: {$vaultName}";
                $type = 'received';
                $sign = '+';
                $colorClass = 'text-green-600 dark:text-green-400';
                break;
                
            case 'vault_penalty':
                $displayName = "Early withdrawal penalty: {$vaultName}";
                $type = 'sent';
                $sign = '-';
                $colorClass = 'text-red-600 dark:text-red-400';
                break;
                
            case 'vault_transfer_in':
                $displayName = "Transfer to vault: {$vaultName}";
                break;
                
            case 'vault_transfer_out':
                $displayName = "Transfer from vault: {$vaultName}";
                break;
                
            case 'vault_matured':
                $displayName = "Vault matured: {$vaultName}";
                $type = 'received';
                $sign = '+';
                $colorClass = 'text-green-600 dark:text-green-400';
                break;
                
            default:
                $displayName = $transaction->description ?? 'Vault transaction';
        }
        
        // Format amount display (no sign for neutral transactions)
        $amountDisplay = $sign ? $sign . $currencySymbol . number_format($amount, 2) : $currencySymbol . number_format($amount, 2);
        
        // Format date
        $dateDisplay = $this->formatDate($transaction->created_at);
        
        return [
            'id' => $transaction->id,
            'type' => $type,
            'amount' => $amount,
            'amount_display' => $amountDisplay,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'name' => $displayName,
            'description' => $transaction->description,
            'date_display' => $dateDisplay,
            'date_raw' => $transaction->created_at->toISOString(),
            'status' => $transaction->status,
        ];
    }
    
    /**
     * Determine transaction type based on user's role
     */
    private function determineTransactionType($transaction, $isSourceUser, $isDestinationUser)
    {
        // Case 1: User is ONLY the source (money leaves their wallet)
        if ($isSourceUser && !$isDestinationUser) {
            $currency = $transaction->sourceWallet->currency;
            $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency->code);
            return ['type' => 'sent', 'amount' => $amount, 'currency' => $currency];
        }
        
        // Case 2: User is ONLY the destination (money enters their wallet)
        if (!$isSourceUser && $isDestinationUser) {
            $currency = $transaction->destinationWallet->currency;
            $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency->code);
            return ['type' => 'received', 'amount' => $amount, 'currency' => $currency];
        }
        
        // Case 3: User is BOTH source and destination (internal exchange)
        if ($isSourceUser && $isDestinationUser) {
            $currency = $transaction->sourceWallet->currency;
            $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency->code);
            return ['type' => 'sent', 'amount' => $amount, 'currency' => $currency];
        }
        
        // Fallback: Use destination wallet if available
        if ($transaction->destinationWallet) {
            $currency = $transaction->destinationWallet->currency;
            $amount = MoneyHelper::fromSmallestUnit($transaction->amount, $currency->code);
            return ['type' => 'received', 'amount' => $amount, 'currency' => $currency];
        }
        
        return ['type' => 'sent', 'amount' => 0, 'currency' => null];
    }
    
    /**
     * Get user-friendly transaction display name
     */
    private function getTransactionDisplayName($transaction, $type, $isSourceUser, $isDestinationUser)
    {
        $metadata = $transaction->metadata;
        
        switch ($transaction->type) {
            case 'deposit':
                return 'Deposit';
                
            case 'transfer':
                if ($type === 'sent') {
                    $recipientName = $metadata['recipient_name'] ?? 
                                    ($transaction->destinationWallet->user->name ?? 'Someone');
                    return "Transfer to {$recipientName}";
                } else {
                    $senderName = $metadata['sender_name'] ?? 
                                 ($transaction->sourceWallet->user->name ?? 'Someone');
                    return "Transfer from {$senderName}";
                }
                
            case 'withdrawal':
                return 'Withdrawal';
                
            case 'exchange':
                $fromCurrency = $transaction->sourceWallet->currency->code ?? '';
                $toCurrency = $transaction->destinationWallet->currency->code ?? '';
                return "Exchange {$fromCurrency} → {$toCurrency}";
                
            case 'fee':
                return 'Fee';
                
            case 'refund':
                return 'Refund';
                
            case 'reward':
                return 'Reward';
                
            default:
                return $transaction->description ?? ucfirst($transaction->type);
        }
    }
    
    /**
     * Format date for display
     */
    private function formatDate($date)
    {
        $now = now();
        $diffInDays = $date->diffInDays($now);
        
        if ($diffInDays === 0) {
            return 'Today';
        } elseif ($diffInDays === 1) {
            return 'Yesterday';
        } elseif ($diffInDays < 7) {
            return $date->format('l');
        } else {
            return $date->format('M j, Y');
        }
    }
    
    /**
     * Get main currency for total balance display
     */
    private function getMainCurrency($defaultWallet)
    {
        if ($defaultWallet) {
            return [
                'code' => $defaultWallet['currency_code'],
                'symbol' => $defaultWallet['currency_symbol'],
                'balance' => $defaultWallet['balance'],
                'formatted_balance' => $defaultWallet['formatted_balance'],
            ];
        }
        
        return [
            'code' => 'USD',
            'symbol' => '$',
            'balance' => 0,
            'formatted_balance' => '$0.00',
        ];
    }
    
    /**
     * Get flag emoji from currency code
     */
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