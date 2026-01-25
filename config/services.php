<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'byteplus_ark' => [
        'api_key' => env('BYTEPLUS_ARK_API_KEY'),
        'base_url' => env('BYTEPLUS_ARK_BASE_URL', 'https://ark.ap-southeast.bytepluses.com/api/v3'),
        'model' => env('BYTEPLUS_ARK_MODEL', 'GPT-OSS-120B'),
    ],

    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        'merchant_id' => env('MIDTRANS_MERCHANT_ID'),
    ],

    'whatsapp' => [
        'api_version' => env('WHATSAPP_API_VERSION', 'v22.0'),
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'embedded_signup_config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        // Flow endpoint configuration
        'flow_private_key' => env('WHATSAPP_FLOW_PRIVATE_KEY'),
        'flow_passphrase' => env('WHATSAPP_FLOW_PASSPHRASE'),
        'flow_table_fee' => env('WHATSAPP_FLOW_TABLE_FEE', 100000),
    ],

    'google_analytics' => [
        'measurement_id' => env('GA_MEASUREMENT_ID'),
    ],

];
