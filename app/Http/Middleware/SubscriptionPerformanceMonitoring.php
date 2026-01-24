<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for monitoring subscription-related request performance.
 * 
 * Tracks request duration, logs slow requests, and collects performance metrics.
 */
class SubscriptionPerformanceMonitoring
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Process the request
        $response = $next($request);

        // Calculate metrics
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = memory_get_usage() - $startMemory;
        $memoryPeak = memory_get_peak_usage();

        // Log performance metrics
        $this->logPerformanceMetrics($request, $response, $duration, $memoryUsed, $memoryPeak);

        // Check for slow requests
        $slowThreshold = config('monitoring.performance.slow_api_threshold_ms', 3000);
        if ($duration > $slowThreshold) {
            $this->alertSlowRequest($request, $duration, $slowThreshold);
        }

        // Add performance headers in debug mode
        if (config('app.debug')) {
            $response->headers->set('X-Response-Time', round($duration, 2) . 'ms');
            $response->headers->set('X-Memory-Usage', $this->formatBytes($memoryUsed));
        }

        return $response;
    }

    /**
     * Log performance metrics for the request.
     *
     * @param Request $request
     * @param Response $response
     * @param float $duration Duration in milliseconds
     * @param int $memoryUsed Memory used in bytes
     * @param int $memoryPeak Peak memory usage in bytes
     * @return void
     */
    private function logPerformanceMetrics(
        Request $request,
        Response $response,
        float $duration,
        int $memoryUsed,
        int $memoryPeak
    ): void {
        // Only log if performance monitoring is enabled
        if (!config('monitoring.performance.enabled', true)) {
            return;
        }

        $logData = [
            'event' => 'request.performance',
            'method' => $request->method(),
            'path' => $request->path(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'memory_peak_mb' => round($memoryPeak / 1024 / 1024, 2),
            'user_id' => $request->user()?->id,
        ];

        // Add query parameters for subscription routes
        if (str_contains($request->path(), 'subscription')) {
            $logData['query_params'] = $request->query();
        }

        Log::info('Request performance metrics', $logData);
    }

    /**
     * Alert on slow request.
     *
     * @param Request $request
     * @param float $duration Duration in milliseconds
     * @param float $threshold Threshold in milliseconds
     * @return void
     */
    private function alertSlowRequest(Request $request, float $duration, float $threshold): void
    {
        Log::warning('Slow request detected', [
            'alert' => 'slow_request',
            'method' => $request->method(),
            'path' => $request->path(),
            'duration_ms' => round($duration, 2),
            'threshold_ms' => $threshold,
            'user_id' => $request->user()?->id,
            'severity' => 'medium',
        ]);
    }

    /**
     * Format bytes to human-readable format.
     *
     * @param int $bytes
     * @return string
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
