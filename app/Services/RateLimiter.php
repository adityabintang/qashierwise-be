<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimiter
{
    /**
     * Check if user has exceeded rate limit.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @return bool True if under limit, false if exceeded
     */
    public function checkLimit(string $phoneNumber): bool
    {
        try {
            $maxMessages = $this->getMaxMessages();
            $count = $this->getCount($phoneNumber);

            $isUnderLimit = $count < $maxMessages;

            Log::info('Rate limit check', [
                'phone_number' => $phoneNumber,
                'current_count' => $count,
                'max_messages' => $maxMessages,
                'under_limit' => $isUnderLimit,
            ]);

            return $isUnderLimit;
        } catch (\Exception $e) {
            Log::error('Rate limit check failed', [
                'operation' => 'rate_limit_check',
                'error_type' => get_class($e),
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Graceful degradation: allow message on error
            return true;
        }
    }

    /**
     * Increment the message counter for a user.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @return int Current count after increment
     */
    public function incrementCounter(string $phoneNumber): int
    {
        try {
            $key = $this->getKey($phoneNumber);
            $ttl = $this->getTtl();

            // Get current count
            $count = Cache::get($key, 0);

            // Increment counter
            $newCount = $count + 1;

            // Store with TTL
            Cache::put($key, $newCount, $ttl);

            Log::info('Rate limit counter incremented', [
                'phone_number' => $phoneNumber,
                'new_count' => $newCount,
                'ttl_seconds' => $ttl,
            ]);

            return $newCount;
        } catch (\Exception $e) {
            Log::error('Rate limit increment failed', [
                'operation' => 'rate_limit_increment',
                'error_type' => get_class($e),
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Return 0 on error to allow processing
            return 0;
        }
    }

    /**
     * Get current message count for a user.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @return int Current message count
     */
    public function getCount(string $phoneNumber): int
    {
        try {
            $key = $this->getKey($phoneNumber);
            $count = Cache::get($key, 0);

            return (int) $count;
        } catch (\Exception $e) {
            Log::error('Rate limit get count failed', [
                'operation' => 'rate_limit_get_count',
                'error_type' => get_class($e),
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Return 0 on error to allow processing
            return 0;
        }
    }

    /**
     * Get the Redis key for rate limiting.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @return string Redis key
     */
    protected function getKey(string $phoneNumber): string
    {
        return "rateLimit#{$phoneNumber}";
    }

    /**
     * Get the maximum messages allowed from configuration.
     *
     * @return int Maximum messages allowed
     */
    protected function getMaxMessages(): int
    {
        return $this->validatePositiveInteger(
            config('ai_agent.anti_spam.rate_limit.max_messages', 20),
            20,
            'rate_limit.max_messages'
        );
    }

    /**
     * Get the TTL in seconds from configuration.
     *
     * @return int TTL in seconds
     */
    protected function getTtl(): int
    {
        return $this->validatePositiveInteger(
            config('ai_agent.anti_spam.rate_limit.ttl_seconds', 86400),
            86400,
            'rate_limit.ttl_seconds'
        );
    }

    /**
     * Validate that a configuration value is a positive integer.
     *
     * @param  mixed  $value  The configuration value to validate
     * @param  int  $default  The default value to use if validation fails
     * @param  string  $configKey  The configuration key name for logging
     * @return int The validated value or default
     */
    protected function validatePositiveInteger($value, int $default, string $configKey): int
    {
        // Check if value is an integer
        if (! is_int($value)) {
            Log::warning('Invalid configuration value: not an integer', [
                'config_key' => $configKey,
                'configured_value' => $value,
                'configured_type' => gettype($value),
                'default_value' => $default,
                'timestamp' => now(),
            ]);

            return $default;
        }

        // Check if value is positive
        if ($value <= 0) {
            Log::warning('Invalid configuration value: not positive', [
                'config_key' => $configKey,
                'configured_value' => $value,
                'default_value' => $default,
                'timestamp' => now(),
            ]);

            return $default;
        }

        return $value;
    }
}
