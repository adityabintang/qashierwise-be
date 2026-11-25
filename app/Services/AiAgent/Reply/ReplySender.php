<?php

namespace App\Services\AiAgent\Reply;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\WhatsAppMessageHistory;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Thin wrapper around the WhatsApp text-send API.
 *
 * Centralises:
 *   - SDK client construction (was inlined at every send site)
 *   - exception swallowing + structured logging
 *
 * Send failures are logged as errors but never re-thrown — a transient outbound
 * failure should not crash the whole AI agent pipeline. Callers that need to
 * branch on success/failure can switch to `sendOrFail()` (TBD).
 */
class ReplySender
{
    /**
     * Send a plain-text WhatsApp reply.
     *
     * @param  WhatsAppAccount  $account  The merchant's connected WhatsApp account.
     * @param  string  $to  Customer's wa_id (digits, no '+').
     * @param  string  $message  Plain text body. Caller is responsible for length.
     */
    public function send(WhatsAppAccount $account, string $to, string $message): void
    {
        try {
            $client = new WhatsAppCloudApi([
                'from_phone_number_id' => $account->phone_number_id,
                'access_token' => $account->access_token,
            ]);

            $response = $client->sendTextMessage($to, $message);
            rescue(function () use ($account, $to, $message, $response) {
                $contact = WhatsAppContact::withoutGlobalScopes()
                    ->where('user_id', $account->user_id)
                    ->where('phone_number_id', $account->phone_number_id)
                    ->where('wa_id', $to)
                    ->first();

                if ($contact) {
                    WhatsAppMessageHistory::recordText($contact, $response, $message);
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to send WhatsApp reply', [
                'error' => $e->getMessage(),
                'to' => $to,
            ]);
        }
    }
}
