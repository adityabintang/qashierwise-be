<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageStatusUpdated;
use App\Events\NewWhatsAppMessage;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Verify webhook (GET request from WhatsApp)
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Check if a token and mode were sent
        if ($mode && $token) {
            // Check the mode and token sent are correct
            if ($mode === 'subscribe' && $token === config('whatsapp.webhook_verify_token')) {
                // Respond with 200 OK and challenge token from the request
                Log::info('Webhook verified successfully');
                return response($challenge, 200)->header('Content-Type', 'text/plain');
            } else {
                // Responds with '403 Forbidden' if verify tokens do not match
                Log::warning('Webhook verification failed - invalid token');
                return response()->json(['error' => 'Forbidden'], 403);
            }
        }

        return response()->json(['error' => 'Bad Request'], 400);
    }

    /**
     * Handle incoming webhook events (POST request from WhatsApp)
     */
    public function handle(Request $request)
    {
        try {
            $body = $request->all();

            Log::info('Webhook received', ['body' => $body]);

            // Check if this is a WhatsApp webhook event
            if (isset($body['object']) && $body['object'] === 'whatsapp_business_account') {

                // Iterate through entries
                foreach ($body['entry'] as $entry) {
                    foreach ($entry['changes'] as $change) {

                        $phoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;

                        if (!$phoneNumberId) {
                            continue;
                        }

                        // Find WhatsApp account and user
                        $whatsappAccount = WhatsAppAccount::where('phone_number_id', $phoneNumberId)
                            ->where('is_active', true)
                            ->first();

                        if (!$whatsappAccount) {
                            Log::warning('WhatsApp account not found', ['phone_number_id' => $phoneNumberId]);
                            continue;
                        }

                        $userId = $whatsappAccount->user_id;

                        // Handle message events
                        if (isset($change['value']['messages'])) {
                            foreach ($change['value']['messages'] as $message) {
                                $this->handleIncomingMessage($message, $change['value'], $userId);
                            }
                        }

                        // Handle status updates
                        if (isset($change['value']['statuses'])) {
                            foreach ($change['value']['statuses'] as $status) {
                                $this->handleMessageStatus($status, $userId);
                            }
                        }
                    }
                }

                return response()->json(['status' => 'success'], 200);
            }

            return response()->json(['status' => 'ignored'], 200);

        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Handle incoming message
     */
    protected function handleIncomingMessage($message, $value, $userId)
    {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];
        $type = $message['type'];

        Log::info("Incoming $type message from $from", [
            'message_id' => $messageId,
            'user_id' => $userId
        ]);

        // Get or create contact
        $contactName = $value['contacts'][0]['profile']['name'] ?? $from;

        $contact = WhatsAppContact::firstOrCreate(
            [
                'user_id' => $userId,
                'wa_id' => $from,
            ],
            [
                'name' => $contactName,
            ]
        );

        // Extract message content based on type
        $content = null;
        $metadata = [];

        $messageData = [
            'message_id' => $messageId,
            'from' => $from,
            'timestamp' => $timestamp,
            'type' => $message['type'],
        ];

        // Handle different message types
        switch ($type) {
            case 'text':
                $content = $message['text']['body'];
                break;

            case 'image':
                $content = $message['image']['caption'] ?? 'Image';
                $metadata = [
                    'media_id' => $message['image']['id'],
                    'mime_type' => $message['image']['mime_type'] ?? null,
                ];
                break;

            case 'document':
                $content = $message['document']['filename'] ?? 'Document';
                $metadata = [
                    'media_id' => $message['document']['id'],
                    'mime_type' => $message['document']['mime_type'] ?? null,
                    'caption' => $message['document']['caption'] ?? null,
                ];
                break;

            case 'audio':
                $content = 'Audio message';
                $metadata = [
                    'media_id' => $message['audio']['id'],
                    'mime_type' => $message['audio']['mime_type'] ?? null,
                ];
                break;

            case 'video':
                $content = $message['video']['caption'] ?? 'Video';
                $metadata = [
                    'media_id' => $message['video']['id'],
                    'mime_type' => $message['video']['mime_type'] ?? null,
                ];
                break;

            case 'location':
                $location = $message['location'];
                $content = "Location: {$location['latitude']}, {$location['longitude']}";
                $metadata = $location;
                break;

            case 'contacts':
                $content = 'Contact shared';
                $metadata = $message['contacts'];
                break;

            case 'button':
                $content = $message['button']['text'] ?? 'Button clicked';
                $metadata = [
                    'payload' => $message['button']['payload'] ?? null,
                ];
                break;

            case 'interactive':
                if (isset($message['interactive']['type'])) {
                    $interactiveType = $message['interactive']['type'];
                    if ($interactiveType === 'button_reply') {
                        $content = $message['interactive']['button_reply']['title'] ?? 'Button';
                        $metadata = $message['interactive']['button_reply'];
                    } elseif ($interactiveType === 'list_reply') {
                        $content = $message['interactive']['list_reply']['title'] ?? 'List item';
                        $metadata = $message['interactive']['list_reply'];
                    }
                }
                break;

            default:
                $content = "Unsupported message type: $type";
                $metadata = $message;
                break;
        }

        // Save message to database (use updateOrCreate to prevent duplicate errors)
        $whatsappMessage = WhatsAppMessage::updateOrCreate(
            ['message_id' => $messageId],
            [
                'user_id' => $userId,
                'contact_id' => $contact->id,
                'direction' => 'incoming',
                'type' => $type,
                'content' => $content,
                'metadata' => $metadata,
                'status' => 'delivered',
                'is_read' => false,
                'sent_at' => now()->timestamp($timestamp),
                'delivered_at' => now(),
            ]
        );

        // Update contact's last message info
        $contact->update([
            'last_message_at' => now(),
            'last_message_text' => $content,
            'unread_count' => $contact->unread_count + 1,
        ]);

        // Broadcast event to frontend
        broadcast(new NewWhatsAppMessage($whatsappMessage, $contact));

        Log::info('Message saved and broadcasted', [
            'message_id' => $messageId,
            'contact_id' => $contact->id
        ]);

        return $messageData;
    }

    /**
     * Handle message status updates
     */
    protected function handleMessageStatus($status, $userId)
    {
        $messageId = $status['id'];
        $statusValue = $status['status']; // sent, delivered, read, failed
        $timestamp = $status['timestamp'];

        Log::info("Status update: $messageId -> $statusValue");

        // Find message in database
        $message = WhatsAppMessage::where('message_id', $messageId)
            ->where('user_id', $userId)
            ->first();

        if (!$message) {
            Log::warning('Message not found for status update', ['message_id' => $messageId]);
            return;
        }

        // Update message status
        $updateData = ['status' => $statusValue];

        if ($statusValue === 'delivered') {
            $updateData['delivered_at'] = now()->timestamp($timestamp);
        } elseif ($statusValue === 'read') {
            $updateData['read_at'] = now()->timestamp($timestamp);
            $updateData['delivered_at'] = $updateData['delivered_at'] ?? now();
        }

        $message->update($updateData);

        // Broadcast status update to frontend
        broadcast(new MessageStatusUpdated($message));

        Log::info('Message status updated and broadcasted', [
            'message_id' => $messageId,
            'status' => $statusValue
        ]);

        if ($statusValue === 'failed' && isset($status['errors'])) {
            Log::error('Message delivery failed', [
                'message_id' => $messageId,
                'errors' => $status['errors']
            ]);
        }
    }
}
