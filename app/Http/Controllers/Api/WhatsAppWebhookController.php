<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageStatusUpdated;
use App\Events\NewWhatsAppMessage;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\MediaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppWebhookController extends Controller
{
    protected MediaStorageService $mediaStorageService;

    public function __construct(MediaStorageService $mediaStorageService)
    {
        $this->mediaStorageService = $mediaStorageService;
    }

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
                $mediaId = $message['image']['id'];
                $mimeType = $message['image']['mime_type'] ?? 'image/jpeg';
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'image', $mimeType, null, $from);
                $content = json_encode([
                    'url' => $mediaResult['url'],
                    'caption' => $message['image']['caption'] ?? '',
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                ]);
                $metadata = [
                    'media_id' => $mediaId,
                    'mime_type' => $mimeType,
                    'stored_url' => $mediaResult['url'],
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                    'file_size' => $mediaResult['file_size'],
                ];
                break;

            case 'document':
                $mediaId = $message['document']['id'];
                $mimeType = $message['document']['mime_type'] ?? 'application/octet-stream';
                $filename = $message['document']['filename'] ?? 'document';
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'document', $mimeType, $filename, $from);
                $content = json_encode([
                    'url' => $mediaResult['url'],
                    'filename' => $filename,
                    'caption' => $message['document']['caption'] ?? '',
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                ]);
                $metadata = [
                    'media_id' => $mediaId,
                    'mime_type' => $mimeType,
                    'filename' => $filename,
                    'stored_url' => $mediaResult['url'],
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                    'file_size' => $mediaResult['file_size'],
                ];
                break;

            case 'audio':
                $mediaId = $message['audio']['id'];
                $mimeType = $message['audio']['mime_type'] ?? 'audio/ogg';
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'audio', $mimeType, null, $from);
                $content = json_encode([
                    'url' => $mediaResult['url'],
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                ]);
                $metadata = [
                    'media_id' => $mediaId,
                    'mime_type' => $mimeType,
                    'stored_url' => $mediaResult['url'],
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                    'file_size' => $mediaResult['file_size'],
                ];
                break;

            case 'video':
                $mediaId = $message['video']['id'];
                $mimeType = $message['video']['mime_type'] ?? 'video/mp4';
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'video', $mimeType, null, $from);
                $content = json_encode([
                    'url' => $mediaResult['url'],
                    'caption' => $message['video']['caption'] ?? '',
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                ]);
                $metadata = [
                    'media_id' => $mediaId,
                    'mime_type' => $mimeType,
                    'stored_url' => $mediaResult['url'],
                    'exceeded_limit' => $mediaResult['exceeded_limit'],
                    'file_size' => $mediaResult['file_size'],
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

    /**
     * Download media from WhatsApp and store to R2/local storage
     *
     * @param string $mediaId WhatsApp media ID
     * @param string $type Media type (image, document, audio, video)
     * @param string $mimeType MIME type of the media
     * @param string|null $filename Original filename (for documents)
     * @param string|null $senderPhone Sender phone number for reply
     * @return array{url: string|null, exceeded_limit: bool, file_size: int, max_size: int}
     */
    protected function downloadAndStoreMedia(string $mediaId, string $type, string $mimeType, ?string $filename = null, ?string $senderPhone = null): array
    {
        try {
            $accessToken = config('whatsapp.access_token');

            // Step 1: Get media URL and file size from WhatsApp
            $mediaInfoResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$mediaId}");

            if (!$mediaInfoResponse->successful()) {
                Log::error('Failed to get media info from WhatsApp', [
                    'media_id' => $mediaId,
                    'response' => $mediaInfoResponse->body(),
                ]);
                return null;
            }

            $mediaInfo = $mediaInfoResponse->json();
            $mediaUrl = $mediaInfo['url'] ?? null;
            $fileSize = $mediaInfo['file_size'] ?? 0;

            if (!$mediaUrl) {
                Log::error('Media URL not found in WhatsApp response', ['media_id' => $mediaId]);
                return ['url' => null, 'exceeded_limit' => false, 'file_size' => 0, 'max_size' => 0];
            }

            // Step 2: Check file size limit
            $maxSize = config("whatsapp.media_limits.{$type}", 16 * 1024 * 1024);
            if ($fileSize > $maxSize) {
                Log::warning('Incoming media exceeds size limit, skipping storage', [
                    'media_id' => $mediaId,
                    'type' => $type,
                    'file_size' => $fileSize,
                    'max_size' => $maxSize,
                ]);

                // Send reply to client about file size limit
                if ($senderPhone) {
                    $this->sendFileSizeLimitReply($senderPhone, $type, $fileSize, $maxSize);
                }

                return ['url' => null, 'exceeded_limit' => true, 'file_size' => $fileSize, 'max_size' => $maxSize];
            }

            // Step 3: Download media content
            $mediaResponse = Http::withToken($accessToken)
                ->timeout(60)
                ->get($mediaUrl);

            if (!$mediaResponse->successful()) {
                Log::error('Failed to download media from WhatsApp', [
                    'media_id' => $mediaId,
                    'url' => $mediaUrl,
                ]);
                return null;
            }

            $mediaContent = $mediaResponse->body();

            // Double-check actual content size
            $actualSize = strlen($mediaContent);
            if ($actualSize > $maxSize) {
                Log::warning('Downloaded media exceeds size limit, skipping storage', [
                    'media_id' => $mediaId,
                    'type' => $type,
                    'actual_size' => $actualSize,
                    'max_size' => $maxSize,
                ]);

                if ($senderPhone) {
                    $this->sendFileSizeLimitReply($senderPhone, $type, $actualSize, $maxSize);
                }

                return ['url' => null, 'exceeded_limit' => true, 'file_size' => $actualSize, 'max_size' => $maxSize];
            }

            // Step 3: Generate filename and path
            $extension = $this->getExtensionFromMimeType($mimeType);
            $generatedFilename = $filename ?? "media_{$mediaId}.{$extension}";
            $sanitizedFilename = $this->mediaStorageService->sanitizeFilename($generatedFilename);
            $uniqueFilename = $this->mediaStorageService->generateUniqueFilename($sanitizedFilename);
            $path = $this->mediaStorageService->generatePath($type, $uniqueFilename);

            // Step 4: Store to configured disk (R2 or local)
            $diskName = $this->mediaStorageService->getDiskName();
            Storage::disk($diskName)->put($path, $mediaContent, [
                'ContentType' => $mimeType,
            ]);

            // Step 5: Get public URL
            $publicUrl = $this->mediaStorageService->getPublicUrl($path);

            Log::info('Media downloaded and stored successfully', [
                'media_id' => $mediaId,
                'type' => $type,
                'path' => $path,
                'url' => $publicUrl,
            ]);

            return ['url' => $publicUrl, 'exceeded_limit' => false, 'file_size' => $actualSize, 'max_size' => $maxSize];

        } catch (\Exception $e) {
            Log::error('Failed to download and store media', [
                'media_id' => $mediaId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return ['url' => null, 'exceeded_limit' => false, 'file_size' => 0, 'max_size' => 0];
        }
    }

    /**
     * Send reply to client when file size exceeds limit
     */
    protected function sendFileSizeLimitReply(string $to, string $type, int $fileSize, int $maxSize): void
    {
        try {
            $fileSizeMB = round($fileSize / (1024 * 1024), 2);
            $maxSizeMB = round($maxSize / (1024 * 1024), 2);

            $typeLabels = [
                'image' => 'Gambar',
                'document' => 'Dokumen',
                'audio' => 'Audio',
                'video' => 'Video',
            ];
            $typeLabel = $typeLabels[$type] ?? 'File';

            $message = "⚠️ *{$typeLabel} tidak dapat diproses*\n\n"
                . "Ukuran file yang Anda kirim ({$fileSizeMB} MB) melebihi batas maksimum ({$maxSizeMB} MB).\n\n"
                . "Silakan kirim file dengan ukuran lebih kecil.";

            $accessToken = config('whatsapp.access_token');
            $phoneNumberId = config('whatsapp.phone_number_id');

            Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => ['body' => $message],
                ]);

            Log::info('File size limit reply sent', ['to' => $to, 'type' => $type]);

        } catch (\Exception $e) {
            Log::error('Failed to send file size limit reply', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get file extension from MIME type
     */
    protected function getExtensionFromMimeType(string $mimeType): string
    {
        $mimeToExt = [
            // Images
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            // Documents
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            // Audio
            'audio/ogg' => 'ogg',
            'audio/mpeg' => 'mp3',
            'audio/amr' => 'amr',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            // Video
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
        ];

        return $mimeToExt[$mimeType] ?? 'bin';
    }
}
