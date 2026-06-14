<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Vault;
use App\Models\Currency;
use App\Services\Vault\VaultService;
use App\Helpers\MoneyHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; // ← ADD THIS

class VaultController extends Controller
{
    use AuthorizesRequests; // ← ADD THIS
    
    protected VaultService $vaultService;

    public function __construct(VaultService $vaultService)
    {
        $this->vaultService = $vaultService;
    }

    // ============================================================================
    // PAGE RENDERING
    // ============================================================================

    public function index()
    {
        $user = auth()->user();
        $vaultsData = $this->vaultService->getUserVaults($user);
        
        // Get user's preferred currency from default wallet
        $defaultWallet = $user->wallets()
            ->where('is_default', true)
            ->with('currency')
            ->first();
        
        $userCurrency = $defaultWallet && $defaultWallet->currency 
            ? $defaultWallet->currency->code 
            : 'USD';
        
        // Format stats with user's preferred currency
        $vaultsData['stats']['formatted_total_value'] = MoneyHelper::format(
            $vaultsData['stats']['total_value'], 
            $userCurrency
        );
        $vaultsData['stats']['formatted_total_interest'] = MoneyHelper::format(
            $vaultsData['stats']['total_interest_earned'], 
            $userCurrency
        );
        
        // Get user's wallets
        $wallets = $user->wallets()->with('currency')->get()->map(function ($wallet) {
            return [
                'id' => $wallet->id,
                'currency_code' => $wallet->currency->code,
                'currency_symbol' => $wallet->currency->symbol,
                'formatted_balance' => MoneyHelper::format($wallet->balance, $wallet->currency->code),
                'balance_float' => MoneyHelper::fromSmallestUnit($wallet->balance, $wallet->currency->code),
                'balance_raw' => $wallet->balance,
                'is_default' => $wallet->is_default,
            ];
        });
        
        $availableTypes = $this->vaultService->getAvailableVaultTypes();
        
        return Inertia::render('Vaults/Index', [
            'vaults' => $vaultsData['vaults'],
            'stats' => $vaultsData['stats'],
            'wallets' => $wallets,
            'availableTypes' => $availableTypes,
        ]);
    }

    public function show(Vault $vault)
    {
        $this->authorize('view', $vault);
        $user = auth()->user();
        
        $vault->load(['wallet.currency', 'transactions' => function($q) {
            $q->latest()->limit(50);
        }]);
        
        $this->vaultService->calculateInterest($vault);
        $vault->refresh();
        
        return Inertia::render('Vaults/Show', [
            'vault' => [
                'id' => $vault->id,
                'name' => $vault->name,
                'icon' => $vault->icon,
                'type' => $vault->type,
                'status' => $vault->status,
                'description' => $vault->description,
                'balance' => $vault->balance,  // Raw integer in cents (e.g., 3500)
                'formatted_balance' => $vault->formatted_balance,  // Formatted string (e.g., "$ 35.00")
                'formatted_interest_earned' => $vault->formatted_interest_earned,
                'formatted_total_value' => $vault->formatted_total_value,
                'interest_rate' => $vault->interest_rate,
                'locked_until' => $vault->locked_until,
                'matures_at' => $vault->matures_at,
                'type_config' => $vault->type_config,
                'type_color_class' => $vault->type_color_class,
                'progress_bar_color_class' => $vault->progress_bar_color_class,
                'status_badge' => $vault->status_badge,
                'days_remaining' => $vault->days_remaining,
                'days_remaining_text' => $vault->days_remaining_text,
                'progress_percentage' => $vault->progress_percentage,
                'is_locked' => $vault->isLocked(),
                'is_matured' => $vault->isMatured(),
                'can_early_withdraw' => $vault->canEarlyWithdraw(),
            ],
            'transactions' => $vault->transactions->map(function ($transaction) {
                return [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'type_name' => $transaction->type_name,
                    'formatted_amount' => $transaction->formatted_amount,
                    'formatted_amount_with_sign' => $transaction->formatted_amount_with_sign,
                    'formatted_balance_after' => $transaction->formatted_balance_after,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at,
                    'icon' => $transaction->icon,
                    'color_class' => $transaction->color_class,
                    'bg_color_class' => $transaction->bg_color_class,
                ];
            }),
            'wallets' => $user->wallets()->with('currency')->get()->map(function ($wallet) {
                return [
                    'id' => $wallet->id,
                    'currency_code' => $wallet->currency->code,
                    'currency_symbol' => $wallet->currency->symbol,
                    'formatted_balance' => MoneyHelper::format($wallet->balance, $wallet->currency->code),
                    'balance_float' => MoneyHelper::fromSmallestUnit($wallet->balance, $wallet->currency->code),
                    'is_default' => $wallet->is_default,
                ];
            }),
        ]);
    }

    // ============================================================================
    // CRUD OPERATIONS
    // ============================================================================

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|min:3',
            'type' => ['required', Rule::in(array_keys(Vault::TYPES))],
            'wallet_id' => 'required|exists:wallets,id',
            'initial_deposit' => 'nullable|numeric|min:0|max:100000',
            'description' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $wallet = $user->wallets()->with('currency')->findOrFail($request->wallet_id);
        $initialDeposit = $request->initial_deposit ?? 0;
        
        if ($initialDeposit > 0) {
            $initialDepositCents = MoneyHelper::toSmallestUnit($initialDeposit, $wallet->currency->code);
            
            if ($wallet->balance < $initialDepositCents) {
                return back()->with('error', 'Insufficient balance for initial deposit');
            }
        }
        
        try {
            DB::beginTransaction();
            
            $vault = $this->vaultService->createVault(
                $user,
                $wallet,
                $request->name,
                $request->type,
                MoneyHelper::toSmallestUnit($initialDeposit, $wallet->currency->code),
                $request->description
            );
            
            DB::commit();
            
            return redirect()->route('vaults.show', $vault)
                ->with('success', 'Vault created successfully!');
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vault creation failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Failed to create vault. ' . $e->getMessage());
        }
    }

    public function update(Request $request, Vault $vault)
    {
        $this->authorize('update', $vault);
        
        $request->validate([
            'name' => 'required|string|max:100|min:3',
            'description' => 'nullable|string|max:500',
        ]);
        
        $vault->update([
            'name' => $request->name,
            'description' => $request->description,
        ]);
        
        return redirect()->back()->with('success', 'Vault updated successfully');
    }

    public function destroy(Vault $vault)
    {
        $this->authorize('delete', $vault);
        
        $totalBalance = $vault->balance + $vault->interest_earned;
        
        if ($totalBalance > 0) {
            return back()->with('error', 'Cannot close vault with remaining balance. Please withdraw all funds first.');
        }
        
        $vault->update(['status' => Vault::STATUS_CLOSED]);
        
        return redirect()->route('vaults.index')->with('success', 'Vault closed successfully');
    }

    // ============================================================================
    // FINANCIAL OPERATIONS
    // ============================================================================

    public function deposit(Request $request, Vault $vault)
    {
        $this->authorize('update', $vault);
        
        $request->validate([
            'amount' => 'required|numeric|min:1|max:50000',
            'wallet_id' => 'required|exists:wallets,id',
        ]);
        
        $user = $request->user();
        $wallet = $user->wallets()->with('currency')->findOrFail($request->wallet_id);
        
        if ($wallet->currency_code !== $vault->wallet->currency_code) {
            return back()->with('error', 'Wallet currency must match vault currency');
        }
        
        $amountCents = MoneyHelper::toSmallestUnit($request->amount, $wallet->currency->code);
        
        if ($wallet->balance < $amountCents) {
            return back()->with('error', 'Insufficient balance in wallet');
        }
        
        try {
            DB::beginTransaction();
            $this->vaultService->deposit($vault, $amountCents, $wallet);
            DB::commit();
            
            return redirect()->route('vaults.show', $vault)->with('success', 'Deposit successful!');
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vault deposit failed', ['vault_id' => $vault->id, 'error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function withdraw(Request $request, Vault $vault)
    {
        $this->authorize('update', $vault);
        
        $maxWithdraw = $vault->getBalanceFloatAttribute();
        
        $request->validate([
            'amount' => [
                'required', 
                'numeric', 
                'min:1',
                function ($attribute, $value, $fail) use ($maxWithdraw) {
                    if ($value > $maxWithdraw) {
                        $fail("Amount exceeds vault balance of {$maxWithdraw}");
                    }
                },
            ],
        ]);
        
        $amountCents = MoneyHelper::toSmallestUnit($request->amount, $vault->wallet->currency->code);
        
        try {
            DB::beginTransaction();
            $result = $this->vaultService->withdraw($vault, $amountCents);
            DB::commit();
            
            $message = "Withdrawal successful! ";
            if ($result['penalty'] > 0) {
                $penaltyFormatted = MoneyHelper::format($result['penalty'], $result['currency']);
                $message .= "Early withdrawal penalty of {$penaltyFormatted} was applied.";
            }
            
            return redirect()->route('vaults.show', $vault)->with('success', $message);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Vault withdrawal failed', ['vault_id' => $vault->id, 'error' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    // ============================================================================
    // API/JSON ENDPOINTS
    // ============================================================================
    /**
 * Preview interest for a vault type
 * 
 * @param Request $request
 * @return \Illuminate\Http\JsonResponse
 */
public function previewInterest(Request $request)
{
    $request->validate([
        'type' => ['required', Rule::in(array_keys(Vault::TYPES))],
        'amount' => 'required|numeric|min:1',
        'wallet_id' => 'sometimes|exists:wallets,id', // Optional: use selected wallet's currency
    ]);
    
    $user = auth()->user();
    $typeConfig = Vault::TYPES[$request->type];
    $interestRate = $typeConfig['interest_rate'];
    
    // Calculate projected interest in dollars
    $projectedInterest = ($request->amount * $interestRate) / 100;
    
    // Get currency from selected wallet or default wallet
    if ($request->has('wallet_id')) {
        $wallet = $user->wallets()->with('currency')->find($request->wallet_id);
        $currencyCode = $wallet ? $wallet->currency->code : 'USD';
        $currencySymbol = $wallet ? $wallet->currency->symbol : '$';
    } else {
        $defaultWallet = $user->wallets()->where('is_default', true)->with('currency')->first();
        $currencyCode = $defaultWallet ? $defaultWallet->currency->code : 'USD';
        $currencySymbol = $defaultWallet ? $defaultWallet->currency->symbol : '$';
    }
    
    // Convert projected interest to smallest unit (cents) for MoneyHelper
    $projectedInterestInCents = (int) round($projectedInterest * 100);
    
    return response()->json([
        'interest_rate' => $interestRate,
        'projected_interest' => round($projectedInterest, 2),
        'lock_days' => $typeConfig['lock_days'],
        'penalty' => $typeConfig['penalty'],
        'formatted_interest' => MoneyHelper::format($projectedInterestInCents, $currencyCode),
        'currency_symbol' => $currencySymbol,
    ]);
}
// /**
//  * Preview interest for a vault type
//  * 
//  * @param Request $request
//  * @return \Illuminate\Http\JsonResponse
//  */
// public function previewInterest(Request $request)
// {
//     $request->validate([
//         'type' => ['required', Rule::in(array_keys(Vault::TYPES))],
//         'amount' => 'required|numeric|min:1',
//     ]);
    
//     $user = auth()->user();
//     $typeConfig = Vault::TYPES[$request->type];
//     $interestRate = $typeConfig['interest_rate'];
    
//     // Calculate projected interest in dollars
//     $projectedInterest = ($request->amount * $interestRate) / 100;
    
//     // Get the user's preferred currency from their default wallet
//     $defaultWallet = $user->wallets()->where('is_default', true)->with('currency')->first();
//     $currencyCode = $defaultWallet ? $defaultWallet->currency->code : 'USD';
//     $currencySymbol = $defaultWallet ? $defaultWallet->currency->symbol : '$';
    
//     // Convert projected interest to smallest unit (cents) for MoneyHelper
//     $projectedInterestInCents = (int) round($projectedInterest * 100);
    
//     return response()->json([
//         'interest_rate' => $interestRate,
//         'projected_interest' => round($projectedInterest, 2),
//         'lock_days' => $typeConfig['lock_days'],
//         'penalty' => $typeConfig['penalty'],
//         'formatted_interest' => MoneyHelper::format($projectedInterestInCents, $currencyCode),
//         'currency_symbol' => $currencySymbol,
//     ]);
// }

    public function previewWithdrawal(Request $request, Vault $vault)
    {
        $this->authorize('update', $vault);
        
        $request->validate(['amount' => 'required|numeric|min:1']);
        
        $amountFloat = $request->amount;
        $amountCents = MoneyHelper::toSmallestUnit($amountFloat, $vault->wallet->currency->code);
        $penaltyCents = $vault->calculatePenalty($amountCents);
        $netAmountCents = $amountCents - $penaltyCents;
        
        return response()->json([
            'amount' => $amountFloat,
            'formatted_amount' => MoneyHelper::format($amountCents, $vault->wallet->currency->code),
            'penalty' => MoneyHelper::fromSmallestUnit($penaltyCents, $vault->wallet->currency->code),
            'formatted_penalty' => MoneyHelper::format($penaltyCents, $vault->wallet->currency->code),
            'net_amount' => MoneyHelper::fromSmallestUnit($netAmountCents, $vault->wallet->currency->code),
            'formatted_net' => MoneyHelper::format($netAmountCents, $vault->wallet->currency->code),
            'has_penalty' => $penaltyCents > 0,
            'currency_symbol' => $vault->wallet->currency->symbol,
        ]);
    }

    public function checkWithdrawal(Vault $vault)
    {
        $this->authorize('view', $vault);
        
        return response()->json([
            'can_withdraw' => !$vault->isLocked() || $vault->canEarlyWithdraw(),
            'is_locked' => $vault->isLocked(),
            'days_remaining' => $vault->getDaysRemaining(),
            'penalty_percentage' => $vault->isLocked() ? Vault::TYPES[$vault->type]['penalty'] : 0,
            'max_withdraw_float' => $vault->getBalanceFloatAttribute(),
            'max_withdraw_formatted' => $vault->formatted_balance,
        ]);
    }
}