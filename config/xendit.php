<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Xendit API Key
    |--------------------------------------------------------------------------
    |
    | Master account API key for XenPlatform. All API calls use this key
    | with the for-user-id header to transact on behalf of sub-accounts.
    |
    */
    'api_key' => env('XENDIT_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Xendit Webhook Token
    |--------------------------------------------------------------------------
    |
    | Token used to verify incoming webhook notifications from Xendit.
    | This is sent in the x-callback-token header.
    |
    */
    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Xendit Base URL
    |--------------------------------------------------------------------------
    |
    | Base URL for Xendit API calls.
    |
    */
    'base_url' => env('XENDIT_BASE_URL', 'https://api.xendit.co'),

    /*
    |--------------------------------------------------------------------------
    | Webhook callback URL for sub-accounts
    |--------------------------------------------------------------------------
    |
    | Public HTTPS URL Xendit calls when sub-accounts receive payment events.
    | Falls back to APP_URL + /api/webhooks/xendit when unset. XenPlatform
    | sub-accounts do NOT inherit master callbacks — must be set per account.
    |
    */
    'webhook_url' => env('XENDIT_WEBHOOK_URL', rtrim(env('APP_URL', ''), '/') . '/api/webhooks/xendit'),

    /*
    |--------------------------------------------------------------------------
    | Platform Fee Percentage
    |--------------------------------------------------------------------------
    |
    | Platform fee percentage deducted from each QRIS transaction.
    |
    */
    'platform_fee_percentage' => (float) env('XENDIT_PLATFORM_FEE', 2.5),

];
