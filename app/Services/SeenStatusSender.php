<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SeenStatusSender
{
    /**
     * Send seen status to WhatsApp for a message.
     *
     * @param  WhatsAppAccount  $account  The WhatsApp account
     * @param  string  $messageId  The WhatsApp message ID
     * @return bool True if successful, false otherwise
     */
    public function sendSeenStatus(WhatsAppAccount $account, string $messageId): bool
    {
        try {
            // Check if seen status is enabled in configuration
            if (! $this->isEnabled()) {
                Log::info('Seen status disabled in configuration', [
                    'message_id' => $messageId,
                ]);

                return false;
            }

            $phoneNumberId = $account->phone_number_id;
            $accessToken = $account->access_token;

            if (! $phoneNumberId || ! $accessToken) {
                Log::warning('Missing phone number ID or access token', [
                    'operation' => 'send_seen_status',
                    'message_id' => $messageId,
                    'account_id' => $account->id,
                    'has_phone_number_id' => ! empty($phoneNumberId),
                    'has_access_token' => ! empty($accessToken),
                    'timestamp' => now(),
                ]);

                return false;
            }

            $url = "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages";

            $payload = [
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ];

            Log::info('Sending seen status', [
                'message_id' => $messageId,
                'phone_number_id' => $phoneNumberId,
            ]);

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, $payload);

            if ($response->successful()) {
                Log::info('Seen status sent successfully', [
                    'message_id' => $messageId,
                ]);

                return true;
            }

            Log::warning('Failed to send seen status', [
                'operation' => 'send_seen_status',
                'error_type' => 'http_error',
                'message_id' => $messageId,
                'status_code' => $response->status(),
                'response' => $response->body(),
                'timestamp' => now(),
            ]);

            return false;
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Seen status connection exception', [
                'operation' => 'send_seen_status',
                'error_type' => 'connection_exception',
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ]);

            return false;
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::warning('Seen status request exception', [
                'operation' => 'send_seen_status',
                'error_type' => 'request_exception',
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'timestamp' => now(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::warning('Seen status unexpected exception', [
                'operation' => 'send_seen_status',
                'error_type' => get_class($e),
                'message_id' => $messageId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'timestamp' => now(),
            ]);

            // Always return false on exception, but don't throw
            return false;
        }
    }

    /**
     * Check if seen status is enabled in configuration.
     *
     * @return bool True if enabled, false otherwise
     */
    protected function isEnabled(): bool
    {
        return $this->validateBooleanConfig(
            config('ai_agent.anti_spam.seen_status.enabled', true),
            true,
            'seen_status.enabled'
        );
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
