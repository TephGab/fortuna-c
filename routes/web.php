<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\VaultController; // ADD THIS
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

// Guest language switch (no login required)
Route::get('/locale/{locale}', [LocaleController::class, 'setGuestLocale'])->name('locale.set');

Route::middleware(['auth', 'verified'])->group(function () {
    // Authenticated language switch
    Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

    // Dashboard route (Inertia view)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ============================================================================
    // VAULT ROUTES
    // ============================================================================


    // Inside your auth middleware group, add:
    Route::prefix('vaults')->name('vaults.')->group(function () {
        // Page routes
        Route::get('/', [VaultController::class, 'index'])->name('index');
        Route::get('/{vault}', [VaultController::class, 'show'])->name('show');

        // CRUD operations
        Route::post('/', [VaultController::class, 'store'])->name('store');
        Route::put('/{vault}', [VaultController::class, 'update'])->name('update');
        Route::delete('/{vault}', [VaultController::class, 'destroy'])->name('destroy');

        // Financial operations
        Route::post('/{vault}/deposit', [VaultController::class, 'deposit'])->name('deposit');
        Route::post('/{vault}/withdraw', [VaultController::class, 'withdraw'])->name('withdraw');

        // API/JSON endpoints
        Route::post('/preview-interest', [VaultController::class, 'previewInterest'])->name('preview.interest');
        Route::post('/{vault}/preview-withdrawal', [VaultController::class, 'previewWithdrawal'])->name('preview.withdrawal');
        Route::get('/{vault}/check-withdrawal', [VaultController::class, 'checkWithdrawal'])->name('check.withdrawal');
    });

    // ============================================================================
    // DEPOSIT ROUTES
    // ============================================================================

    Route::prefix('deposits')->group(function () {
        Route::get('/', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/card', [DepositController::class, 'card'])->name('deposits.card');
        Route::get('/bank-transfer', [DepositController::class, 'bankTransfer'])->name('deposits.bank-transfer');
        Route::get('/wire-transfer', [DepositController::class, 'wireTransfer'])->name('deposits.wire-transfer');
        Route::get('/success', [DepositController::class, 'success'])->name('deposits.success');
        Route::get('/cancel', [DepositController::class, 'cancel'])->name('deposits.cancel');
        Route::get('/paypal', [DepositController::class, 'paypal'])->name('paypal');
        Route::get('/authorized-agent', [DepositController::class, 'authorizedAgent'])->name('authorized-agent');

        // PayPal API endpoints (must be under the same prefix)
        Route::post('/paypal/create-order', [DepositController::class, 'createPayPalOrder'])->name('deposits.paypal.create-order');
        Route::get('/paypal/success', [DepositController::class, 'handlePayPalSuccess'])->name('deposits.paypal.success');
        Route::get('/paypal/cancel', [DepositController::class, 'handlePayPalCancel'])->name('deposits.paypal.cancel');
    });

    // ============================================================================
    // TRANSFER ROUTES
    // ============================================================================

    Route::prefix('transfers')->group(function () {
        Route::get('/', [TransferController::class, 'index'])->name('transfers.index');
        Route::post('/get-recipient', [TransferController::class, 'getRecipient']);
        Route::post('/calculate', [TransferController::class, 'calculateTransfer']);
        Route::post('/create-quote', [TransferController::class, 'createQuote']);
        Route::post('/execute', [TransferController::class, 'executeTransfer']);
        Route::get('/success', [TransferController::class, 'success'])->name('transfers.success');
    });

    // ============================================================================
    // WALLET ROUTES
    // ============================================================================

    // Add new currency wallet
    Route::post('/wallets/add-currency', [WalletController::class, 'addCurrency'])->name('wallets.add-currency');
    Route::get('/wallets/available-currencies', [WalletController::class, 'availableCurrencies'])->name('wallets.available-currencies');
});

// ============================================================================
// API ROUTES (JSON responses, no Inertia)
// ============================================================================

Route::middleware(['auth'])->prefix('api')->group(function () {
    // Deposit API endpoints
    Route::prefix('deposits')->group(function () {
        Route::post('/create-payment-intent', [DepositController::class, 'createPaymentIntent']);
        Route::post('/confirm-payment', [DepositController::class, 'confirmPayment']);
    });

    // Vault API endpoints (if you need additional JSON endpoints)
    Route::prefix('vaults')->group(function () {
        // Add any additional API endpoints here if needed
    });
});

// ============================================================================
// WEBHOOK ROUTES (no auth required - external services call these)
// ============================================================================

// Stripe webhook (no auth required - Stripe calls this)
Route::post('/stripe/webhook', [DepositController::class, 'handleWebhook']);

require __DIR__ . '/settings.php';



// use App\Http\Controllers\DashboardController;
// use App\Http\Controllers\DepositController;
// use App\Http\Controllers\LocaleController;
// use App\Http\Controllers\TransferController;
// use App\Http\Controllers\WalletController;
// use Illuminate\Support\Facades\Route;

// Route::inertia('/', 'Welcome')->name('home');

// // Guest language switch (no login required)
// Route::get('/locale/{locale}', [LocaleController::class, 'setGuestLocale'])->name('locale.set');

// Route::middleware(['auth', 'verified'])->group(function () {
//     // Authenticated language switch
//     Route::post('/locale/switch', [LocaleController::class, 'switch'])->name('locale.switch');

//     //Route::inertia('dashboard', 'Dashboard')->name('dashboard');
//     // Dashboard route (Inertia view)
//     Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

//     // Deposit page routes (Inertia views)
//     Route::prefix('deposits')->group(function () {
//         Route::get('/', [DepositController::class, 'index'])->name('deposits.index');
//         Route::get('/card', [DepositController::class, 'card'])->name('deposits.card');
//         Route::get('/bank-transfer', [DepositController::class, 'bankTransfer'])->name('deposits.bank-transfer');
//         Route::get('/wire-transfer', [DepositController::class, 'wireTransfer'])->name('deposits.wire-transfer');
//         Route::get('/success', [DepositController::class, 'success'])->name('deposits.success');
//         Route::get('/cancel', [DepositController::class, 'cancel'])->name('deposits.cancel');
//         Route::get('/paypal', [DepositController::class, 'paypal'])->name('paypal');
//         Route::get('/authorized-agent', [DepositController::class, 'authorizedAgent'])->name('authorized-agent');

//         // PayPal API endpoints (must be under the same prefix)
//         Route::post('/paypal/create-order', [DepositController::class, 'createPayPalOrder'])->name('deposits.paypal.create-order');
//         Route::get('/paypal/success', [DepositController::class, 'handlePayPalSuccess'])->name('deposits.paypal.success');
//         Route::get('/paypal/cancel', [DepositController::class, 'handlePayPalCancel'])->name('deposits.paypal.cancel');


//     });

//     // Transfer routes
//     Route::prefix('transfers')->group(function () {
//         Route::get('/', [TransferController::class, 'index'])->name('transfers.index');
//         Route::post('/get-recipient', [TransferController::class, 'getRecipient']);
//         Route::post('/calculate', [TransferController::class, 'calculateTransfer']);
//         Route::post('/create-quote', [TransferController::class, 'createQuote']);
//         Route::post('/execute', [TransferController::class, 'executeTransfer']);
//         Route::get('/success', [TransferController::class, 'success'])->name('transfers.success');
//     });

//     // Add new currency wallet
//     Route::post('/wallets/add-currency', [WalletController::class, 'addCurrency'])->name('wallets.add-currency');
//     Route::get('/wallets/available-currencies', [WalletController::class, 'availableCurrencies'])->name('wallets.available-currencies');
// });

// // API routes (no Inertia, just JSON responses)
// Route::middleware(['auth'])->prefix('api/deposits')->group(function () {
//     Route::post('/create-payment-intent', [DepositController::class, 'createPaymentIntent']);
//     Route::post('/confirm-payment', [DepositController::class, 'confirmPayment']);
// });

// // Stripe webhook (no auth required - Stripe calls this)
// Route::post('/stripe/webhook', [DepositController::class, 'handleWebhook']);

// require __DIR__ . '/settings.php';
