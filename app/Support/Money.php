<?php

namespace App\Support;

class Money
{
    /** currency code => display symbol (prefix) */
    public const SYMBOLS = [
        'EUR' => '€',
        'USD' => '$',
        'GBP' => '£',
        'CHF' => 'CHF ',
        'JPY' => '¥',
        'CAD' => 'CA$',
        'AUD' => 'A$',
        'SEK' => 'kr ',
        'NOK' => 'kr ',
        'DKK' => 'kr ',
        'PLN' => 'zł ',
        'BTC' => '₿',
    ];

    public static function symbol(string $currency): string
    {
        return self::SYMBOLS[strtoupper($currency)] ?? strtoupper($currency).' ';
    }

    public static function format(float $amount, string $currency = 'EUR', int $decimals = 2): string
    {
        $symbol = self::symbol($currency);
        $sign = $amount < 0 ? '-' : '';

        return $sign.$symbol.number_format(abs($amount), $decimals, '.', ',');
    }
}
