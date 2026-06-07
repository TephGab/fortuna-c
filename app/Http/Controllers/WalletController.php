<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\Wallet;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\Log;

class WalletController extends Controller
{
    /**
     * Add a new currency wallet for the authenticated user.
     *
     * This method is atomic – it uses firstOrCreate() to prevent race conditions.
     * If the wallet already exists, it returns a 409 Conflict error.
     * An existing wallet's balance is never read or modified.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function addCurrency(Request $request)
    {
        // Validate the currency code
        $request->validate([
            'currency_code' => 'required|string|size:3|exists:currencies,code',
        ]);

        $user = $request->user();
        $currencyCode = strtoupper($request->currency_code);
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        // Atomic operation: either creates a new wallet or returns the existing one
        $wallet = Wallet::firstOrCreate(
            [
                'user_id'     => $user->id,
                'currency_id' => $currency->id,
            ],
            [
                'balance'       => 0,           // smallest unit (e.g., 0 cents)
                'locked_balance'=> 0,
                'is_default'    => false,
                'name'          => "{$currencyCode} Account",
            ]
        );

        // If the wallet already existed, return conflict error
        if (!$wallet->wasRecentlyCreated) {
            return response()->json([
                'error' => __('You already have a :currency wallet.', ['currency' => $currencyCode])
            ], 409);
        }

        // Log success
        Log::info('User added new currency wallet', [
            'user_id'   => $user->id,
            'currency'  => $currencyCode,
            'wallet_id' => $wallet->id,
        ]);

        // Return formatted wallet data for the frontend
        return response()->json([
            'success' => true,
            'wallet'  => [
                'id'                => $wallet->id,
                'currency_code'     => $currency->code,
                'currency_symbol'   => $currency->symbol,
                'balance'           => MoneyHelper::fromSmallestUnit($wallet->balance, $currency->code),
                'formatted_balance' => MoneyHelper::format($wallet->balance, $currency->code),
                'currency_flag'     => $this->getFlagEmoji($currency->code),
                'is_default'        => false,
            ],
        ]);
    }

    /**
     * Return a list of active currencies that the user does NOT already own.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
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

    /**
     * Get the flag emoji for a given currency code.
     * Used for visual identification in the frontend.
     *
     * @param  string  $currencyCode
     * @return string
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