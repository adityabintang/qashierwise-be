<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate limiting middleware for QRIS generation endpoints.
 * 
 * Implements user-specific rate limits to prevent abuse of QRIS generation.
 * Requirement 9.3: Implement rate limiting for QRIS generation
 */
class QrisRateLimiter
{
    /**
     * Maximum number of QRIS generation requests per minute per user.
     */
    public const MAX_REQUESTS_PER_MINUTE = 10;

    /**
     * Maximum number of QRIS generation requests per hour per user.
     */
    public const MAX_REQUESTS_PER_HOUR = 60;

    /**
     * Decay time in seconds for per-minute limit.
     */
    public const DECAY_MINUTES = 60;

    /**
     * Decay time in seconds for per-hour limit.
     */
    public const DECAY_HOURS = 3600;

    public function __construct(
        private RateLimiter $limiter,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required',
                ],
            ], 401);
        }

        $userId = $user->id;
        $minuteKey = $this->getMinuteRateLimitKey($userId);
        $hourKey = $this->getHourRateLimitKey($userId);

        // Check per-minute rate limit
        if ($this->limiter->tooManyAttempts($minuteKey, self::MAX_REQUESTS_PER_MINUTE)) {
            return $this->buildRateLimitResponse(
                $minuteKey,
                self::MAX_REQUESTS_PER_MINUTE,
                'minute',
                $userId
            );
        }

        // Check per-hour rate limit
        if ($this->limiter->tooManyAttempts($hourKey, self::MAX_REQUESTS_PER_HOUR)) {
            return $this->buildRateLimitResponse(
                $hourKey,
                self::MAX_REQUESTS_PER_HOUR,
                'hour',
                $userId
            );
        }

        // Increment both rate limiters
        $this->limiter->hit($minuteKey, self::DECAY_MINUTES);
        $this->limiter->hit($hourKey, self::DECAY_HOURS);

        $response = $next($request);

        // Add rate limit headers to response
        return $this->addRateLimitHeaders($response, $minuteKey, $hourKey);
    }

    /**
     * Get the rate limit key for per-minute limiting.
     */
    private function getMinuteRateLimitKey(int $userId): string
    {
        return "qris_rate_limit:minute:{$userId}";
    }

    /**
     * Get the rate limit key for per-hour limiting.
     */
    private function getHourRateLimitKey(int $userId): string
    {
        return "qris_rate_limit:hour:{$userId}";
    }

    /**
     * Build the rate limit exceeded response.
     */
    private function buildRateLimitResponse(
        string $key,
        int $maxAttempts,
        string $period,
        int $userId
    ): Response {
        $retryAfter = $this->limiter->availableIn($key);

        Log::warning('QRIS rate limit exceeded', [
            'user_id' => $userId,
            'period' => $period,
            'max_attempts' => $maxAttempts,
            'retry_after' => $retryAfter,
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'RATE_LIMIT_EXCEEDED',
                'message' => "Too many QRIS generation requests. Please try again later.",
            ],
            'rate_limit' => [
                'limit' => $maxAttempts,
                'period' => $period,
                'retry_after_seconds' => $retryAfter,
            ],
        ], 429)->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Add rate limit headers to the response.
     */
    private function addRateLimitHeaders(
        Response $response,
        string $minuteKey,
        string $hourKey
    ): Response {
        $minuteRemaining = $this->limiter->remaining($minuteKey, self::MAX_REQUESTS_PER_MINUTE);
        $hourRemaining = $this->limiter->remaining($hourKey, self::MAX_REQUESTS_PER_HOUR);

        $response->headers->set('X-RateLimit-Limit-Minute', (string) self::MAX_REQUESTS_PER_MINUTE);
        $response->headers->set('X-RateLimit-Remaining-Minute', (string) max(0, $minuteRemaining));
        $response->headers->set('X-RateLimit-Limit-Hour', (string) self::MAX_REQUESTS_PER_HOUR);
        $response->headers->set('X-RateLimit-Remaining-Hour', (string) max(0, $hourRemaining));

        return $response;
    }

    /**
     * Get the number of remaining attempts for a user (per minute).
     */
    public function getRemainingAttempts(int $userId): int
    {
        $key = $this->getMinuteRateLimitKey($userId);
        return $this->limiter->remaining($key, self::MAX_REQUESTS_PER_MINUTE);
    }

    /**
     * Clear rate limit for a specific user (useful for testing).
     */
    public function clearRateLimit(int $userId): void
    {
        $this->limiter->clear($this->getMinuteRateLimitKey($userId));
        $this->limiter->clear($this->getHourRateLimitKey($userId));
    }
}
