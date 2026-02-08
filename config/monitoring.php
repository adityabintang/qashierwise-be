<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for subscription system monitoring, alerting, and metrics.
    |
    */

    /**
     * Enable/disable monitoring features.
     */
    'enabled' => env('MONITORING_ENABLED', true),

    /**
     * Alert notification channels.
     */
    'alerts' => [
        'email' => [
            'enabled' => env('MONITORING_EMAIL_ALERTS', false),
            'recipients' => explode(',', env('MONITORING_ALERT_EMAILS', '')),
        ],
        'slack' => [
            'enabled' => env('MONITORING_SLACK_ALERTS', false),
            'webhook_url' => env('MONITORING_SLACK_WEBHOOK', ''),
        ],
        'log' => [
            'enabled' => true,
            'channel' => env('MONITORING_LOG_CHANNEL', 'stack'),
        ],
    ],

    /**
     * Error rate thresholds for alerting.
     */
    'thresholds' => [
        'error_rate' => env('MONITORING_ERROR_RATE_THRESHOLD', 0.1), // 10%
        'webhook_failures' => env('MONITORING_WEBHOOK_FAILURE_THRESHOLD', 5),
        'api_timeout_ms' => env('MONITORING_API_TIMEOUT_THRESHOLD', 5000), // 5 seconds
        'invalid_signatures' => env('MONITORING_INVALID_SIGNATURE_THRESHOLD', 5),
    ],

    /**
     * Metrics retention period.
     */
    'metrics' => [
        'retention_hours' => env('MONITORING_METRICS_RETENTION', 24),
        'aggregation_interval' => env('MONITORING_AGGREGATION_INTERVAL', 'hourly'), // hourly, daily
    ],

    /**
     * Health check configuration.
     */
    'health_check' => [
        'enabled' => env('MONITORING_HEALTH_CHECK_ENABLED', true),
        'endpoint' => '/api/health/subscription',
    ],

    /**
     * Logging configuration for subscription events.
     */
    'logging' => [
        'subscription_events' => [
            'enabled' => true,
            'level' => env('MONITORING_SUBSCRIPTION_LOG_LEVEL', 'info'),
            'include_context' => true,
        ],
        'webhook_events' => [
            'enabled' => true,
            'level' => env('MONITORING_WEBHOOK_LOG_LEVEL', 'info'),
            'include_payload' => env('MONITORING_WEBHOOK_LOG_PAYLOAD', false),
        ],
        'api_calls' => [
            'enabled' => true,
            'level' => env('MONITORING_API_LOG_LEVEL', 'info'),
            'include_duration' => true,
        ],
        'errors' => [
            'enabled' => true,
            'level' => 'error',
            'include_trace' => env('MONITORING_ERROR_LOG_TRACE', true),
        ],
    ],

    /**
     * Performance monitoring.
     */
    'performance' => [
        'enabled' => env('MONITORING_PERFORMANCE_ENABLED', true),
        'slow_query_threshold_ms' => env('MONITORING_SLOW_QUERY_THRESHOLD', 1000),
        'slow_api_threshold_ms' => env('MONITORING_SLOW_API_THRESHOLD', 3000),
    ],
];
