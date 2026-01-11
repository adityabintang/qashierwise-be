<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Agent Anti-Spam Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for AI Agent anti-spam and message validation workflow.
    | This includes rate limiting, message deduplication, and seen status settings.
    |
    */

    'anti_spam' => [
        /*
        |--------------------------------------------------------------------------
        | Rate Limiting Configuration
        |--------------------------------------------------------------------------
        |
        | Controls how many messages a user can send within a time window.
        | Default: 20 messages per 24 hours (86400 seconds)
        |
        */
        'rate_limit' => [
            'enabled' => env('AI_AGENT_RATE_LIMIT_ENABLED', true),
            'max_messages' => env('AI_AGENT_RATE_LIMIT_MAX', 20),
            'ttl_seconds' => env('AI_AGENT_RATE_LIMIT_TTL', 86400), // 24 hours
        ],

        /*
        |--------------------------------------------------------------------------
        | Message Deduplication Configuration
        |--------------------------------------------------------------------------
        |
        | Prevents duplicate message processing by tracking recent messages.
        | Default: 300 seconds (5 minutes) TTL for deduplication keys
        |
        */
        'deduplication' => [
            'enabled' => env('AI_AGENT_DEDUP_ENABLED', true),
            'ttl_seconds' => env('AI_AGENT_DEDUP_TTL', 300), // 5 minutes
        ],

        /*
        |--------------------------------------------------------------------------
        | Message Buffer Configuration (Debounce)
        |--------------------------------------------------------------------------
        |
        | Buffers rapid consecutive messages before sending to AI.
        | This prevents multiple AI responses when:
        | - WhatsApp sends duplicate webhook events
        | - User sends multiple messages quickly (spam enter)
        | - Voice note + text timing issues
        |
        | debounce_seconds: Wait time before processing buffered messages
        | ttl_seconds: Auto-cleanup buffer after this time (safety)
        |
        */
        'buffer' => [
            'enabled' => env('AI_AGENT_BUFFER_ENABLED', true),
            'debounce_seconds' => env('AI_AGENT_BUFFER_DEBOUNCE', 2), // 2 seconds wait
            'ttl_seconds' => env('AI_AGENT_BUFFER_TTL', 60), // 1 minute auto-cleanup
        ],

        /*
        |--------------------------------------------------------------------------
        | Seen Status Configuration
        |--------------------------------------------------------------------------
        |
        | Controls whether to send "read" status back to WhatsApp for messages.
        | This provides user feedback that their message has been received.
        |
        */
        'seen_status' => [
            'enabled' => env('AI_AGENT_SEEN_STATUS_ENABLED', true),
        ],
    ],
];
