<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base currency
    |--------------------------------------------------------------------------
    |
    | Net worth, allocation and every aggregated figure in the UI is converted
    | into this currency. Individual holdings keep their own native currency.
    |
    */

    'base_currency' => env('FINFOLIO_BASE_CURRENCY', 'EUR'),

    /*
    |--------------------------------------------------------------------------
    | Price providers
    |--------------------------------------------------------------------------
    |
    | Each asset type is routed to a provider. Providers only need public
    | endpoints, no API key is required for the defaults.
    |
    */

    'providers' => [
        'crypto' => env('PRICES_CRYPTO_PROVIDER', 'coingecko'),
        'stock' => env('PRICES_EQUITY_PROVIDER', 'yahoo'),
        'etf' => env('PRICES_EQUITY_PROVIDER', 'yahoo'),
        'fund' => env('PRICES_EQUITY_PROVIDER', 'yahoo'),
        'index' => env('PRICES_EQUITY_PROVIDER', 'yahoo'),
        'commodity' => env('PRICES_EQUITY_PROVIDER', 'yahoo'),
        // 'other' and 'cash' have no provider: value is entered by hand.
    ],

    /*
    |--------------------------------------------------------------------------
    | Asset categories (the "what are you searching for?" selector)
    |--------------------------------------------------------------------------
    */

    'categories' => [
        'stock' => ['label' => 'Stocks', 'searchable' => true],
        'etf' => ['label' => 'ETF', 'searchable' => true],
        'index' => ['label' => 'Index funds', 'searchable' => true],
        'commodity' => ['label' => 'Commodities', 'searchable' => true],
        'crypto' => ['label' => 'Crypto', 'searchable' => true],
        'realestate' => ['label' => 'Real estate', 'searchable' => false],
        'cash' => ['label' => 'Cash', 'searchable' => false],
        'other' => ['label' => 'Other', 'searchable' => false],
    ],

    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key' => env('COINGECKO_API_KEY'),
    ],

    'yahoo' => [
        'base_url' => env('YAHOO_BASE_URL', 'https://query1.finance.yahoo.com'),
    ],

    'fx' => [
        'base_url' => env('FX_BASE_URL', 'https://open.er-api.com/v6/latest'),
        'cache_ttl' => (int) env('FX_CACHE_TTL', 3600),
    ],

    'cache_ttl' => (int) env('PRICES_CACHE_TTL', 120),

    /*
    |--------------------------------------------------------------------------
    | Chart ranges
    |--------------------------------------------------------------------------
    |
    | Windows offered by the net-worth chart selector. `days` of null means
    | "all history".
    |
    */

    'ranges' => [
        '1D' => ['label' => '1D',  'days' => 1],
        '1W' => ['label' => '1W',  'days' => 7],
        '1M' => ['label' => '1M',  'days' => 30],
        'YTD' => ['label' => 'YTD', 'days' => 'ytd'],
        '1Y' => ['label' => '1Y',  'days' => 365],
        'Max' => ['label' => 'Max', 'days' => null],
    ],
];
