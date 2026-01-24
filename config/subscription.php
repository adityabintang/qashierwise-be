<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Provider
    |--------------------------------------------------------------------------
    |
    | The default subscription provider to use for new subscriptions.
    | Supported: "polar", "midtrans"
    |
    */
    'provider' => env('SUBSCRIPTION_PROVIDER', 'midtrans'),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans subscription integration.
    |
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com/v1'
            : 'https://api.sandbox.midtrans.com/v1',
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
        'success' => env('MIDTRANS_SUBSCRIPTION_SUCCESS_URL', env('APP_URL') . '/subscription/success'),
        'cancel' => env('MIDTRANS_SUBSCRIPTION_CANCEL_URL', env('APP_URL') . '/subscription/cancel'),
        'error' => env('MIDTRANS_SUBSCRIPTION_ERROR_URL', env('APP_URL') . '/subscription/error'),
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
        'standard' => [
            'id' => 'standard',
            'name' => 'Standard',
            'price' => 99000,
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
            'features' => [
                'Semua fitur Basic',
                'Manajemen kontak unlimited',
                'Template pesan kustom',
                'Laporan analitik dasar',
                'Dukungan email',
                'Hingga 2 outlet',
                'QRIS unlimited',
                'Delivery & Pickup',
            ],
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price' => 199000,
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
            'features' => [
                'Semua fitur Standard',
                'API akses penuh',
                'Laporan analitik lanjutan',
                'Integrasi webhook',
                'Dukungan prioritas',
                'Multi-user support',
                'Unlimited outlet',
                'Multi-branding',
                'Custom domain',
            ],
        ],
    ],
];
