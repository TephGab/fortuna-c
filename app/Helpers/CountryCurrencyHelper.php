<?php

namespace App\Helpers;

class CountryCurrencyHelper
{
    /**
     * Country to currency mapping
     */
    protected static array $map = [
        'US' => 'USD', 'GB' => 'GBP', 'CA' => 'CAD', 'AU' => 'AUD',
        'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR', 'ES' => 'EUR',
        'JP' => 'JPY', 'CN' => 'CNY', 'IN' => 'INR', 'BR' => 'BRL',
        'MX' => 'MXN', 'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK',
        'DK' => 'DKK', 'PL' => 'PLN', 'RU' => 'RUB', 'TR' => 'TRY',
        'ZA' => 'ZAR', 'NG' => 'NGN', 'EG' => 'EGP', 'AE' => 'AED',
        'SA' => 'SAR', 'KW' => 'KWD', 'BH' => 'BHD', 'QA' => 'QAR',
        'SG' => 'SGD', 'HK' => 'HKD', 'MY' => 'MYR', 'TH' => 'THB',
        'VN' => 'VND', 'PH' => 'PHP', 'ID' => 'IDR', 'KR' => 'KRW',
    ];

    public static function getCurrency(string $countryCode): string
    {
        return self::$map[strtoupper($countryCode)] ?? 'USD';
    }

    public static function getCountries(): array
    {
        $countries = [
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy', 'ES' => 'Spain',
            'JP' => 'Japan', 'CN' => 'China', 'IN' => 'India', 'BR' => 'Brazil',
            'AU' => 'Australia', 'MX' => 'Mexico', 'CH' => 'Switzerland',
        ];
        
        $result = [];
        foreach ($countries as $code => $name) {
            $result[] = [
                'code' => $code,
                'name' => $name,
                'currency' => self::getCurrency($code),
            ];
        }
        return $result;
    }
}