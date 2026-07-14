<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Configuration des services Mobile Money
    |--------------------------------------------------------------------------
    */
    'orange_money' => [
        'enabled' => env('ORANGE_MONEY_ENABLED', true),
        'api_url' => env('ORANGE_MONEY_API_URL', 'https://api.orange.com/money'),
        'merchant_id' => env('ORANGE_MONEY_MERCHANT_ID', ''),
        'api_key' => env('ORANGE_MONEY_API_KEY', ''),
        'api_secret' => env('ORANGE_MONEY_API_SECRET', ''),
        'callback_url' => env('ORANGE_MONEY_CALLBACK_URL', ''),
    ],
    
    'mtn_mobile_money' => [
        'enabled' => env('MTN_MONEY_ENABLED', true),
        'api_url' => env('MTN_MONEY_API_URL', 'https://api.mtn.com/money'),
        'merchant_id' => env('MTN_MONEY_MERCHANT_ID', ''),
        'api_key' => env('MTN_MONEY_API_KEY', ''),
        'api_secret' => env('MTN_MONEY_API_SECRET', ''),
        'callback_url' => env('MTN_MONEY_CALLBACK_URL', ''),
    ],
    
    'moov_money' => [
        'enabled' => env('MOOV_MONEY_ENABLED', true),
        'api_url' => env('MOOV_MONEY_API_URL', 'https://api.moov.com/money'),
        'merchant_id' => env('MOOV_MONEY_MERCHANT_ID', ''),
        'api_key' => env('MOOV_MONEY_API_KEY', ''),
        'api_secret' => env('MOOV_MONEY_API_SECRET', ''),
        'callback_url' => env('MOOV_MONEY_CALLBACK_URL', ''),
    ],
];