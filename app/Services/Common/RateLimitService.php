<?php

namespace App\Services\Common;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class RateLimitService
{
    private const RATE_LIMIT_KEY = 'deposit_rate_limit_';
    private const RATE_LIMIT_COOLDOWN = 20;

    public function check(User $user): void
    {
        $key = self::RATE_LIMIT_KEY . $user->id;
        
        if (Cache::has($key)) {
            throw new \Exception('Please wait a moment before trying again.');
        }
        
        Cache::put($key, true, self::RATE_LIMIT_COOLDOWN);
    }
}