<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Provider
    |--------------------------------------------------------------------------
    |
    | The subscription provider to use for subscriptions.
    | Currently supports: "midtrans"
    |
    */
    'provider' => 'midtrans',

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
    | Invoice Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans Invoice API.
    | Invoices are automatically sent to user's email after successful payment.
    |
    */
    'invoice' => [
        'enabled' => env('MIDTRANS_INVOICE_ENABLED', true),
        'due_days' => env('MIDTRANS_INVOICE_DUE_DAYS', 7),
        'payment_methods' => [
            'bca_va',
            'bni_va',
            'bri_va',
            'permata_va',
            'gopay',
            'shopeepay',
        ],
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
        'success' => env('MIDTRANS_SUBSCRIPTION_SUCCESS_URL', env('APP_URL').'/subscription/success'),
        'cancel' => env('MIDTRANS_SUBSCRIPTION_CANCEL_URL', env('APP_URL').'/subscription/cancel'),
        'error' => env('MIDTRANS_SUBSCRIPTION_ERROR_URL', env('APP_URL').'/subscription/error'),
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
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price_monthly' => 350000,
            'currency' => 'IDR',
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
