<?php

namespace App\Services\Common;

use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class LockService
{
    private const PROCESSING_LOCK_KEY = 'deposit_processing_';
    private const LOCK_DURATION = 30;

    public function acquire(User $user): Lock
    {
        $lockKey = self::PROCESSING_LOCK_KEY . $user->id;
        $lock = Cache::lock($lockKey, self::LOCK_DURATION);
        
        if (!$lock->get()) {
            throw new \Exception('A transaction is already in progress. Please try again.');
        }
        
        return $lock;
    }

    public function release(Lock $lock): void
    {
        $lock->release();
    }
}