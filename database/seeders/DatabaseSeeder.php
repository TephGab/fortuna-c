<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\User;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\ExchangeRateSeeder;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            ExchangeRateSeeder::class,
            PlatformSettingsSeeder::class,
        ]);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
