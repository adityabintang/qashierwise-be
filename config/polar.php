<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Polar.sh API Token
    |--------------------------------------------------------------------------
    |
    | Your Polar.sh API token for authenticating API requests.
    | Get this from your Polar.sh dashboard.
    |
    */
    'api_token' => env('POLAR_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | Enable sandbox mode for testing. When enabled, API calls will be made
    | to the Polar.sh sandbox environment instead of production.
    | Set POLAR_SANDBOX=true in .env to enable.
    |
    */
    'sandbox' => env('POLAR_SANDBOX', false),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | The secret used to validate incoming webhook requests from Polar.sh.
    | This ensures webhooks are authentic and haven't been tampered with.
    |
    */
    'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Product IDs
    |--------------------------------------------------------------------------
    |
    | Mapping of internal plan identifiers to Polar.sh product IDs.
    | These IDs are used when creating checkout sessions.
    |
    */
    'products' => [
        'standard' => env('POLAR_PRODUCT_STANDARD'),
        'pro' => env('POLAR_PRODUCT_PRO'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | URLs for redirecting users after checkout completion or cancellation.
    |
    */
    'urls' => [
        'success' => env('POLAR_SUCCESS_URL', '/dashboard?subscription=success'),
        'cancel' => env('POLAR_CANCEL_URL', '/pricing?subscription=cancelled'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Period
    |--------------------------------------------------------------------------
    |
    | Number of days for the free trial period.
    |
    */
    'trial_days' => env('POLAR_TRIAL_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Plan Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each subscription plan including features and pricing.
    |
    */
    'plans' => [
        'free_trial' => [
            'id' => 'free_trial',
            'name' => 'Basic (Free Trial)',
            'price_monthly' => 0,
            'tier' => 'basic',
            'features' => [
                'Akses dasar ke dashboard',
                'Manajemen kontak terbatas',
                'Template pesan standar',
            ],
        ],
        'standard' => [
            'id' => 'standard',
            'name' => 'Standard',
            'price_monthly' => 99000,
            'tier' => 'standard',
            'features' => [
                'Semua fitur Basic',
                'Manajemen kontak unlimited',
                'Template pesan kustom',
                'Laporan analitik dasar',
                'Dukungan email',
            ],
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price_monthly' => 199000,
            'tier' => 'pro',
            'features' => [
                'Semua fitur Standard',
                'API akses penuh',
                'Laporan analitik lanjutan',
                'Integrasi webhook',
                'Dukungan prioritas',
                'Multi-user support',
            ],
        ],
    ],
];
