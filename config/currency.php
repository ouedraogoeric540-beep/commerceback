<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Devise Pivot (Pivot Currency)
    |--------------------------------------------------------------------------
    |
    | Cette devise sert de base pour toutes les conversions. 
    | Les taux de change seront stockés par rapport à cette devise.
    |
    */
    'pivot_currency' => env('BASE_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Fournisseurs de Taux de Change (Providers)
    |--------------------------------------------------------------------------
    |
    | Définition de la chaîne de responsabilité (Fallback Strategy).
    | Le service de conversion essaiera le 'primary', s'il échoue (timeout, erreur),
    | il passera au 'secondary', puis au 'fallback'.
    |
    */
    'providers' => [
        'primary' => \App\Modules\Currency\Providers\FrankfurterProvider::class,
        'secondary' => \App\Modules\Currency\Providers\ExchangeRateApiProvider::class,
        'fallback' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Settings
    |--------------------------------------------------------------------------
    |
    | Redis est recommandé pour des performances Enterprise.
    |
    */
    'cache' => [
        'store' => env('CACHE_STORE', 'file'),
        'ttl'   => 3600, // 1 heure par défaut
        'prefix' => 'currency_rate_',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Keys (Si nécessaires)
    |--------------------------------------------------------------------------
    */
    'api_keys' => [
        'exchange_rate_api' => env('EXCHANGE_RATE_API_KEY', ''),
    ],
];
