<?php

use App\Http\Controllers\DepositController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Deposit routes - All deposit pages and API endpoints
    Route::prefix('deposits')->group(function () {
        // Page routes (Inertia views)
        Route::get('/', [DepositController::class, 'index'])->name('deposits.index');
        Route::get('/card', [DepositController::class, 'card'])->name('deposits.card');
        Route::get('/success', [DepositController::class, 'success'])->name('deposits.success');
        Route::get('/cancel', [DepositController::class, 'cancel'])->name('deposits.cancel');

        // API endpoint for creating Stripe checkout
        Route::post('/checkout', [DepositController::class, 'createCheckout'])->name('deposits.checkout');
    });
});

// Stripe webhook (no auth required - Stripe calls this)
Route::post('/stripe/webhook', [DepositController::class, 'handleWebhook']);

require __DIR__ . '/settings.php';
