<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Service for monitoring subscription events and alerting on errors.
 * 
 * Provides centralized error alerting, metrics tracking, and health monitoring
 * for the subscription system.
 */
class SubscriptionMonitoringService
{
    /**
     * Alert thresholds for error rates.
     */
    private const ERROR_RATE_THRESHOLD = 0.1; // 10% error rate
    private const WEBHOOK_FAILURE_THRESHOLD = 5; // 5 consecutive failures
    private const API_TIMEOUT_THRESHOLD = 5000; // 5 seconds

    /**
     * Alert on subscription creation failure.
     *
     * @param string $userId User ID
     * @param string $planId Plan ID
     * @param string $error Error message
     * @param array $context Additional context
     * @return void
     */
    public function alertSubscriptionCreationFailed(
        string $userId,
        string $planId,
        string $error,
        array $context = []
    ): void {
        $this->incrementErrorCounter('subscription.creation.failed');

        Log::critical('Subscription creation failed', array_merge([
            'alert' => 'subscription_creation_failed',
            'userId' => $userId,
            'planId' => $planId,
            'error' => $error,
            'severity' => 'high',
        ], $context));

        // Check if error rate exceeds threshold
        if ($this->isErrorRateExceeded('subscription.creation')) {
            $this->sendCriticalAlert('Subscription Creation Error Rate Exceeded', [
                'error_rate' => $this->getErrorRate('subscription.creation'),
                'threshold' => self::ERROR_RATE_THRESHOLD,
                'recent_errors' => $this->getRecentErrors('subscription.creation', 10),
            ]);
        }
    }

    /**
     * Alert on webhook processing failure.
     *
     * @param string $webhookType Webhook type (payment, recurring, pay_account)
     * @param string $orderId Order ID
     * @param string $error Error message
     * @param array $context Additional context
     * @return void
     */
    public function alertWebhookProcessingFailed(
        string $webhookType,
        string $orderId,
        string $error,
        array $context = []
    ): void {
        $this->incrementErrorCounter("webhook.{$webhookType}.failed");

        Log::error('Webhook processing failed', array_merge([
            'alert' => 'webhook_processing_failed',
            'webhookType' => $webhookType,
            'orderId' => $orderId,
            'error' => $error,
            'severity' => 'medium',
        ], $context));

        // Check for consecutive failures
        $consecutiveFailures = $this->incrementConsecutiveFailures("webhook.{$webhookType}");
        if ($consecutiveFailures >= self::WEBHOOK_FAILURE_THRESHOLD) {
            $this->sendCriticalAlert('Webhook Processing Consecutive Failures', [
                'webhook_type' => $webhookType,
                'consecutive_failures' => $consecutiveFailures,
                'threshold' => self::WEBHOOK_FAILURE_THRESHOLD,
                'order_id' => $orderId,
            ]);
        }
    }

    /**
     * Alert on Midtrans API timeout.
     *
     * @param string $endpoint API endpoint
     * @param float $duration Duration in milliseconds
     * @param array $context Additional context
     * @return void
     */
    public function alertApiTimeout(
        string $endpoint,
        float $duration,
        array $context = []
    ): void {
        if ($duration > self::API_TIMEOUT_THRESHOLD) {
            Log::warning('Midtrans API timeout', array_merge([
                'alert' => 'api_timeout',
                'endpoint' => $endpoint,
                'duration_ms' => $duration,
                'threshold_ms' => self::API_TIMEOUT_THRESHOLD,
                'severity' => 'medium',
            ], $context));

            $this->incrementErrorCounter('api.timeout');
        }
    }

    /**
     * Alert on subscription cancellation failure.
     *
     * @param string $subscriptionId Subscription ID
     * @param string $error Error message
     * @param array $context Additional context
     * @return void
     */
    public function alertSubscriptionCancellationFailed(
        string $subscriptionId,
        string $error,
        array $context = []
    ): void {
        Log::error('Subscription cancellation failed', array_merge([
            'alert' => 'subscription_cancellation_failed',
            'subscriptionId' => $subscriptionId,
            'error' => $error,
            'severity' => 'high',
        ], $context));

        $this->incrementErrorCounter('subscription.cancellation.failed');
    }

    /**
     * Alert on payment failure.
     *
     * @param string $subscriptionId Subscription ID
     * @param string $orderId Order ID
     * @param string $reason Failure reason
     * @param array $context Additional context
     * @return void
     */
    public function alertPaymentFailed(
        string $subscriptionId,
        string $orderId,
        string $reason,
        array $context = []
    ): void {
        Log::warning('Subscription payment failed', array_merge([
            'alert' => 'payment_failed',
            'subscriptionId' => $subscriptionId,
            'orderId' => $orderId,
            'reason' => $reason,
            'severity' => 'medium',
        ], $context));

        $this->incrementErrorCounter('payment.failed');

        // Check if payment failure rate is high
        if ($this->isErrorRateExceeded('payment')) {
            $this->sendCriticalAlert('Payment Failure Rate Exceeded', [
                'error_rate' => $this->getErrorRate('payment'),
                'threshold' => self::ERROR_RATE_THRESHOLD,
                'subscription_id' => $subscriptionId,
            ]);
        }
    }

    /**
     * Alert on webhook signature validation failure.
     *
     * @param string $orderId Order ID
     * @param string $ipAddress IP address
     * @param array $context Additional context
     * @return void
     */
    public function alertWebhookSignatureInvalid(
        string $orderId,
        string $ipAddress,
        array $context = []
    ): void {
        Log::warning('Webhook signature validation failed', array_merge([
            'alert' => 'webhook_signature_invalid',
            'orderId' => $orderId,
            'ipAddress' => $ipAddress,
            'severity' => 'high',
        ], $context));

        $this->incrementErrorCounter('webhook.signature.invalid');

        // Check for potential security issue
        $invalidAttempts = $this->incrementInvalidSignatureAttempts($ipAddress);
        if ($invalidAttempts >= 5) {
            $this->sendCriticalAlert('Multiple Invalid Webhook Signatures', [
                'ip_address' => $ipAddress,
                'attempts' => $invalidAttempts,
                'order_id' => $orderId,
                'severity' => 'critical',
            ]);
        }
    }

    /**
     * Record successful operation (for error rate calculation).
     *
     * @param string $operation Operation name
     * @return void
     */
    public function recordSuccess(string $operation): void
    {
        $this->incrementSuccessCounter($operation);
        $this->resetConsecutiveFailures($operation);
    }

    /**
     * Increment error counter.
     *
     * @param string $key Counter key
     * @return int Current count
     */
    private function incrementErrorCounter(string $key): int
    {
        $cacheKey = "monitoring:errors:{$key}:" . now()->format('Y-m-d-H');
        return Cache::increment($cacheKey, 1);
    }

    /**
     * Increment success counter.
     *
     * @param string $key Counter key
     * @return int Current count
     */
    private function incrementSuccessCounter(string $key): int
    {
        $cacheKey = "monitoring:success:{$key}:" . now()->format('Y-m-d-H');
        return Cache::increment($cacheKey, 1);
    }

    /**
     * Increment consecutive failures counter.
     *
     * @param string $key Counter key
     * @return int Current count
     */
    private function incrementConsecutiveFailures(string $key): int
    {
        $cacheKey = "monitoring:consecutive_failures:{$key}";
        $count = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, now()->addHour());
        return $count;
    }

    /**
     * Reset consecutive failures counter.
     *
     * @param string $key Counter key
     * @return void
     */
    private function resetConsecutiveFailures(string $key): void
    {
        $cacheKey = "monitoring:consecutive_failures:{$key}";
        Cache::forget($cacheKey);
    }

    /**
     * Increment invalid signature attempts for an IP.
     *
     * @param string $ipAddress IP address
     * @return int Current count
     */
    private function incrementInvalidSignatureAttempts(string $ipAddress): int
    {
        $cacheKey = "monitoring:invalid_signature:" . md5($ipAddress);
        $count = Cache::get($cacheKey, 0) + 1;
        Cache::put($cacheKey, $count, now()->addHour());
        return $count;
    }

    /**
     * Check if error rate exceeds threshold.
     *
     * @param string $operation Operation name
     * @return bool
     */
    private function isErrorRateExceeded(string $operation): bool
    {
        $errorRate = $this->getErrorRate($operation);
        return $errorRate > self::ERROR_RATE_THRESHOLD;
    }

    /**
     * Get error rate for an operation.
     *
     * @param string $operation Operation name
     * @return float Error rate (0.0 to 1.0)
     */
    private function getErrorRate(string $operation): float
    {
        $hour = now()->format('Y-m-d-H');
        $errors = Cache::get("monitoring:errors:{$operation}:{$hour}", 0);
        $successes = Cache::get("monitoring:success:{$operation}:{$hour}", 0);
        
        $total = $errors + $successes;
        if ($total === 0) {
            return 0.0;
        }

        return $errors / $total;
    }

    /**
     * Get recent errors for an operation.
     *
     * @param string $operation Operation name
     * @param int $limit Number of recent errors to retrieve
     * @return array
     */
    private function getRecentErrors(string $operation, int $limit = 10): array
    {
        $cacheKey = "monitoring:recent_errors:{$operation}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Send critical alert.
     *
     * @param string $title Alert title
     * @param array $data Alert data
     * @return void
     */
    private function sendCriticalAlert(string $title, array $data): void
    {
        // Log the critical alert
        Log::critical($title, array_merge([
            'alert_type' => 'critical',
            'timestamp' => now()->toIso8601String(),
        ], $data));

        // In production, this would send email/SMS/Slack notification
        // For now, we just log it
        
        // Example: Send email to admin
        // Mail::to(config('monitoring.alert_email'))
        //     ->send(new CriticalAlertMail($title, $data));

        // Example: Send Slack notification
        // Notification::route('slack', config('monitoring.slack_webhook'))
        //     ->notify(new CriticalAlert($title, $data));
    }

    /**
     * Get monitoring metrics for dashboard.
     *
     * @return array
     */
    public function getMetrics(): array
    {
        $hour = now()->format('Y-m-d-H');

        return [
            'subscription_creation' => [
                'errors' => Cache::get("monitoring:errors:subscription.creation.failed:{$hour}", 0),
                'successes' => Cache::get("monitoring:success:subscription.creation:{$hour}", 0),
                'error_rate' => $this->getErrorRate('subscription.creation'),
            ],
            'webhook_processing' => [
                'payment_errors' => Cache::get("monitoring:errors:webhook.payment.failed:{$hour}", 0),
                'recurring_errors' => Cache::get("monitoring:errors:webhook.recurring.failed:{$hour}", 0),
                'pay_account_errors' => Cache::get("monitoring:errors:webhook.pay_account.failed:{$hour}", 0),
            ],
            'payment_failures' => [
                'count' => Cache::get("monitoring:errors:payment.failed:{$hour}", 0),
                'error_rate' => $this->getErrorRate('payment'),
            ],
            'api_timeouts' => [
                'count' => Cache::get("monitoring:errors:api.timeout:{$hour}", 0),
            ],
            'security' => [
                'invalid_signatures' => Cache::get("monitoring:errors:webhook.signature.invalid:{$hour}", 0),
            ],
        ];
    }

    /**
     * Check system health.
     *
     * @return array
     */
    public function checkHealth(): array
    {
        $metrics = $this->getMetrics();
        $issues = [];

        // Check subscription creation error rate
        if ($metrics['subscription_creation']['error_rate'] > self::ERROR_RATE_THRESHOLD) {
            $issues[] = [
                'type' => 'high_error_rate',
                'component' => 'subscription_creation',
                'severity' => 'high',
                'error_rate' => $metrics['subscription_creation']['error_rate'],
            ];
        }

        // Check payment failure rate
        if ($metrics['payment_failures']['error_rate'] > self::ERROR_RATE_THRESHOLD) {
            $issues[] = [
                'type' => 'high_error_rate',
                'component' => 'payment',
                'severity' => 'high',
                'error_rate' => $metrics['payment_failures']['error_rate'],
            ];
        }

        // Check for webhook processing errors
        $totalWebhookErrors = $metrics['webhook_processing']['payment_errors'] +
                             $metrics['webhook_processing']['recurring_errors'] +
                             $metrics['webhook_processing']['pay_account_errors'];
        
        if ($totalWebhookErrors > 10) {
            $issues[] = [
                'type' => 'high_webhook_errors',
                'component' => 'webhook_processing',
                'severity' => 'medium',
                'error_count' => $totalWebhookErrors,
            ];
        }

        // Check for security issues
        if ($metrics['security']['invalid_signatures'] > 5) {
            $issues[] = [
                'type' => 'security_concern',
                'component' => 'webhook_signature',
                'severity' => 'high',
                'invalid_count' => $metrics['security']['invalid_signatures'],
            ];
        }

        return [
            'status' => empty($issues) ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'issues' => $issues,
            'metrics' => $metrics,
        ];
    }
}
