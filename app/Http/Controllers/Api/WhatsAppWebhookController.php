<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageStatusUpdated;
use App\Events\NewWhatsAppMessage;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MediaStorageService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class WhatsAppWebhookController extends Controller
{
    protected MediaStorageService $mediaStorageService;

    protected WhatsAppAccountService $whatsAppAccountService;

    public function __construct(
        MediaStorageService $mediaStorageService,
        WhatsAppAccountService $whatsAppAccountService
    ) {
        $this->mediaStorageService = $mediaStorageService;
        $this->whatsAppAccountService = $whatsAppAccountService;
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
                    $wabaId = $entry['id'] ?? null;

                    foreach ($entry['changes'] as $change) {
                        $field = $change['field'] ?? null;

                        Log::info('Webhook change received', [
                            'field' => $field,
                            'waba_id' => $wabaId,
                        ]);

                        // Handle template status updates (message_template_status_update field)
                        if ($field === 'message_template_status_update') {
                            Log::info('Template status update webhook detected', $change['value']);
                            $this->handleTemplateStatusUpdate($change['value'], $wabaId);

                            continue;
                        }

                        $phoneNumberId = $change['value']['metadata']['phone_number_id'] ?? null;

                        if (! $phoneNumberId) {
                            continue;
                        }

                        // Find WhatsApp account using the service for multi-tenant routing
                        $whatsappAccount = $this->whatsAppAccountService->getAccountByPhoneNumberId($phoneNumberId);

                        if (! $whatsappAccount) {
                            // Log and skip processing for unknown phone numbers (Requirement 5.2)
                            Log::info('Webhook received for unknown phone number, skipping processing', [
                                'phone_number_id' => $phoneNumberId,
                                'waba_id' => $wabaId,
                            ]);

                            continue;
                        }

                        $userId = $whatsappAccount->user_id;

                        Log::info('Webhook routed to user', [
                            'phone_number_id' => $phoneNumberId,
                            'user_id' => $userId,
                            'waba_id' => $wabaId,
                        ]);

                        // Handle message events - pass account for user-specific credentials
                        if (isset($change['value']['messages'])) {
                            foreach ($change['value']['messages'] as $message) {
                                $this->handleIncomingMessage($message, $change['value'], $userId, $whatsappAccount);
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
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }

    /**
     * Handle incoming message
     *
     * @param  array  $message  Message data from webhook
     * @param  array  $value  Value object containing metadata and contacts
     * @param  int  $userId  User ID to associate the message with
     * @param  WhatsAppAccount  $whatsappAccount  The WhatsApp account for user-specific credentials
     */
    protected function handleIncomingMessage($message, $value, $userId, WhatsAppAccount $whatsappAccount)
    {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];
        $type = $message['type'];

        Log::info("Incoming $type message from $from", [
            'message_id' => $messageId,
            'user_id' => $userId,
            'phone_number_id' => $whatsappAccount->phone_number_id,
        ]);

        // Get or create contact
        // Note: Bypass global scope because webhooks are not authenticated
        $contactName = $value['contacts'][0]['profile']['name'] ?? $from;

        $contact = WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
            [
                'user_id' => $userId,
                'phone_number_id' => $whatsappAccount->phone_number_id,
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

        // Handle different message types - pass account for user-specific credentials
        switch ($type) {
            case 'text':
                $content = $message['text']['body'];
                break;

            case 'image':
                $mediaId = $message['image']['id'];
                $mimeType = $message['image']['mime_type'] ?? 'image/jpeg';
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'image', $mimeType, null, $from, $whatsappAccount);
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
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'document', $mimeType, $filename, $from, $whatsappAccount);
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
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'audio', $mimeType, null, $from, $whatsappAccount);
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
                $mediaResult = $this->downloadAndStoreMedia($mediaId, 'video', $mimeType, null, $from, $whatsappAccount);
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
                    } elseif ($interactiveType === 'nfm_reply') {
                        // Handle WhatsApp Flow response
                        $flowResponse = $message['interactive']['nfm_reply'] ?? [];
                        $content = 'Flow response received';
                        $metadata = $flowResponse;

                        Log::info('Flow response received but reservation processing is disabled', [
                            'user_id' => $userId,
                            'contact_id' => $contact->id,
                            'flow_id' => $flowResponse['flow_id'] ?? null,
                        ]);
                    }
                }
                break;

            default:
                $content = "Unsupported message type: $type";
                $metadata = $message;
                break;
        }

        // Save message to database (use updateOrCreate to prevent duplicate errors)
        // Note: Bypass global scope because webhooks are not authenticated
        $whatsappMessage = WhatsAppMessage::withoutGlobalScopes()->updateOrCreate(
            ['message_id' => $messageId],
            [
                'user_id' => $userId,
                'phone_number_id' => $whatsappAccount->phone_number_id,
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
            'contact_id' => $contact->id,
        ]);

        // Check if AI Agent is active for this account
        $aiAgent = \App\Models\AiAgent::where('whatsapp_account_id', $whatsappAccount->id)
            ->where('is_active', true)
            ->first();

        if ($aiAgent && $type === 'text') {
            // Dispatch AI Agent processing to queue
            \App\Jobs\ProcessAiAgentMessage::dispatch(
                $whatsappAccount,
                $contact,
                $content
            )->onQueue('ai-agent');

            Log::info('AI Agent message dispatched to queue', [
                'contact_id' => $contact->id,
                'ai_agent_id' => $aiAgent->id,
            ]);
        }

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

        Log::info("Status update: $messageId -> $statusValue", [
            'user_id' => $userId,
            'status_data' => $status,
        ]);

        // Find message in database
        // Note: Bypass global scope because webhooks are not authenticated
        $message = WhatsAppMessage::withoutGlobalScopes()
            ->where('message_id', $messageId)
            ->where('user_id', $userId)
            ->first();

        if (! $message) {
            // Try to find without user_id filter for debugging
            $messageWithoutFilter = WhatsAppMessage::withoutGlobalScopes()
                ->where('message_id', $messageId)
                ->first();

            Log::warning('Message not found for status update', [
                'message_id' => $messageId,
                'user_id' => $userId,
                'found_without_filter' => $messageWithoutFilter ? true : false,
                'actual_user_id' => $messageWithoutFilter?->user_id,
            ]);

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

        // Refresh message to get updated data
        $message->refresh();

        Log::info('Broadcasting MessageStatusUpdated', [
            'message_id' => $message->id,
            'wa_message_id' => $message->message_id,
            'user_id' => $message->user_id,
            'status' => $message->status,
        ]);

        // Broadcast status update to frontend
        try {
            broadcast(new MessageStatusUpdated($message));

            Log::info('Message status updated and broadcasted successfully', [
                'message_id' => $messageId,
                'status' => $statusValue,
                'user_id' => $message->user_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast MessageStatusUpdated', [
                'message_id' => $messageId,
                'status' => $statusValue,
                'user_id' => $message->user_id,
                'error' => $e->getMessage(),
            ]);
        }

        if ($statusValue === 'failed' && isset($status['errors'])) {
            Log::error('Message delivery failed', [
                'message_id' => $messageId,
                'errors' => $status['errors'],
            ]);
        }
    }

    /**
     * Handle template status update webhook from Meta
     *
     * Webhook payload example:
     * {
     *   "event": "APPROVED" | "PENDING" | "REJECTED" | "DISABLED" | "PENDING_DELETION" | "DELETED",
     *   "message_template_id": 123456789,
     *   "message_template_name": "template_name",
     *   "message_template_language": "en",
     *   "reason": "NONE" | rejection reason
     * }
     */
    protected function handleTemplateStatusUpdate(array $value, ?string $wabaId)
    {
        $event = $value['event'] ?? null;
        $templateId = $value['message_template_id'] ?? null;
        $templateName = $value['message_template_name'] ?? null;
        $templateLanguage = $value['message_template_language'] ?? null;
        $reason = $value['reason'] ?? null;
        $rejectionInfo = $value['rejection_info'] ?? null;

        Log::info('Template status update received', [
            'event' => $event,
            'template_id' => $templateId,
            'template_name' => $templateName,
            'language' => $templateLanguage,
            'reason' => $reason,
            'rejection_info' => $rejectionInfo,
            'waba_id' => $wabaId,
        ]);

        if (! $templateName || ! $event) {
            Log::warning('Template status update missing required fields', $value);

            return;
        }

        // Map Meta event to our status values
        $statusMap = [
            'APPROVED' => 'APPROVED',
            'PENDING' => 'PENDING',
            'REJECTED' => 'REJECTED',
            'DISABLED' => 'DISABLED',
            'PENDING_DELETION' => 'PENDING_DELETION',
            'DELETED' => 'DELETED',
            'IN_APPEAL' => 'IN_APPEAL',
            'PAUSED' => 'PAUSED',
        ];

        $newStatus = $statusMap[$event] ?? $event;

        // Find template - try multiple strategies
        $template = null;

        // Strategy 1: Find by template_id if available (most reliable)
        if ($templateId) {
            $template = WhatsAppTemplate::where('template_id', $templateId)->first();

            if ($template) {
                Log::info('Template found by template_id', ['template_id' => $templateId]);
            }
        }

        // Strategy 2: Find by name and language
        if (! $template && $templateName) {
            $query = WhatsAppTemplate::where('name', $templateName);

            if ($templateLanguage) {
                $query->where('language', $templateLanguage);
            }

            // If we have waba_id, try to find the account's template
            if ($wabaId) {
                $whatsappAccount = WhatsAppAccount::where('waba_id', $wabaId)->first();
                if ($whatsappAccount) {
                    $query->where('whatsapp_account_id', $whatsappAccount->id);
                }
            }

            $template = $query->first();

            if ($template) {
                Log::info('Template found by name and language', [
                    'template_name' => $templateName,
                    'language' => $templateLanguage,
                ]);
            }
        }

        // Strategy 3: Find by name only (fallback for single-tenant or when language doesn't match)
        if (! $template && $templateName) {
            $template = WhatsAppTemplate::where('name', $templateName)->first();

            if ($template) {
                Log::info('Template found by name only (fallback)', ['template_name' => $templateName]);
            }
        }

        if (! $template) {
            Log::warning('Template not found for status update', [
                'template_name' => $templateName,
                'template_id' => $templateId,
                'language' => $templateLanguage,
                'waba_id' => $wabaId,
            ]);

            return;
        }

        // Update template status
        $oldStatus = $template->status;
        $template->status = $newStatus;

        // Store rejection info if template was rejected
        if ($newStatus === 'REJECTED' && ! empty($rejectionInfo)) {
            $template->rejection_info = $rejectionInfo;
        } elseif ($newStatus !== 'REJECTED') {
            // Clear rejection info if template was no longer rejected
            $template->rejection_info = null;
        }

        // Update template_id if we received it from Meta and don't have it yet
        if ($templateId && ! $template->template_id) {
            $template->template_id = $templateId;
        }

        $template->save();

        Log::info('Template status updated successfully', [
            'template_id' => $template->id,
            'template_name' => $templateName,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'reason' => $reason,
            'rejection_info' => $rejectionInfo,
        ]);

        // If template was deleted, optionally remove from database
        if ($newStatus === 'DELETED') {
            Log::info('Template marked as deleted', ['template_name' => $templateName]);
            // Optionally: $template->delete();
        }
    }

    /**
     * Download media from WhatsApp and store to R2/local storage
     *
     * @param  string  $mediaId  WhatsApp media ID
     * @param  string  $type  Media type (image, document, audio, video)
     * @param  string  $mimeType  MIME type of the media
     * @param  string|null  $filename  Original filename (for documents)
     * @param  string|null  $senderPhone  Sender phone number for reply
     * @param  WhatsAppAccount|null  $whatsappAccount  WhatsApp account for user-specific credentials
     * @return array{url: string|null, exceeded_limit: bool, file_size: int, max_size: int}
     */
    protected function downloadAndStoreMedia(string $mediaId, string $type, string $mimeType, ?string $filename = null, ?string $senderPhone = null, ?WhatsAppAccount $whatsappAccount = null): array
    {
        try {
            // Use user-specific credentials if available, otherwise fall back to config
            $accessToken = $whatsappAccount?->access_token ?? config('whatsapp.access_token');
            $phoneNumberId = $whatsappAccount?->phone_number_id ?? config('whatsapp.phone_number_id');

            // Step 1: Get media URL and file size from WhatsApp
            $mediaInfoResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$mediaId}");

            if (! $mediaInfoResponse->successful()) {
                Log::error('Failed to get media info from WhatsApp', [
                    'media_id' => $mediaId,
                    'response' => $mediaInfoResponse->body(),
                ]);

                return null;
            }

            $mediaInfo = $mediaInfoResponse->json();
            $mediaUrl = $mediaInfo['url'] ?? null;
            $fileSize = $mediaInfo['file_size'] ?? 0;

            if (! $mediaUrl) {
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
                    $this->sendFileSizeLimitReply($senderPhone, $type, $fileSize, $maxSize, $accessToken, $phoneNumberId);
                }

                return ['url' => null, 'exceeded_limit' => true, 'file_size' => $fileSize, 'max_size' => $maxSize];
            }

            // Step 3: Download media content
            $mediaResponse = Http::withToken($accessToken)
                ->timeout(60)
                ->get($mediaUrl);

            if (! $mediaResponse->successful()) {
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
                    $this->sendFileSizeLimitReply($senderPhone, $type, $actualSize, $maxSize, $accessToken, $phoneNumberId);
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
     *
     * @param  string  $to  Recipient phone number
     * @param  string  $type  Media type
     * @param  int  $fileSize  Actual file size in bytes
     * @param  int  $maxSize  Maximum allowed size in bytes
     * @param  string|null  $accessToken  User-specific access token (falls back to config)
     * @param  string|null  $phoneNumberId  User-specific phone number ID (falls back to config)
     */
    protected function sendFileSizeLimitReply(string $to, string $type, int $fileSize, int $maxSize, ?string $accessToken = null, ?string $phoneNumberId = null): void
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
                ."Ukuran file yang Anda kirim ({$fileSizeMB} MB) melebihi batas maksimum ({$maxSizeMB} MB).\n\n"
                .'Silakan kirim file dengan ukuran lebih kecil.';

            // Use provided credentials or fall back to config
            $accessToken = $accessToken ?? config('whatsapp.access_token');
            $phoneNumberId = $phoneNumberId ?? config('whatsapp.phone_number_id');

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

    /**
     * Handle WhatsApp Flow response (nfm_reply)
     *
     * @param  array  $flowResponse  The flow response data
     * @param  int  $userId  User ID
     * @param  WhatsAppContact  $contact  The WhatsApp contact
     */
    protected function handleFlowResponse(array $flowResponse, int $userId, WhatsAppContact $contact): void
    {
        try {
            // Extract response data from the flow
            $responseJson = $flowResponse['response_json'] ?? null;
            $flowToken = $flowResponse['flow_token'] ?? null;
            $flowId = $flowResponse['flow_id'] ?? null;

            if (! $responseJson) {
                Log::warning('Flow response without response_json', [
                    'flow_response' => $flowResponse,
                    'user_id' => $userId,
                ]);

                return;
            }

            // Parse the JSON response
            $responseData = is_string($responseJson) ? json_decode($responseJson, true) : $responseJson;

            if (! is_array($responseData)) {
                Log::warning('Invalid flow response_json format', [
                    'response_json' => $responseJson,
                    'user_id' => $userId,
                ]);

                return;
            }

            Log::info('Processing flow response', [
                'flow_token' => $flowToken,
                'flow_id' => $flowId,
                'response_data' => $responseData,
                'user_id' => $userId,
            ]);

            // Check if this is a reservation flow response
            if ($this->isReservationFlowResponse($responseData)) {
                $this->processReservationFlowResponse(
                    $userId,
                    $flowToken,
                    $flowId,
                    $responseData,
                    $contact
                );
            }

        } catch (\Exception $e) {
            Log::error('Error processing flow response', [
                'error' => $e->getMessage(),
                'flow_response' => $flowResponse,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Check if the flow response is a reservation flow
     */
    protected function isReservationFlowResponse(array $responseData): bool
    {
        // Check for reservation-specific fields
        $reservationFields = ['customer_name', 'reservation_date', 'reservation_time', 'guest_count'];

        foreach ($reservationFields as $field) {
            if (isset($responseData[$field])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process reservation flow response and create reservation
     */
    protected function processReservationFlowResponse(
        int $userId,
        ?string $flowToken,
        ?string $flowId,
        array $responseData,
        WhatsAppContact $contact
    ): void {
        try {
            $flowService = app(\App\Services\WhatsAppFlowService::class);

            // Add flow_id to response data
            $responseData['flow_id'] = $flowId;

            // Process the flow response
            $reservation = $flowService->processFlowResponse(
                $userId,
                $flowToken ?? '',
                $responseData,
                $contact
            );

            Log::info('Reservation created from flow response', [
                'reservation_id' => $reservation->id,
                'user_id' => $userId,
                'contact_id' => $contact->id,
            ]);

            // Send confirmation message back to the customer
            $this->sendReservationConfirmation($userId, $contact, $reservation);

        } catch (\Exception $e) {
            Log::error('Error creating reservation from flow', [
                'error' => $e->getMessage(),
                'response_data' => $responseData,
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * Send reservation confirmation message to customer
     */
    protected function sendReservationConfirmation(int $userId, WhatsAppContact $contact, \App\Models\Reservation $reservation): void
    {
        try {
            $whatsappClient = $this->whatsAppAccountService->getClientForUser($userId);

            $confirmationMessage = sprintf(
                "✅ *Reservasi Diterima!*\n\n".
                "Kode: #RES%06d\n".
                "Nama: %s\n".
                "Tanggal: %s\n".
                "Jam: %s\n".
                "Jumlah Tamu: %d orang\n\n".
                "Status: Menunggu konfirmasi\n\n".
                'Kami akan menghubungi Anda untuk konfirmasi. Terima kasih! 🙏',
                $reservation->id,
                $reservation->customer_name,
                $reservation->reservation_time->format('d M Y'),
                $reservation->reservation_time->format('H:i'),
                $reservation->guest_count
            );

            $whatsappClient->sendTextMessage(
                $contact->wa_id,
                $confirmationMessage
            );

            Log::info('Reservation confirmation sent', [
                'reservation_id' => $reservation->id,
                'contact_id' => $contact->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Error sending reservation confirmation', [
                'error' => $e->getMessage(),
                'reservation_id' => $reservation->id,
            ]);
        }
    }
}
