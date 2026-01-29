<?php

namespace App\Services;

use App\Jobs\ProcessBufferedMessages;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use Illuminate\Support\Facades\Log;

class AntiSpamWorkflow
{
    /**
     * Create a new AntiSpamWorkflow instance.
     */
    public function __construct(
        protected RateLimiter $rateLimiter,
        protected MessageDeduplicator $messageDeduplicator,
        protected SeenStatusSender $seenStatusSender,
        protected MessageBuffer $messageBuffer
    ) {}

    /**
     * Validate incoming message through anti-spam workflow.
     *
     * @param  WhatsAppAccount  $account  The WhatsApp account
     * @param  WhatsAppContact  $contact  The contact sending the message
     * @param  string  $messageContent  The message text
     * @param  string  $messageId  The WhatsApp message ID
     * @return bool True if message should be processed immediately, false if buffered/rejected
     */
    public function validate(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageContent,
        string $messageId
    ): bool {
        $phoneNumber = $contact->wa_id;

        Log::info('Anti-spam workflow started', [
            'phone_number' => $phoneNumber,
            'message_id' => $messageId,
            'account_id' => $account->id,
        ]);

        // Step 1: Rate limiting check
        if (! $this->checkRateLimit($phoneNumber)) {
            Log::info('Message rejected: rate limit exceeded', [
                'phone_number' => $phoneNumber,
                'message_id' => $messageId,
            ]);

            return false;
        }

        // Step 2: Message deduplication check (exact duplicate within TTL)
        if ($this->checkDuplicate($phoneNumber, $messageContent)) {
            Log::info('Message rejected: duplicate detected', [
                'phone_number' => $phoneNumber,
                'message_id' => $messageId,
            ]);

            return false;
        }

        // Step 3: Send seen status (non-blocking)
        $this->sendSeenStatus($account, $messageId);

        // Step 4: Check if message buffering is enabled
        if ($this->isBufferingEnabled()) {
            return $this->handleBufferedMessage($account, $contact, $messageContent, $messageId);
        }

        // Step 5: Mark message as seen for deduplication (non-buffered flow)
        $this->messageDeduplicator->markAsSeen($phoneNumber, $messageContent);

        // Step 6: Increment rate limit counter
        $this->rateLimiter->incrementCounter($phoneNumber);

        Log::info('Anti-spam workflow completed: message approved (non-buffered)', [
            'phone_number' => $phoneNumber,
            'message_id' => $messageId,
        ]);

        return true;
    }

    /**
     * Handle message with buffering/debounce mechanism.
     *
     * @param  WhatsAppAccount  $account  The WhatsApp account
     * @param  WhatsAppContact  $contact  The contact sending the message
     * @param  string  $messageContent  The message text
     * @param  string  $messageId  The WhatsApp message ID
     * @return bool Always returns false (message will be processed via buffer job)
     */
    protected function handleBufferedMessage(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageContent,
        string $messageId
    ): bool {
        $phoneNumber = $contact->wa_id;

        // Push message to buffer FIRST
        $this->messageBuffer->push($phoneNumber, $messageContent);

        // Get buffer size after push for logging
        $bufferSize = $this->messageBuffer->getBufferSize($phoneNumber);

        // Mark message as seen for deduplication
        $this->messageDeduplicator->markAsSeen($phoneNumber, $messageContent);

        // Increment rate limit counter
        $this->rateLimiter->incrementCounter($phoneNumber);

        // Start debounce timer - only dispatch job if this is the first message
        $isFirstMessage = $this->messageBuffer->startDebounce($phoneNumber);

        if ($isFirstMessage) {
            // Dispatch delayed job to process buffered messages
            $debounceSeconds = $this->getDebounceSeconds();

            ProcessBufferedMessages::dispatch($account, $contact)
                ->onQueue('ai-agent')
                ->delay(now()->addSeconds($debounceSeconds));

            Log::info('Buffered message: job scheduled', [
                'phone_number' => $phoneNumber,
                'message_id' => $messageId,
                'message_content' => $messageContent,
                'debounce_seconds' => $debounceSeconds,
                'buffer_size' => $bufferSize,
                'job_will_run_at' => now()->addSeconds($debounceSeconds)->toDateTimeString(),
            ]);
        } else {
            // Extend debounce timer for subsequent messages
            $this->messageBuffer->extendDebounce($phoneNumber);

            Log::info('Buffered message: added to existing buffer', [
                'phone_number' => $phoneNumber,
                'message_id' => $messageId,
                'message_content' => $messageContent,
                'buffer_size' => $bufferSize,
            ]);
        }

        // Return false - message will be processed via ProcessBufferedMessages job
        return false;
    }

    /**
     * Check if user has exceeded rate limit.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @return bool True if under limit, false if exceeded
     */
    protected function checkRateLimit(string $phoneNumber): bool
    {
        try {
            // Check if rate limiting is enabled
            if (! $this->isRateLimitEnabled()) {
                Log::info('Rate limiting disabled in configuration', [
                    'phone_number' => $phoneNumber,
                ]);

                return true;
            }

            Log::info('Checking rate limit', [
                'phone_number' => $phoneNumber,
            ]);

            $isUnderLimit = $this->rateLimiter->checkLimit($phoneNumber);

            Log::info('Rate limit check result', [
                'phone_number' => $phoneNumber,
                'under_limit' => $isUnderLimit,
            ]);

            return $isUnderLimit;
        } catch (\Exception $e) {
            Log::error('Rate limit check failed in workflow', [
                'operation' => 'workflow_rate_limit_check',
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
     * Check if message is a duplicate.
     *
     * @param  string  $phoneNumber  User's WhatsApp phone number
     * @param  string  $messageContent  The message text content
     * @return bool True if duplicate, false if unique
     */
    protected function checkDuplicate(string $phoneNumber, string $messageContent): bool
    {
        try {
            // Check if deduplication is enabled
            if (! $this->isDeduplicationEnabled()) {
                Log::info('Deduplication disabled in configuration', [
                    'phone_number' => $phoneNumber,
                ]);

                return false;
            }

            Log::info('Checking for duplicate message', [
                'phone_number' => $phoneNumber,
            ]);

            $isDuplicate = $this->messageDeduplicator->isDuplicate($phoneNumber, $messageContent);

            Log::info('Duplicate check result', [
                'phone_number' => $phoneNumber,
                'is_duplicate' => $isDuplicate,
            ]);

            return $isDuplicate;
        } catch (\Exception $e) {
            Log::error('Duplicate check failed in workflow', [
                'operation' => 'workflow_duplicate_check',
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
     * Send seen status to WhatsApp (non-blocking).
     *
     * @param  WhatsAppAccount  $account  The WhatsApp account
     * @param  string  $messageId  The WhatsApp message ID
     */
    protected function sendSeenStatus(WhatsAppAccount $account, string $messageId): void
    {
        try {
            Log::info('Sending seen status', [
                'message_id' => $messageId,
                'account_id' => $account->id,
            ]);

            $success = $this->seenStatusSender->sendSeenStatus($account, $messageId);

            if ($success) {
                Log::info('Seen status sent successfully', [
                    'message_id' => $messageId,
                ]);
            } else {
                Log::warning('Seen status failed to send', [
                    'operation' => 'workflow_send_seen_status',
                    'message_id' => $messageId,
                    'timestamp' => now(),
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Seen status exception in workflow', [
                'operation' => 'workflow_send_seen_status',
                'error_type' => get_class($e),
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Non-blocking: continue processing even if seen status fails
        }
    }

    /**
     * Check if rate limiting is enabled in configuration.
     *
     * @return bool True if enabled, false otherwise
     */
    protected function isRateLimitEnabled(): bool
    {
        return $this->validateBooleanConfig(
            config('ai_agent.anti_spam.rate_limit.enabled', true),
            true,
            'rate_limit.enabled'
        );
    }

    /**
     * Check if deduplication is enabled in configuration.
     *
     * @return bool True if enabled, false otherwise
     */
    protected function isDeduplicationEnabled(): bool
    {
        return $this->validateBooleanConfig(
            config('ai_agent.anti_spam.deduplication.enabled', true),
            true,
            'deduplication.enabled'
        );
    }

    /**
     * Check if message buffering is enabled in configuration.
     *
     * @return bool True if enabled, false otherwise
     */
    protected function isBufferingEnabled(): bool
    {
        return $this->validateBooleanConfig(
            config('ai_agent.anti_spam.buffer.enabled', true),
            true,
            'buffer.enabled'
        );
    }

    /**
     * Get debounce delay in seconds from configuration.
     *
     * @return int Debounce seconds
     */
    protected function getDebounceSeconds(): int
    {
        $value = config('ai_agent.anti_spam.buffer.debounce_seconds', 2);

        if (! is_int($value) || $value <= 0) {
            Log::warning('Invalid buffer.debounce_seconds config', [
                'configured_value' => $value,
                'default_value' => 2,
            ]);

            return 2;
        }

        return $value;
    }

    /**
     * Validate that a configuration value is a boolean.
     *
     * @param  mixed  $value  The configuration value to validate
     * @param  bool  $default  The default value to use if validation fails
     * @param  string  $configKey  The configuration key name for logging
     * @return bool The validated value or default
     */
    protected function validateBooleanConfig($value, bool $default, string $configKey): bool
    {
        // Check if value is a boolean
        if (! is_bool($value)) {
            Log::warning('Invalid configuration value: not a boolean', [
                'config_key' => $configKey,
                'configured_value' => $value,
                'configured_type' => gettype($value),
                'default_value' => $default,
                'timestamp' => now(),
            ]);

            return $default;
        }

        return $value;
    }
}
