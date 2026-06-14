<?php

namespace App\Services\Vault;

use App\Models\User;
use App\Models\Vault;
use App\Models\Wallet;
use App\Models\VaultTransaction;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class VaultService
{
    private const INTEREST_CACHE_KEY = 'vault_interest_calculation_';
    private const DAILY_INTEREST_LIMIT = 86400;

    // ============================================================================
    // VAULT CREATION
    // ============================================================================

    // app/Services/Vault/VaultService.php

public function createVault(
    User $user,
    Wallet $wallet,
    string $name,
    string $type,
    int $initialAmount = 0,
    ?string $description = null
): Vault {
    $typeConfig = Vault::TYPES[$type];
    
    $lockDays = $typeConfig['lock_days'];
    $lockedUntil = $lockDays > 0 ? now()->addDays($lockDays) : null;
    $maturesAt = $lockDays > 0 ? now()->addDays($lockDays) : null;

    // FIX: Set status to LOCKED for locked vault types, ACTIVE for flexible
    $status = $type === 'flexible' ? Vault::STATUS_ACTIVE : Vault::STATUS_LOCKED;

    $vault = Vault::create([
        'user_id' => $user->id,
        'wallet_id' => $wallet->id,
        'name' => $name,
        'icon' => $typeConfig['icon'],
        'type' => $type,
        'status' => $status,  // ← FIXED: Now 'locked' for locked vaults
        'balance' => $initialAmount,
        'withdrawable_balance' => $this->getInitialWithdrawableBalance($type, $initialAmount),
        'original_balance' => $initialAmount,
        'interest_rate' => $typeConfig['interest_rate'],
        'locked_until' => $lockedUntil,
        'matures_at' => $maturesAt,
        'description' => $description,
    ]);

    if ($initialAmount > 0) {
        $this->recordTransaction($vault, VaultTransaction::TYPE_DEPOSIT, $initialAmount);
    }

    Log::info('Vault created', [
        'user_id' => $user->id, 
        'vault_id' => $vault->id,
        'type' => $type,
        'status' => $status  // ← Log the status for debugging
    ]);

    return $vault;
}

    // public function createVault(
    //     User $user,
    //     Wallet $wallet,
    //     string $name,
    //     string $type,
    //     int $initialAmount = 0,
    //     ?string $description = null
    // ): Vault {
    //     $typeConfig = Vault::TYPES[$type];
        
    //     $lockDays = $typeConfig['lock_days'];
    //     $lockedUntil = $lockDays > 0 ? now()->addDays($lockDays) : null;
    //     $maturesAt = $lockDays > 0 ? now()->addDays($lockDays) : null;

    //     $vault = Vault::create([
    //         'user_id' => $user->id,
    //         'wallet_id' => $wallet->id,
    //         'name' => $name,
    //         'icon' => $typeConfig['icon'],
    //         'type' => $type,
    //         'status' => Vault::STATUS_ACTIVE,
    //         'balance' => $initialAmount,
    //         'withdrawable_balance' => $this->getInitialWithdrawableBalance($type, $initialAmount),
    //         'original_balance' => $initialAmount,
    //         'interest_rate' => $typeConfig['interest_rate'],
    //         'locked_until' => $lockedUntil,
    //         'matures_at' => $maturesAt,
    //         'description' => $description,
    //     ]);

    //     if ($initialAmount > 0) {
    //         $this->recordTransaction($vault, VaultTransaction::TYPE_DEPOSIT, $initialAmount);
    //     }

    //     Log::info('Vault created', ['user_id' => $user->id, 'vault_id' => $vault->id]);

    //     return $vault;
    // }

    private function getInitialWithdrawableBalance(string $type, int $initialAmount): int
    {
        if ($type === 'flexible') return $initialAmount;
        return 0;
    }

    // ============================================================================
    // DEPOSIT
    // ============================================================================

    public function deposit(Vault $vault, int $amountInSmallestUnit, ?Wallet $sourceWallet = null): void
    {
        $wallet = $sourceWallet ?? $vault->wallet;
        
        if ($wallet->currency_code !== $vault->wallet->currency_code) {
            throw new \Exception('Wallet currency must match vault currency');
        }
        
        if ($wallet->balance < $amountInSmallestUnit) {
            throw new \Exception('Insufficient balance in wallet');
        }
        
        DB::beginTransaction();
        
        try {
            $wallet->decreaseBalance($amountInSmallestUnit);
            $vault->increment('balance', $amountInSmallestUnit);
            $this->updateWithdrawableBalanceForDeposit($vault, $amountInSmallestUnit);
            
            if ($vault->isLocked() && $vault->status !== Vault::STATUS_LOCKED) {
                $vault->update(['status' => Vault::STATUS_LOCKED]);
            }
            
            $this->recordTransaction($vault, VaultTransaction::TYPE_DEPOSIT, $amountInSmallestUnit);
            
            DB::commit();
            
            Log::info('Deposit completed', ['vault_id' => $vault->id, 'amount' => $amountInSmallestUnit]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function updateWithdrawableBalanceForDeposit(Vault $vault, int $amount): void
    {
        if ($vault->type === 'flexible') {
            $vault->increment('withdrawable_balance', $amount);
        } elseif ($vault->isMatured()) {
            $vault->increment('withdrawable_balance', $amount);
        }
    }

    // ============================================================================
    // WITHDRAWAL
    // ============================================================================

    public function withdraw(Vault $vault, int $amountInSmallestUnit): array
    {
        $availableToWithdraw = $this->getAvailableWithdrawableBalance($vault);
        
        if ($availableToWithdraw < $amountInSmallestUnit) {
            throw new \Exception('Insufficient withdrawable balance');
        }
        
        DB::beginTransaction();
        
        try {
            $penaltyAmount = $this->calculateEarlyWithdrawalPenalty($vault, $amountInSmallestUnit);
            $netWithdrawAmount = $amountInSmallestUnit - $penaltyAmount;
            
            if ($netWithdrawAmount <= 0) {
                throw new \Exception('Withdrawal amount too small after penalty');
            }
            
            $vault->decrement('balance', $amountInSmallestUnit);
            $vault->wallet->increaseBalance($netWithdrawAmount);
            $this->updateWithdrawableBalanceForWithdrawal($vault, $amountInSmallestUnit);
            
            $this->recordTransaction($vault, VaultTransaction::TYPE_WITHDRAWAL, $amountInSmallestUnit);
            
            if ($penaltyAmount > 0) {
                $this->recordTransaction($vault, VaultTransaction::TYPE_PENALTY, $penaltyAmount);
            }
            
            $this->updateVaultStatusAfterWithdrawal($vault);
            
            DB::commit();
            
            return [
                'withdrawn' => $netWithdrawAmount,
                'penalty' => $penaltyAmount,
                'total_deducted' => $amountInSmallestUnit,
                'currency' => $vault->wallet->currency->code,
                'currency_symbol' => $vault->wallet->currency->symbol,
            ];
            
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function getAvailableWithdrawableBalance(Vault $vault): int
    {
        if ($vault->type === 'flexible') return $vault->balance;
        if ($vault->isMatured()) return $vault->balance;
        return $vault->withdrawable_balance;
    }

    private function updateWithdrawableBalanceForWithdrawal(Vault $vault, int $amount): void
    {
        if ($vault->type === 'flexible') {
            $vault->decrement('withdrawable_balance', $amount);
        } elseif (!$vault->isMatured()) {
            $decrementAmount = min($amount, $vault->withdrawable_balance);
            if ($decrementAmount > 0) {
                $vault->decrement('withdrawable_balance', $decrementAmount);
            }
        }
    }

    private function calculateEarlyWithdrawalPenalty(Vault $vault, int $amount): int
    {
        if ($vault->type === 'flexible') return 0;
        if ($vault->isMatured()) return 0;
        return $vault->calculatePenalty($amount);
    }

    private function updateVaultStatusAfterWithdrawal(Vault $vault): void
    {
        $totalValue = $vault->balance + $vault->interest_earned;
        
        if ($totalValue === 0) {
            $vault->update(['status' => Vault::STATUS_CLOSED]);
        } elseif ($vault->isMatured() && $vault->status !== Vault::STATUS_MATURED) {
            $vault->update(['status' => Vault::STATUS_MATURED]);
        }
    }

    // ============================================================================
    // INTEREST CALCULATION
    // ============================================================================

    public function calculateInterest(Vault $vault): void
    {
        if ($vault->type === 'flexible' || $vault->balance === 0) return;
        if (in_array($vault->status, [Vault::STATUS_MATURED, Vault::STATUS_CLOSED])) return;
        
        $cacheKey = self::INTEREST_CACHE_KEY . $vault->id . '_' . now()->format('Y-m-d');
        if (Cache::has($cacheKey)) return;
        
        $balanceFloat = MoneyHelper::fromSmallestUnit($vault->balance, $vault->currency_code);
        $dailyRate = pow(1 + ($vault->interest_rate / 100), 1 / 365) - 1;
        $interestFloat = $balanceFloat * $dailyRate;
        $interest = MoneyHelper::toSmallestUnit($interestFloat, $vault->currency_code);
        
        if ($interest > 0) {
            DB::beginTransaction();
            
            try {
                $vault->increment('balance', $interest);
                $vault->increment('interest_earned', $interest);
                
                if ($vault->isLocked()) {
                    $vault->increment('withdrawable_balance', $interest);
                }
                
                $this->recordTransaction($vault, VaultTransaction::TYPE_INTEREST, $interest);
                Cache::put($cacheKey, true, self::DAILY_INTEREST_LIMIT);
                
                DB::commit();
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Interest calculation failed', ['vault_id' => $vault->id]);
            }
        }
    }

    // ============================================================================
    // MATURITY PROCESSING
    // ============================================================================

    public function processMaturedVaults(): int
    {
        $maturedVaults = Vault::where('type', '!=', 'flexible')
            ->where('status', Vault::STATUS_LOCKED)
            ->where('matures_at', '<=', now())
            ->get();
        
        $processed = 0;
        
        foreach ($maturedVaults as $vault) {
            DB::beginTransaction();
            
            try {
                $this->calculateInterest($vault);
                
                $vault->update([
                    'status' => Vault::STATUS_MATURED,
                    'withdrawable_balance' => $vault->balance,
                ]);
                
                $this->recordTransaction($vault, VaultTransaction::TYPE_MATURITY, 0);
                
                DB::commit();
                $processed++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Maturity processing failed', ['vault_id' => $vault->id]);
            }
        }
        
        return $processed;
    }

    // ============================================================================
    // TRANSACTION RECORDING
    // ============================================================================

    private function recordTransaction(Vault $vault, string $type, int $amount): void
    {
        VaultTransaction::create([
            'vault_id' => $vault->id,
            'user_id' => $vault->user_id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $vault->balance,
            'currency' => $vault->currency_code,
            'description' => $this->getTransactionDescription($type, $amount, $vault->currency_code),
        ]);
    }

    private function getTransactionDescription(string $type, int $amount, string $currencyCode): string
    {
        $formattedAmount = MoneyHelper::format($amount, $currencyCode);
        
        return match ($type) {
            VaultTransaction::TYPE_DEPOSIT => "Deposit to vault: {$formattedAmount}",
            VaultTransaction::TYPE_WITHDRAWAL => "Withdrawal from vault: {$formattedAmount}",
            VaultTransaction::TYPE_INTEREST => "Interest earned: {$formattedAmount}",
            VaultTransaction::TYPE_PENALTY => "Early withdrawal penalty: {$formattedAmount}",
            VaultTransaction::TYPE_TRANSFER_IN => "Transfer received: {$formattedAmount}",
            VaultTransaction::TYPE_TRANSFER_OUT => "Transfer sent: {$formattedAmount}",
            VaultTransaction::TYPE_MATURITY => "Vault matured - funds now available",
            default => "Vault transaction: {$formattedAmount}",
        };
    }

    // ============================================================================
    // USER VAULTS SUMMARY
    // ============================================================================

    public function getUserVaults(User $user): array
    {
        $vaults = Vault::with(['wallet.currency'])
            ->where('user_id', $user->id)
            ->where('status', '!=', Vault::STATUS_CLOSED)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $totalLockedValue = 0;
        $totalFlexibleValue = 0;
        $totalInterestEarned = 0;
        $totalValue = 0;
        
        foreach ($vaults as $vault) {
            $value = $vault->balance + $vault->interest_earned;
            
            if ($vault->isLocked()) {
                $totalLockedValue += $value;
            } else {
                $totalFlexibleValue += $value;
            }
            
            $totalInterestEarned += $vault->interest_earned;
            $totalValue += $value;
        }
        
        return [
            'vaults' => $vaults,
            'stats' => [
                'total_locked_value' => $totalLockedValue,
                'total_flexible_value' => $totalFlexibleValue,
                'total_interest_earned' => $totalInterestEarned,
                'total_value' => $totalValue,
                'total_vaults' => $vaults->count(),
                'active_vaults' => $vaults->where('status', Vault::STATUS_ACTIVE)->count(),
                'locked_vaults' => $vaults->where('status', Vault::STATUS_LOCKED)->count(),
                'matured_vaults' => $vaults->where('status', Vault::STATUS_MATURED)->count(),
            ],
        ];
    }

    public function getAvailableVaultTypes(): array
    {
        return Vault::TYPES;
    }
}