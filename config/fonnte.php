<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fonnte API token (Authorization header value, raw — no Bearer prefix)
    |--------------------------------------------------------------------------
    | Token is bound to ONE Fonnte device. Empirically verified 2026-05-28:
    |   POST https://api.fonnte.com/send
    |   Authorization: <token>
    |   form: target, message, typing, delay
    */
    'token' => env('FONNTE_TOKEN'),

    'base_url' => env('FONNTE_BASE_URL', 'https://api.fonnte.com'),

    /*
    |--------------------------------------------------------------------------
    | Origin number (Qashierwise's Fonnte device — for display only)
    |--------------------------------------------------------------------------
    */
    'origin_number' => env('FONNTE_ORIGIN_NUMBER', '62882003235019'),

    /*
    |--------------------------------------------------------------------------
    | Send options (PM-mandated defaults: typing=true, delay=5-10)
    |--------------------------------------------------------------------------
    */
    'typing' => env('FONNTE_TYPING', true),
    'delay'  => env('FONNTE_DELAY', '5-10'),

    /*
    |--------------------------------------------------------------------------
    | Low-stock threshold (≤ this triggers notification)
    |--------------------------------------------------------------------------
    */
    'low_stock_threshold' => (int) env('FONNTE_LOW_STOCK_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Low-stock notification dedupe TTL (seconds)
    | Prevents spamming when stock stays low across many orders.
    |--------------------------------------------------------------------------
    */
    'low_stock_dedupe_ttl' => (int) env('FONNTE_LOW_STOCK_DEDUPE_TTL', 86400), // 24h
];
