<?php

return [
    'max_webhooks_per_user' => 10,

    'retry' => [
        'max_attempts' => 4,
        'delays' => [
            1 => 5,      // 5 minutes
            2 => 30,     // 30 minutes
            3 => 120,    // 2 hours
            4 => 720,    // 12 hours
        ],
    ],

    'delivery' => [
        'timeout' => 30,
        'require_https' => true,
    ],

    'valid_events' => [
        'message.incoming',
        'message.status_updated',
        'template.status_changed',
    ],
];
