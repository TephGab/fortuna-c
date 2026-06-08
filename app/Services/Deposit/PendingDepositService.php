<?php

namespace App\Services\Deposit;

use App\Models\User;
use App\Models\Transaction;

class PendingDepositService
{
    /**
     * Check if user has a pending deposit
     *
     * @throws \Exception
     */
    public function check(User $user): void
    {
        $pendingDeposit = Transaction::where('user_id', $user->id)
            ->where('type', 'deposit')
            ->where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(10))
            ->first();

        if ($pendingDeposit) {
            throw new \Exception('You already have a pending deposit. Please wait for it to complete.');
        }
    }
}