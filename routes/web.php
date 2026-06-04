<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepositController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    //Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    // Dashboard route (Inertia view)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Deposit page routes (Inertia views)
    Route::prefix('deposits')->group(function () {
        Route::get('/', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/card', [DepositController::class, 'card'])->name('deposits.card');
        Route::get('/bank-transfer', [DepositController::class, 'bankTransfer'])->name('deposits.bank-transfer');
        Route::get('/wire-transfer', [DepositController::class, 'wireTransfer'])->name('deposits.wire-transfer');
        Route::get('/success', [DepositController::class, 'success'])->name('deposits.success');
        Route::get('/cancel', [DepositController::class, 'cancel'])->name('deposits.cancel');
    });
});

// API routes (no Inertia, just JSON responses)
Route::middleware(['auth'])->prefix('api/deposits')->group(function () {
    Route::post('/create-payment-intent', [DepositController::class, 'createPaymentIntent']);
    Route::post('/confirm-payment', [DepositController::class, 'confirmPayment']);
});

// Stripe webhook (no auth required - Stripe calls this)
Route::post('/stripe/webhook', [DepositController::class, 'handleWebhook']);

require __DIR__ . '/settings.php';
