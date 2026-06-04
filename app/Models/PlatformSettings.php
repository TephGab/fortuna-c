<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSettings extends Model
{
    protected $table = 'platform_settings';
    
    protected $fillable = [
        'key', 'value'
    ];

    protected $casts = [
        'value' => 'json',
    ];

    /**
     * Get a setting value by key with caching
     */
    public static function get(string $key, $default = null)
    {
        $cacheKey = "setting_{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default) {
            $setting = self::where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    /**
     * Set a setting value and clear cache
     */
    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        
        Cache::forget("setting_{$key}");
    }

    /**
     * Get base currency code
     */
    public static function getBaseCurrency(): string
    {
        return self::get('base_currency', 'DOP');
    }
}