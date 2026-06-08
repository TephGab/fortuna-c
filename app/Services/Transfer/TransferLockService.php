<?php

namespace App\Services\Transfer;

use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class TransferLockService
{
    private const TRANSFER_LOCK_KEY = 'transfer_processing_';
    private const LOCK_DURATION = 30;

    /**
     * Acquire a lock for the user
     */
    public function acquire(User $user): Lock
    {
        $lockKey = self::TRANSFER_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
        if (!$lock->get()) {
            throw new \Exception('A transaction is already in progress. Please wait.');
        }
        
        return $lock;
    }

    /**
     * Release a lock
     */
    public function release(Lock $lock): void
    {
        $lock->release();
    }
}