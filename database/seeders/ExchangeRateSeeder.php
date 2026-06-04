<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ExchangeRate;
use App\Models\Currency;

class ExchangeRateSeeder extends Seeder
{
    public function run(): void
    {
        // Get currency IDs by code
        $currencies = Currency::all()->keyBy('code');
        
        // Exchange rates (1 base = X target)
        $rates = [
            // ==================== USD AS BASE ====================
            // Major World Currencies
            ['base' => 'USD', 'target' => 'EUR', 'rate' => 0.920000],
            ['base' => 'USD', 'target' => 'GBP', 'rate' => 0.790000],
            ['base' => 'USD', 'target' => 'JPY', 'rate' => 148.500000],
            ['base' => 'USD', 'target' => 'CAD', 'rate' => 1.350000],
            ['base' => 'USD', 'target' => 'AUD', 'rate' => 1.520000],
            ['base' => 'USD', 'target' => 'CHF', 'rate' => 0.890000],
            ['base' => 'USD', 'target' => 'CNY', 'rate' => 7.200000],
            ['base' => 'USD', 'target' => 'NZD', 'rate' => 1.630000],
            ['base' => 'USD', 'target' => 'SGD', 'rate' => 1.340000],
            ['base' => 'USD', 'target' => 'HKD', 'rate' => 7.820000],
            ['base' => 'USD', 'target' => 'KRW', 'rate' => 1330.000000],
            ['base' => 'USD', 'target' => 'INR', 'rate' => 83.500000],
            
            // European Currencies
            ['base' => 'USD', 'target' => 'SEK', 'rate' => 10.500000], // Swedish Krona
            ['base' => 'USD', 'target' => 'NOK', 'rate' => 10.800000], // Norwegian Krone
            ['base' => 'USD', 'target' => 'DKK', 'rate' => 6.900000],  // Danish Krone
            ['base' => 'USD', 'target' => 'PLN', 'rate' => 4.000000],  // Polish Zloty
            ['base' => 'USD', 'target' => 'CZK', 'rate' => 23.000000], // Czech Koruna
            ['base' => 'USD', 'target' => 'HUF', 'rate' => 360.000000], // Hungarian Forint
            ['base' => 'USD', 'target' => 'RON', 'rate' => 4.600000],  // Romanian Leu
            ['base' => 'USD', 'target' => 'BGN', 'rate' => 1.800000],  // Bulgarian Lev
            
            // Latin American Currencies
            ['base' => 'USD', 'target' => 'BRL', 'rate' => 5.100000],   // Brazilian Real
            ['base' => 'USD', 'target' => 'MXN', 'rate' => 17.200000],  // Mexican Peso
            ['base' => 'USD', 'target' => 'ARG', 'rate' => 850.000000],  // Argentine Peso
            ['base' => 'USD', 'target' => 'CLP', 'rate' => 900.000000],  // Chilean Peso
            ['base' => 'USD', 'target' => 'COP', 'rate' => 4000.000000], // Colombian Peso
            ['base' => 'USD', 'target' => 'PEN', 'rate' => 3.700000],    // Peruvian Sol
            ['base' => 'USD', 'target' => 'UYU', 'rate' => 38.500000],   // Uruguayan Peso
            ['base' => 'USD', 'target' => 'PYG', 'rate' => 7300.000000], // Paraguayan Guarani
            ['base' => 'USD', 'target' => 'BOB', 'rate' => 6.900000],    // Bolivian Boliviano
            ['base' => 'USD', 'target' => 'CRC', 'rate' => 520.000000],  // Costa Rican Colón
            ['base' => 'USD', 'target' => 'GTQ', 'rate' => 7.800000],    // Guatemalan Quetzal
            ['base' => 'USD', 'target' => 'HNL', 'rate' => 24.700000],   // Honduran Lempira
            ['base' => 'USD', 'target' => 'NIO', 'rate' => 36.500000],   // Nicaraguan Córdoba
            ['base' => 'USD', 'target' => 'PAB', 'rate' => 1.000000],    // Panamanian Balboa (1:1 with USD)
            
            // Caribbean Currencies (Includes Haitian Gourde)
            ['base' => 'USD', 'target' => 'DOP', 'rate' => 59.000000],   // Dominican Peso
            ['base' => 'USD', 'target' => 'HTG', 'rate' => 132.000000],  // Haitian Gourde
            ['base' => 'USD', 'target' => 'CUP', 'rate' => 24.000000],   // Cuban Peso
            ['base' => 'USD', 'target' => 'JMD', 'rate' => 155.000000],  // Jamaican Dollar
            ['base' => 'USD', 'target' => 'TTD', 'rate' => 6.800000],    // Trinidad and Tobago Dollar
            ['base' => 'USD', 'target' => 'BSD', 'rate' => 1.000000],    // Bahamian Dollar (1:1 with USD)
            ['base' => 'USD', 'target' => 'BBD', 'rate' => 2.000000],    // Barbadian Dollar
            ['base' => 'USD', 'target' => 'XCD', 'rate' => 2.700000],    // East Caribbean Dollar
            ['base' => 'USD', 'target' => 'KYD', 'rate' => 0.820000],    // Cayman Islands Dollar
            ['base' => 'USD', 'target' => 'AWG', 'rate' => 1.790000],    // Aruban Florin
            ['base' => 'USD', 'target' => 'ANG', 'rate' => 1.790000],    // Netherlands Antillean Guilder
            
            // Middle Eastern Currencies
            ['base' => 'USD', 'target' => 'AED', 'rate' => 3.670000],    // UAE Dirham
            ['base' => 'USD', 'target' => 'SAR', 'rate' => 3.750000],    // Saudi Riyal
            ['base' => 'USD', 'target' => 'QAR', 'rate' => 3.640000],    // Qatari Riyal
            ['base' => 'USD', 'target' => 'KWD', 'rate' => 0.307000],    // Kuwaiti Dinar
            ['base' => 'USD', 'target' => 'BHD', 'rate' => 0.376000],    // Bahraini Dinar
            ['base' => 'USD', 'target' => 'OMR', 'rate' => 0.385000],    // Omani Rial
            ['base' => 'USD', 'target' => 'JOD', 'rate' => 0.709000],    // Jordanian Dinar
            ['base' => 'USD', 'target' => 'EGP', 'rate' => 48.500000],   // Egyptian Pound
            
            // African Currencies
            ['base' => 'USD', 'target' => 'ZAR', 'rate' => 18.500000],   // South African Rand
            ['base' => 'USD', 'target' => 'NGN', 'rate' => 1500.000000], // Nigerian Naira
            ['base' => 'USD', 'target' => 'KES', 'rate' => 150.000000],  // Kenyan Shilling
            ['base' => 'USD', 'target' => 'GHS', 'rate' => 12.500000],   // Ghanaian Cedi
            ['base' => 'USD', 'target' => 'TZS', 'rate' => 2500.000000], // Tanzanian Shilling
            ['base' => 'USD', 'target' => 'UGX', 'rate' => 3800.000000], // Ugandan Shilling
            ['base' => 'USD', 'target' => 'RWF', 'rate' => 1250.000000], // Rwandan Franc
            ['base' => 'USD', 'target' => 'MAD', 'rate' => 10.000000],   // Moroccan Dirham
            ['base' => 'USD', 'target' => 'TND', 'rate' => 3.100000],    // Tunisian Dinar
            ['base' => 'USD', 'target' => 'DZD', 'rate' => 135.000000],  // Algerian Dinar
            ['base' => 'USD', 'target' => 'XAF', 'rate' => 600.000000],  // Central African CFA
            ['base' => 'USD', 'target' => 'XOF', 'rate' => 600.000000],  // West African CFA
            
            // Asian Currencies
            ['base' => 'USD', 'target' => 'THB', 'rate' => 36.500000],   // Thai Baht
            ['base' => 'USD', 'target' => 'VND', 'rate' => 24500.000000], // Vietnamese Dong
            ['base' => 'USD', 'target' => 'IDR', 'rate' => 15600.000000], // Indonesian Rupiah
            ['base' => 'USD', 'target' => 'MYR', 'rate' => 4.700000],    // Malaysian Ringgit
            ['base' => 'USD', 'target' => 'PHP', 'rate' => 56.500000],   // Philippine Peso
            ['base' => 'USD', 'target' => 'PKR', 'rate' => 278.000000],  // Pakistani Rupee
            ['base' => 'USD', 'target' => 'BDT', 'rate' => 110.000000],  // Bangladeshi Taka
            ['base' => 'USD', 'target' => 'LKR', 'rate' => 300.000000],  // Sri Lankan Rupee
            ['base' => 'USD', 'target' => 'NPR', 'rate' => 133.000000],  // Nepalese Rupee
            ['base' => 'USD', 'target' => 'MMK', 'rate' => 2100.000000], // Myanmar Kyat
            ['base' => 'USD', 'target' => 'KHR', 'rate' => 4100.000000], // Cambodian Riel
            ['base' => 'USD', 'target' => 'LAK', 'rate' => 20500.000000], // Lao Kip
            ['base' => 'USD', 'target' => 'MNT', 'rate' => 3450.000000], // Mongolian Tugrik
            
            // Oceanian Currencies
            ['base' => 'USD', 'target' => 'FJD', 'rate' => 2.250000],    // Fijian Dollar
            ['base' => 'USD', 'target' => 'PGK', 'rate' => 3.750000],    // Papua New Guinean Kina
            ['base' => 'USD', 'target' => 'SBD', 'rate' => 8.300000],    // Solomon Islands Dollar
            ['base' => 'USD', 'target' => 'TOP', 'rate' => 2.350000],    // Tongan Paʻanga
            ['base' => 'USD', 'target' => 'VUV', 'rate' => 120.000000],  // Vanuatu Vatu
            ['base' => 'USD', 'target' => 'WST', 'rate' => 2.700000],    // Samoan Tālā
            ['base' => 'USD', 'target' => 'KMF', 'rate' => 450.000000],  // Comorian Franc
            
            // ==================== EUR AS BASE ====================
            ['base' => 'EUR', 'target' => 'USD', 'rate' => 1.086957],
            ['base' => 'EUR', 'target' => 'GBP', 'rate' => 0.858696],
            ['base' => 'EUR', 'target' => 'JPY', 'rate' => 161.413043],
            ['base' => 'EUR', 'target' => 'CAD', 'rate' => 1.467391],
            ['base' => 'EUR', 'target' => 'CHF', 'rate' => 0.967391],
            ['base' => 'EUR', 'target' => 'DOP', 'rate' => 64.130435],
            ['base' => 'EUR', 'target' => 'HTG', 'rate' => 143.478261], // Haitian Gourde
            ['base' => 'EUR', 'target' => 'BRL', 'rate' => 5.543478],
            
            // ==================== GBP AS BASE ====================
            ['base' => 'GBP', 'target' => 'USD', 'rate' => 1.265823],
            ['base' => 'GBP', 'target' => 'EUR', 'rate' => 1.164557],
            ['base' => 'GBP', 'target' => 'JPY', 'rate' => 187.911392],
            ['base' => 'GBP', 'target' => 'CAD', 'rate' => 1.708861],
            ['base' => 'GBP', 'target' => 'DOP', 'rate' => 74.683418],
            ['base' => 'GBP', 'target' => 'HTG', 'rate' => 167.037975], // Haitian Gourde
            ['base' => 'GBP', 'target' => 'BRL', 'rate' => 6.455696],
            
            // ==================== DOP AS BASE (Dominican Peso) ====================
            ['base' => 'DOP', 'target' => 'USD', 'rate' => 0.016949],
            ['base' => 'DOP', 'target' => 'EUR', 'rate' => 0.015593],
            ['base' => 'DOP', 'target' => 'GBP', 'rate' => 0.013389],
            ['base' => 'DOP', 'target' => 'JPY', 'rate' => 2.516949],
            ['base' => 'DOP', 'target' => 'CAD', 'rate' => 0.022881],
            ['base' => 'DOP', 'target' => 'HTG', 'rate' => 2.237288], // 1 DOP = 2.24 HTG
            ['base' => 'DOP', 'target' => 'BRL', 'rate' => 0.086441],
            ['base' => 'DOP', 'target' => 'MXN', 'rate' => 0.291525],
            ['base' => 'DOP', 'target' => 'EUR', 'rate' => 0.015593],
            
            // ==================== HTG AS BASE (Haitian Gourde) ====================
            ['base' => 'HTG', 'target' => 'USD', 'rate' => 0.007576],
            ['base' => 'HTG', 'target' => 'EUR', 'rate' => 0.006970],
            ['base' => 'HTG', 'target' => 'GBP', 'rate' => 0.005987],
            ['base' => 'HTG', 'target' => 'DOP', 'rate' => 0.446970], // 1 HTG = 0.45 DOP
            ['base' => 'HTG', 'target' => 'CAD', 'rate' => 0.010227],
            ['base' => 'HTG', 'target' => 'JPY', 'rate' => 1.125000],
            
            // ==================== BRL AS BASE (Brazilian Real) ====================
            ['base' => 'BRL', 'target' => 'USD', 'rate' => 0.196078],
            ['base' => 'BRL', 'target' => 'EUR', 'rate' => 0.180392],
            ['base' => 'BRL', 'target' => 'GBP', 'rate' => 0.154902],
            ['base' => 'BRL', 'target' => 'DOP', 'rate' => 11.568627],
            ['base' => 'BRL', 'target' => 'HTG', 'rate' => 25.882353], // 1 BRL = 25.88 HTG
            ['base' => 'BRL', 'target' => 'ARS', 'rate' => 166.666667],
            ['base' => 'BRL', 'target' => 'MXN', 'rate' => 3.372549],
            
            // ==================== MXN AS BASE (Mexican Peso) ====================
            ['base' => 'MXN', 'target' => 'USD', 'rate' => 0.058140],
            ['base' => 'MXN', 'target' => 'DOP', 'rate' => 3.430233],
            ['base' => 'MXN', 'target' => 'HTG', 'rate' => 7.674419],
            
            // ==================== CAD AS BASE (Canadian Dollar) ====================
            ['base' => 'CAD', 'target' => 'USD', 'rate' => 0.740741],
            ['base' => 'CAD', 'target' => 'DOP', 'rate' => 43.703704],
            ['base' => 'CAD', 'target' => 'HTG', 'rate' => 97.777778],
            
            // ==================== JPY AS BASE (Japanese Yen) ====================
            ['base' => 'JPY', 'target' => 'USD', 'rate' => 0.006734],
            ['base' => 'JPY', 'target' => 'DOP', 'rate' => 0.397307],
            ['base' => 'JPY', 'target' => 'HTG', 'rate' => 0.888889],
            
            // ==================== CNY AS BASE (Chinese Yuan) ====================
            ['base' => 'CNY', 'target' => 'USD', 'rate' => 0.138889],
            ['base' => 'CNY', 'target' => 'DOP', 'rate' => 8.194444],
            ['base' => 'CNY', 'target' => 'HTG', 'rate' => 18.333333],
        ];

        // Insert all exchange rates
        foreach ($rates as $rate) {
            $baseCurrency = $currencies[$rate['base']] ?? null;
            $targetCurrency = $currencies[$rate['target']] ?? null;
            
            if ($baseCurrency && $targetCurrency) {
                ExchangeRate::updateOrCreate(
                    [
                        'base_currency_id' => $baseCurrency->id,
                        'target_currency_id' => $targetCurrency->id,
                    ],
                    [
                        'rate' => $rate['rate'],
                        'fetched_at' => now(),
                    ]
                );
            }
        }
        
        // Also add the inverse rates automatically for convenience
        $this->addMissingInverseRates($currencies);
    }
    
    /**
     * Add inverse rates for any missing exchange rate combinations
     * This ensures all currency pairs have both directions available
     */
    private function addMissingInverseRates($currencies): void
    {
        $existingRates = ExchangeRate::all();
        
        foreach ($existingRates as $rate) {
            // Check if inverse rate exists
            $inverseExists = ExchangeRate::where('base_currency_id', $rate->target_currency_id)
                ->where('target_currency_id', $rate->base_currency_id)
                ->exists();
            
            if (!$inverseExists) {
                // Calculate inverse rate (1 / rate)
                $inverseRate = 1 / $rate->rate;
                
                ExchangeRate::create([
                    'base_currency_id' => $rate->target_currency_id,
                    'target_currency_id' => $rate->base_currency_id,
                    'rate' => $inverseRate,
                    'fetched_at' => now(),
                ]);
            }
        }
    }
}