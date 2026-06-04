<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            // Major World Currencies
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'symbol_native' => '€',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'symbol' => '£',
                'symbol_native' => '£',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'code' => 'JPY',
                'name' => 'Japanese Yen',
                'symbol' => '¥',
                'symbol_native' => '￥',
                'decimal_places' => 0,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'code' => 'CAD',
                'name' => 'Canadian Dollar',
                'symbol' => 'CA$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'code' => 'AUD',
                'name' => 'Australian Dollar',
                'symbol' => 'A$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'code' => 'CHF',
                'name' => 'Swiss Franc',
                'symbol' => 'CHF',
                'symbol_native' => 'CHF',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'code' => 'CNY',
                'name' => 'Chinese Yuan',
                'symbol' => 'CN¥',
                'symbol_native' => 'CN¥',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 8,
            ],
            
            // Latin American Currencies
            [
                'code' => 'BRL',
                'name' => 'Brazilian Real',
                'symbol' => 'R$',
                'symbol_native' => 'R$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'code' => 'MXN',
                'name' => 'Mexican Peso',
                'symbol' => 'MX$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 11,
            ],
            [
                'code' => 'ARS',
                'name' => 'Argentine Peso',
                'symbol' => 'AR$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 12,
            ],
            [
                'code' => 'CLP',
                'name' => 'Chilean Peso',
                'symbol' => 'CL$',
                'symbol_native' => '$',
                'decimal_places' => 0,
                'is_active' => true,
                'sort_order' => 13,
            ],
            [
                'code' => 'COP',
                'name' => 'Colombian Peso',
                'symbol' => 'CO$',
                'symbol_native' => '$',
                'decimal_places' => 0,
                'is_active' => true,
                'sort_order' => 14,
            ],
            [
                'code' => 'PEN',
                'name' => 'Peruvian Sol',
                'symbol' => 'S/',
                'symbol_native' => 'S/',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 15,
            ],
            
            // Dominican Republic Currency (Your Base Currency)
            [
                'code' => 'DOP',
                'name' => 'Dominican Peso',
                'symbol' => 'RD$',
                'symbol_native' => 'RD$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 16,
            ],
            
            [
                'code' => 'HTG',
                'name' => 'Haitian Gourde',
                'symbol' => 'G',
                'symbol_native' => 'G',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 17, // After DOP (16)
            ],
            
            // Other Important Currencies
            [
                'code' => 'INR',
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'symbol_native' => '₹',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'code' => 'KRW',
                'name' => 'South Korean Won',
                'symbol' => '₩',
                'symbol_native' => '₩',
                'decimal_places' => 0,
                'is_active' => true,
                'sort_order' => 21,
            ],
            [
                'code' => 'SGD',
                'name' => 'Singapore Dollar',
                'symbol' => 'S$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 22,
            ],
            [
                'code' => 'HKD',
                'name' => 'Hong Kong Dollar',
                'symbol' => 'HK$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 23,
            ],
            [
                'code' => 'NZD',
                'name' => 'New Zealand Dollar',
                'symbol' => 'NZ$',
                'symbol_native' => '$',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 24,
            ],
            
            // Middle East
            [
                'code' => 'AED',
                'name' => 'UAE Dirham',
                'symbol' => 'د.إ',
                'symbol_native' => 'د.إ',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'code' => 'SAR',
                'name' => 'Saudi Riyal',
                'symbol' => '﷼',
                'symbol_native' => '﷼',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 31,
            ],
            
            // African Currencies
            [
                'code' => 'ZAR',
                'name' => 'South African Rand',
                'symbol' => 'R',
                'symbol_native' => 'R',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 40,
            ],
            [
                'code' => 'NGN',
                'name' => 'Nigerian Naira',
                'symbol' => '₦',
                'symbol_native' => '₦',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 41,
            ],
            [
                'code' => 'KES',
                'name' => 'Kenyan Shilling',
                'symbol' => 'KSh',
                'symbol_native' => 'KSh',
                'decimal_places' => 2,
                'is_active' => true,
                'sort_order' => 42,
            ],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}