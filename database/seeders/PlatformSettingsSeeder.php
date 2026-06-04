<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PlatformSettings;

class PlatformSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Base Currency (Dominican Peso)
            [
                'key' => 'base_currency',
                'value' => 'DOP',
            ],
            
            // Platform Name
            [
                'key' => 'platform_name',
                'value' => 'Wise Clone',
            ],
            
            // Fee Settings
            [
                'key' => 'deposit_fee_percentage',
                'value' => '2.9',
            ],
            [
                'key' => 'transfer_fee_percentage',
                'value' => '0.5',
            ],
            [
                'key' => 'transfer_fee_minimum',
                'value' => '50', // In smallest unit (DOP cents)
            ],
            [
                'key' => 'exchange_fee_percentage',
                'value' => '0.3',
            ],
            
            // Limit Settings
            [
                'key' => 'min_deposit_amount',
                'value' => '1000', // 10.00 DOP
            ],
            [
                'key' => 'max_deposit_amount',
                'value' => '500000', // 5,000.00 DOP
            ],
            [
                'key' => 'min_transfer_amount',
                'value' => '500', // 5.00 DOP
            ],
            [
                'key' => 'max_transfer_amount',
                'value' => '5000000', // 50,000.00 DOP
            ],
            
            // Email Settings
            [
                'key' => 'support_email',
                'value' => 'support@wiseclone.com',
            ],
            [
                'key' => 'noreply_email',
                'value' => 'noreply@wiseclone.com',
            ],
            
            // Social Links
            [
                'key' => 'twitter_url',
                'value' => 'https://twitter.com/wiseclone',
            ],
            [
                'key' => 'facebook_url',
                'value' => 'https://facebook.com/wiseclone',
            ],
            [
                'key' => 'instagram_url',
                'value' => 'https://instagram.com/wiseclone',
            ],
            
            // Maintenance Mode
            [
                'key' => 'maintenance_mode',
                'value' => 'false',
            ],
            
            // Timezone
            [
                'key' => 'default_timezone',
                'value' => 'America/Santo_Domingo',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSettings::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}