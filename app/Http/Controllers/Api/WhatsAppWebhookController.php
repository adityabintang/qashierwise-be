<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
                        
                        // Handle message events
                        if (isset($change['value']['messages'])) {
                            foreach ($change['value']['messages'] as $message) {
                                $this->handleIncomingMessage($message, $change['value']);
                            }
                        }

                        // Handle status updates
                        if (isset($change['value']['statuses'])) {
                            foreach ($change['value']['statuses'] as $status) {
                                $this->handleMessageStatus($status);
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
    protected function handleIncomingMessage($message, $value)
    {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];

        $messageData = [
            'message_id' => $messageId,
            'from' => $from,
            'timestamp' => $timestamp,
            'type' => $message['type'],
        ];

        // Handle different message types
        switch ($message['type']) {
            case 'text':
                $messageData['text'] = $message['text']['body'];
                Log::info('Text message received', $messageData);
                // TODO: Save to database and process
                break;

            case 'image':
                $messageData['image_id'] = $message['image']['id'];
                $messageData['caption'] = $message['image']['caption'] ?? null;
                $messageData['mime_type'] = $message['image']['mime_type'];
                Log::info('Image message received', $messageData);
                // TODO: Download and save image
                break;

            case 'document':
                $messageData['document_id'] = $message['document']['id'];
                $messageData['filename'] = $message['document']['filename'];
                $messageData['caption'] = $message['document']['caption'] ?? null;
                $messageData['mime_type'] = $message['document']['mime_type'];
                Log::info('Document message received', $messageData);
                // TODO: Download and save document
                break;

            case 'audio':
                $messageData['audio_id'] = $message['audio']['id'];
                $messageData['mime_type'] = $message['audio']['mime_type'];
                Log::info('Audio message received', $messageData);
                // TODO: Download and save audio
                break;

            case 'video':
                $messageData['video_id'] = $message['video']['id'];
                $messageData['caption'] = $message['video']['caption'] ?? null;
                $messageData['mime_type'] = $message['video']['mime_type'];
                Log::info('Video message received', $messageData);
                // TODO: Download and save video
                break;

            case 'location':
                $messageData['latitude'] = $message['location']['latitude'];
                $messageData['longitude'] = $message['location']['longitude'];
                $messageData['name'] = $message['location']['name'] ?? null;
                $messageData['address'] = $message['location']['address'] ?? null;
                Log::info('Location message received', $messageData);
                // TODO: Save location
                break;

            case 'contacts':
                $messageData['contacts'] = $message['contacts'];
                Log::info('Contact message received', $messageData);
                // TODO: Save contacts
                break;

            case 'button':
                $messageData['button_text'] = $message['button']['text'];
                $messageData['button_payload'] = $message['button']['payload'];
                Log::info('Button reply received', $messageData);
                // TODO: Process button response
                break;

            case 'interactive':
                if (isset($message['interactive']['button_reply'])) {
                    $messageData['button_reply'] = $message['interactive']['button_reply'];
                }
                if (isset($message['interactive']['list_reply'])) {
                    $messageData['list_reply'] = $message['interactive']['list_reply'];
                }
                Log::info('Interactive message received', $messageData);
                // TODO: Process interactive response
                break;

            default:
                Log::warning('Unknown message type', $messageData);
                break;
        }

        // Check if message has context (is a reply)
        if (isset($message['context'])) {
            $messageData['context'] = [
                'message_id' => $message['context']['id'],
                'from' => $message['context']['from'] ?? null,
            ];
            Log::info('Message is a reply', ['context' => $messageData['context']]);
        }

        return $messageData;
    }

    /**
     * Handle message status updates
     */
    protected function handleMessageStatus($status)
    {
        $statusData = [
            'message_id' => $status['id'],
            'status' => $status['status'],
            'timestamp' => $status['timestamp'],
            'recipient_id' => $status['recipient_id'],
        ];

        // Status types: sent, delivered, read, failed
        Log::info('Message status update', $statusData);

        // TODO: Update message status in database

        if ($status['status'] === 'failed' && isset($status['errors'])) {
            Log::error('Message delivery failed', [
                'message_id' => $status['id'],
                'errors' => $status['errors']
            ]);
        }

        return $statusData;
    }
}
