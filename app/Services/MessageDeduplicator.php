<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class MessageDeduplicator
{
    /**
     * Check if message is a duplicate.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @param  string  $messageContent  The message text content
     * @return bool True if duplicate, false if unique
     */
    public function isDuplicate(string $phoneNumber, string $messageContent): bool
    {
        try {
            $key = $this->getKey($phoneNumber, $messageContent);
            $exists = Cache::has($key);

            Log::info('Deduplication check', [
                'phone_number' => $phoneNumber,
                'content_hash' => $this->getContentHash($messageContent),
                'is_duplicate' => $exists,
            ]);

            return $exists;
        } catch (\Exception $e) {
            Log::error('Deduplication check failed', [
                'operation' => 'deduplication_check',
                'error_type' => get_class($e),
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Graceful degradation: treat as unique on error
            return false;
        }
    }

    /**
     * Mark message as seen to prevent future duplicates.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @param  string  $messageContent  The message text content
     */
    public function markAsSeen(string $phoneNumber, string $messageContent): void
    {
        try {
            $key = $this->getKey($phoneNumber, $messageContent);
            $ttl = $this->getTtl();

            // Store marker with TTL
            Cache::put($key, '1', $ttl);

            Log::info('Message marked as seen', [
                'phone_number' => $phoneNumber,
                'content_hash' => $this->getContentHash($messageContent),
                'ttl_seconds' => $ttl,
            ]);
        } catch (\Exception $e) {
            Log::error('Mark as seen failed', [
                'operation' => 'mark_as_seen',
                'error_type' => get_class($e),
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Continue processing even if marking fails
        }
    }

    /**
     * Get the Redis key for deduplication.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @param  string  $messageContent  The message text content
     * @return string Redis key
     */
    protected function getKey(string $phoneNumber, string $messageContent): string
    {
        $contentHash = $this->getContentHash($messageContent);

        return "dedup#{$phoneNumber}#{$contentHash}";
    }

    /**
     * Generate MD5 hash of message content.
     *
     * @param  string  $messageContent  The message text content
     * @return string MD5 hash
     */
    protected function getContentHash(string $messageContent): string
    {
        return md5($messageContent);
    }

    /**
     * Get the TTL in seconds from configuration.
     *
     * @return int TTL in seconds
     */
    protected function getTtl(): int
    {
        return $this->validatePositiveInteger(
            config('ai_agent.anti_spam.deduplication.ttl_seconds', 300),
            300,
            'deduplication.ttl_seconds'
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
