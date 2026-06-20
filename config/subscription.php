<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Provider
    |--------------------------------------------------------------------------
    |
    | The subscription provider to use for subscriptions.
    | Currently supports: "xendit"
    |
    */
    'provider' => 'xendit',

    /*
    |--------------------------------------------------------------------------
    | Xendit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Xendit Recurring/Subscriptions integration.
    |
    */
    'xendit' => [
        'api_key' => env('XENDIT_API_KEY'),
        'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),
        'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription URLs
    |--------------------------------------------------------------------------
    |
    | URLs for redirecting users after subscription actions.
    |
    */
    'urls' => [
        'success' => env('XENDIT_SUBSCRIPTION_SUCCESS_URL', env('APP_URL').'/subscription/success'),
        'cancel' => env('XENDIT_SUBSCRIPTION_CANCEL_URL', env('APP_URL').'/subscription/cancelled'),
        'error' => env('XENDIT_SUBSCRIPTION_ERROR_URL', env('APP_URL').'/subscription/error'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Period
    |--------------------------------------------------------------------------
    |
    | Number of days for the free trial period.
    |
    */
    'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Available subscription plans with their configurations.
    |
    */

    'plans' => [
        'free_trial' => [
            'id' => 'free_trial',
            'name' => 'Free Trial',
            'price_monthly' => 0,
            'currency' => 'IDR',
            'tier' => 'basic',
            'features' => [
                'POS Kasir',
                'Menu Management',
                'Laporan Dasar',
                'Hingga 1 outlet',
            ],
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price_monthly' => 350000,
            'currency' => 'IDR',
            'tier' => 'pro',
            'features' => [
                'Semua fitur Basic',
                'Delivery + antrean & biaya',
                'Pembayaran QRIS unlimited',
                'Pengingat & auto confirm',
                'XX pesan/bulan + Chat support',
                'Customer Base',
                'Hingga 2 outlet',
                'Analytic + ekspor CSV',
                'Webhook & API',
            ],
            'durations' => [
                '1_month' => [
                    'id' => 'pro_1_month',
                    'name' => '1 Bulan',
                    'months' => 1,
                    'price' => 350000,
                    'price_per_month' => 350000,
                    'discount' => 0,
                ],
                '3_months' => [
                    'id' => 'pro_3_months',
                    'name' => '3 Bulan',
                    'months' => 3,
                    'price' => 1050000,
                    'price_per_month' => 350000,
                    'discount' => 0,
                ],
                '1_year' => [
                    'id' => 'pro_1_year',
                    'name' => '1 Tahun',
                    'months' => 12,
                    'price' => 3780000, // 10% discount
                    'price_per_month' => 315000,
                    'discount' => 10,
                ],
            ],
        ],
    ],
];
