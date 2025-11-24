<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for WhatsApp Cloud API integration
    |
    */

    'api_version' => env('WHATSAPP_API_VERSION', 'v22.0'),
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    'app_id' => env('WHATSAPP_APP_ID'),
    'app_secret' => env('WHATSAPP_APP_SECRET'),

    // Additional WABA IDs for multi-account support (comma-separated in .env)
    'additional_waba_ids' => array_filter(explode(',', env('WHATSAPP_ADDITIONAL_WABA_IDS', ''))),
];
