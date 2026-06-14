<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ============================================================================
// VAULT SCHEDULED TASKS
// ============================================================================

/**
 * Process vault interest daily at 00:01 AM
 * Calculates daily interest for all locked vaults and processes matured vaults
 */
Schedule::command('vaults:process-interest')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/vault-interest.log'))
    ->onFailure(function () {
        \Log::error('Vault interest processing command failed');
    });

    // Optional: Run every hour for testing (comment out in production)
    // Schedule::command('vaults:process-interest')->hourly();

    // ============================================================================
    // TEST COMMANDS (Remove in production)
    // ============================================================================

    // Test command to verify schedule is working
    Schedule::call(function () {
        \Log::info('Schedule test ran at: ' . now());
    })->everyMinute();  // This will run every minute (for testing only)