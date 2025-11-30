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

    /*
    |--------------------------------------------------------------------------
    | Media File Size Limits (in bytes)
    |--------------------------------------------------------------------------
    |
    | Maximum file sizes for different media types.
    | WhatsApp limits: Image 5MB, Document 100MB, Audio 16MB, Video 16MB
    | You can set lower limits here to control storage costs.
    |
    */
    'media_limits' => [
        'image' => env('WHATSAPP_MAX_IMAGE_SIZE', 5 * 1024 * 1024),      // 5MB default
        'document' => env('WHATSAPP_MAX_DOCUMENT_SIZE', 25 * 1024 * 1024), // 25MB default
        'audio' => env('WHATSAPP_MAX_AUDIO_SIZE', 16 * 1024 * 1024),     // 16MB default
        'video' => env('WHATSAPP_MAX_VIDEO_SIZE', 16 * 1024 * 1024),     // 16MB default
    ],
];
