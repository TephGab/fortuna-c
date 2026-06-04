<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Currency;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'country' => 'required|string|size:2',
        ])->validate();

        // Get currency from country
        $currency = $this->getCurrencyFromCountry($input['country']);
        
        // Get or create currency record
        $currencyRecord = Currency::where('code', $currency)->first();
        
        if (!$currencyRecord) {
            $currencyRecord = Currency::where('code', 'USD')->first();
        }

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
            'country' => $input['country'],
            'currency_id' => $currencyRecord->id,
            'registered_ip' => request()->ip(),
        ]);

        // Create main wallet for the user
        Wallet::create([
            'user_id' => $user->id,
            'currency_id' => $currencyRecord->id,
            'balance' => 0,
            'is_default' => true,
            'name' => 'Main Account',
        ]);

        return $user;
    }

    /**
     * Get currency code from country code
     */
    private function getCurrencyFromCountry(string $countryCode): string
    {
        $map = [
            'US' => 'USD', 'GB' => 'GBP', 'CA' => 'CAD', 'AU' => 'AUD',
            'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
            'JP' => 'JPY', 'CN' => 'CNY', 'IN' => 'INR', 'BR' => 'BRL',
            'MX' => 'MXN', 'DO' => 'DOP', 'HT' => 'HTG', 'CH' => 'CHF',
            'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK', 'PL' => 'PLN',
            'TR' => 'TRY', 'KR' => 'KRW', 'SG' => 'SGD', 'HK' => 'HKD',
            'NZ' => 'NZD', 'TH' => 'THB', 'VN' => 'VND', 'MY' => 'MYR',
            'PH' => 'PHP', 'ZA' => 'ZAR', 'NG' => 'NGN', 'AE' => 'AED',
            'SA' => 'SAR',
        ];
        
        return $map[$countryCode] ?? 'USD';
    }
}

// namespace App\Actions\Fortify;

// use App\Concerns\PasswordValidationRules;
// use App\Concerns\ProfileValidationRules;
// use App\Models\User;
// use Illuminate\Support\Facades\Validator;
// use Laravel\Fortify\Contracts\CreatesNewUsers;

// class CreateNewUser implements CreatesNewUsers
// {
//     use PasswordValidationRules, ProfileValidationRules;

//     /**
//      * Validate and create a newly registered user.
//      *
//      * @param  array<string, string>  $input
//      */
//     public function create(array $input): User
//     {
//         Validator::make($input, [
//             ...$this->profileRules(),
//             'password' => $this->passwordRules(),
//         ])->validate();

//         return User::create([
//             'name' => $input['name'],
//             'email' => $input['email'],
//             'password' => $input['password'],
//         ]);
//     }
// }
