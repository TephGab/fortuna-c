<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Transaction;
use App\Models\Wallet;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Cashier\Billable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'country', 'currency', 'timezone', 'registered_ip', 'preferred_locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable, Billable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'preferred_locale' => 'string',
        ];
    }

    /**
     * Get all wallets for the user
     */
    public function wallets()
    {
        return $this->hasMany(Wallet::class);
    }

    /**
     * Get the main wallet
     */
    public function mainWallet()
    {
        return $this->hasOne(Wallet::class)->where('is_main', true);
    }

    /**
     * Get the vaults for the user
     */
    public function vaults()
    {
        return $this->hasMany(Vault::class);
    }

    /**
     * Get all transactions
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Get user's default wallet
     */
    public function defaultWallet()
    {
        return $this->hasOne(Wallet::class)->where('is_default', true);
    }

    /**
     * Get user's preferred currency code from their default wallet
     */
    public function getPreferredCurrency(): string
    {
        $defaultWallet = $this->wallets()
            ->where('is_default', true)
            ->with('currency')
            ->first();

        if ($defaultWallet && $defaultWallet->currency) {
            return $defaultWallet->currency->code;
        }

        // Fallback: get any wallet
        $anyWallet = $this->wallets()->with('currency')->first();
        if ($anyWallet && $anyWallet->currency) {
            return $anyWallet->currency->code;
        }

        return 'USD';
    }

    /**
     * Accessor for preferred_currency
     */
    public function getPreferredCurrencyAttribute(): string
    {
        return $this->getPreferredCurrency();
    }
}
