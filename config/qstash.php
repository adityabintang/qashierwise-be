<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Qstash Configuration
    |--------------------------------------------------------------------------
    |
    | Qstash is an HTTP-based messaging and scheduling solution for serverless.
    | It allows scheduling jobs to be executed at a specific time in the future.
    |
    */

    'token' => env('QSTASH_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Qstash API URL
    |--------------------------------------------------------------------------
    | Default: http://127.0.0.1:8080/v2 (local) or https://qstash.upstash.io/v2 (cloud)
    */
    'api_url' => env('QSTASH_API_URL', 'http://127.0.0.1:8080/v2'),

    /*
    |--------------------------------------------------------------------------
    | Maximum delay in seconds (7 days)
    |--------------------------------------------------------------------------
    */
    'max_delay_seconds' => 7 * 24 * 60 * 60,
];
