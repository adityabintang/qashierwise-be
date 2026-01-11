<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Message Buffer Service
 * 
 * Implements debounce/buffer mechanism to handle rapid consecutive messages.
 * This prevents duplicate AI responses when:
 * - WhatsApp sends duplicate webhook events
 * - User sends multiple messages quickly (spam enter)
 * - Voice note + text timing issues
 * 
 * Flow:
 * 1. Push message to buffer (Redis list or Cache)
 * 2. Set/reset debounce timer
 * 3. After debounce period, collect all buffered messages
 * 4. Merge similar messages and process once
 */
class MessageBuffer
{
    /**
     * Default debounce delay in seconds
     */
    protected const DEFAULT_DEBOUNCE_SECONDS = 2;

    /**
     * Default buffer TTL in seconds (auto-cleanup)
     */
    protected const DEFAULT_BUFFER_TTL = 60;

    /**
     * Add message to buffer for a specific user.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @param string $messageContent The message text
     * @return void
     */
    public function push(string $phoneNumber, string $messageContent): void
    {
        try {
            $bufferKey = $this->getBufferKey($phoneNumber);
            $ttl = $this->getBufferTtl();
            
            // Get existing buffer or create new
            $buffer = Cache::get($bufferKey, []);
            $buffer[] = $messageContent;
            
            // Store updated buffer with TTL
            Cache::put($bufferKey, $buffer, $ttl);
            
            Log::info('Message pushed to buffer', [
                'phone_number' => $phoneNumber,
                'buffer_key' => $bufferKey,
                'buffer_size' => count($buffer),
                'message_preview' => substr($messageContent, 0, 50),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to push message to buffer', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Check if user is currently in debounce period.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return bool True if in debounce period (should wait), false if ready to process
     */
    public function isDebouncing(string $phoneNumber): bool
    {
        $lockKey = $this->getLockKey($phoneNumber);
        return Cache::has($lockKey);
    }

    /**
     * Start debounce timer for a user.
     * Returns true if this is the first message (should schedule processing).
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return bool True if debounce timer was started (first message), false if already debouncing
     */
    public function startDebounce(string $phoneNumber): bool
    {
        $lockKey = $this->getLockKey($phoneNumber);
        $debounceSeconds = $this->getDebounceSeconds();
        
        // Use atomic add - returns true only if key didn't exist
        $isFirst = Cache::add($lockKey, time(), $debounceSeconds);
        
        Log::info('Debounce check', [
            'phone_number' => $phoneNumber,
            'is_first_message' => $isFirst,
            'debounce_seconds' => $debounceSeconds,
        ]);
        
        return $isFirst;
    }

    /**
     * Get all buffered messages for a user and clear the buffer.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return array Array of message strings
     */
    public function flush(string $phoneNumber): array
    {
        try {
            $bufferKey = $this->getBufferKey($phoneNumber);
            
            // Get all messages from buffer
            $messages = Cache::get($bufferKey, []);
            
            // Clear the buffer
            Cache::forget($bufferKey);
            
            // Clear the debounce lock
            $lockKey = $this->getLockKey($phoneNumber);
            Cache::forget($lockKey);
            
            Log::info('Buffer flushed', [
                'phone_number' => $phoneNumber,
                'message_count' => count($messages),
            ]);
            
            return $messages;
        } catch (\Exception $e) {
            Log::error('Failed to flush buffer', [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Merge buffered messages into a single message.
     * Handles deduplication and concatenation.
     *
     * @param array $messages Array of message strings
     * @return string|null Merged message or null if empty
     */
    public function mergeMessages(array $messages): ?string
    {
        if (empty($messages)) {
            return null;
        }
        
        // Remove exact duplicates while preserving order
        $uniqueMessages = [];
        $seen = [];
        
        foreach ($messages as $message) {
            $normalized = $this->normalizeMessage($message);
            if (!isset($seen[$normalized])) {
                $seen[$normalized] = true;
                $uniqueMessages[] = $message;
            }
        }
        
        Log::info('Messages merged', [
            'original_count' => count($messages),
            'unique_count' => count($uniqueMessages),
            'duplicates_removed' => count($messages) - count($uniqueMessages),
        ]);
        
        // If all messages are the same, return just one
        if (count($uniqueMessages) === 1) {
            return $uniqueMessages[0];
        }
        
        // Join unique messages with space
        return implode(' ', $uniqueMessages);
    }

    /**
     * Get buffer size for a user.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return int Number of messages in buffer
     */
    public function getBufferSize(string $phoneNumber): int
    {
        try {
            $bufferKey = $this->getBufferKey($phoneNumber);
            $buffer = Cache::get($bufferKey, []);
            return count($buffer);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Normalize message for comparison (lowercase, trim whitespace).
     *
     * @param string $message The message to normalize
     * @return string Normalized message
     */
    protected function normalizeMessage(string $message): string
    {
        return strtolower(trim($message));
    }

    /**
     * Get cache key for message buffer.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return string Cache key
     */
    protected function getBufferKey(string $phoneNumber): string
    {
        return "msg_buffer:{$phoneNumber}";
    }

    /**
     * Get cache key for debounce lock.
     *
     * @param string $phoneNumber User's WhatsApp phone number
     * @return string Cache key
     */
    protected function getLockKey(string $phoneNumber): string
    {
        return "msg_debounce:{$phoneNumber}";
    }

    /**
     * Get debounce delay in seconds from config.
     *
     * @return int Debounce seconds
     */
    protected function getDebounceSeconds(): int
    {
        return (int) config('ai_agent.anti_spam.buffer.debounce_seconds', self::DEFAULT_DEBOUNCE_SECONDS);
    }

    /**
     * Get buffer TTL in seconds from config.
     *
     * @return int Buffer TTL seconds
     */
    protected function getBufferTtl(): int
    {
        return (int) config('ai_agent.anti_spam.buffer.ttl_seconds', self::DEFAULT_BUFFER_TTL);
    }
}
