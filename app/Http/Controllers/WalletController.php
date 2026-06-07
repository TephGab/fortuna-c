<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\Wallet;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    /**
     * Add a new currency wallet for the authenticated user.
     */
    public function addCurrency(Request $request)
    {
        $request->validate([
            'currency_code' => 'required|string|size:3|exists:currencies,code',
        ]);

        $user = $request->user();
        $currencyCode = strtoupper($request->currency_code);

        // Find the currency
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        // Check if user already has a wallet in this currency
        $exists = Wallet::where('user_id', $user->id)
            ->where('currency_id', $currency->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => __('You already have a :currency wallet.', ['currency' => $currencyCode])
            ], 409);
        }

        DB::beginTransaction();
        try {
            // Create the new wallet with zero balance
            $wallet = Wallet::create([
                'user_id'       => $user->id,
                'currency_id'   => $currency->id,
                'balance'       => 0,          // smallest unit (e.g., 0 cents)
                'locked_balance' => 0,
                'is_default'    => false,
                'name'          => "{$currencyCode} Account",
            ]);

            DB::commit();

            Log::info('User added new currency wallet', [
                'user_id' => $user->id,
                'currency' => $currencyCode,
                'wallet_id' => $wallet->id,
            ]);

            // Return the new wallet data formatted for the frontend
            return response()->json([
                'success' => true,
                'wallet' => [
                    'id'               => $wallet->id,
                    'currency_code'    => $currency->code,
                    'currency_symbol'  => $currency->symbol,
                    'balance'          => MoneyHelper::fromSmallestUnit($wallet->balance, $currency->code),
                    'formatted_balance' => MoneyHelper::format($wallet->balance, $currency->code),
                    'currency_flag'    => $this->getFlagEmoji($currency->code),
                    'is_default'       => false,
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add currency wallet', [
                'user_id' => $user->id,
                'currency' => $currencyCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => __('Unable to add currency. Please try again.')], 500);
        }
    }

    // Helper to get flag emoji (reuse from DashboardController)
    private function getFlagEmoji($currencyCode)
    {
        $countryMap = [
            'USD' => '🇺🇸',
            'EUR' => '🇪🇺',
            'GBP' => '🇬🇧',
            'JPY' => '🇯🇵',
            'BRL' => '🇧🇷',
            'CAD' => '🇨🇦',
            'AUD' => '🇦🇺',
            'CHF' => '🇨🇭',
            'CNY' => '🇨🇳',
            'INR' => '🇮🇳',
            'MXN' => '🇲🇽',
            'DOP' => '🇩🇴',
            'HTG' => '🇭🇹',
            'KRW' => '🇰🇷',
            'SGD' => '🇸🇬',
            'HKD' => '🇭🇰',
            'NZD' => '🇳🇿',
            'THB' => '🇹🇭',
            'VND' => '🇻🇳',
            'MYR' => '🇲🇾',
        ];
        return $countryMap[$currencyCode] ?? '🌍';
    }

    public function availableCurrencies(Request $request)
    {
        $user = $request->user();
        $ownedCurrencyIds = $user->wallets()->pluck('currency_id');
        $available = Currency::whereNotIn('id', $ownedCurrencyIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['code', 'name', 'symbol']);
        return response()->json($available);
    }
}
