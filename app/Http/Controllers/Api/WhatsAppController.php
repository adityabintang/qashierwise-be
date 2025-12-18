<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Services\MediaStorageService;
use App\Services\TemplateService;
use App\Services\WhatsAppAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\Message\Media\LinkID;
use Netflie\WhatsAppCloudApi\Message\Media\MediaObjectID;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Action as ListAction;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Row as ListRow;
use Netflie\WhatsAppCloudApi\Message\OptionsList\Section as ListSection;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

class WhatsAppController extends Controller
{
    protected $whatsappAccount;

    protected MediaStorageService $mediaStorageService;

    protected TemplateService $templateService;

    protected WhatsAppAccountService $whatsAppAccountService;

    public function __construct(
        MediaStorageService $mediaStorageService,
        TemplateService $templateService,
        WhatsAppAccountService $whatsAppAccountService
    ) {
        $this->mediaStorageService = $mediaStorageService;
        $this->templateService = $templateService;
        $this->whatsAppAccountService = $whatsAppAccountService;
    }

    /**
     * Get WhatsApp Cloud API client for the authenticated user.
     * Uses user's stored credentials from WhatsAppAccountService.
     *
     * @throws WhatsAppNotConnectedException
     */
    protected function getWhatsAppClient(): WhatsAppCloudApi
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new WhatsAppNotConnectedException('Authentication required to access WhatsApp features.');
        }

        return $this->whatsAppAccountService->getClientForUser($userId);
    }

    /**
     * Get the user's active WhatsApp account credentials.
     *
     * @throws WhatsAppNotConnectedException
     */
    protected function getUserWhatsAppAccount(): WhatsAppAccount
    {
        $userId = auth()->id();
        if (! $userId) {
            throw new WhatsAppNotConnectedException('Authentication required to access WhatsApp features.');
        }

        $account = $this->whatsAppAccountService->getActiveAccount($userId);
        if (! $account) {
            throw new WhatsAppNotConnectedException('No connected WhatsApp account found for this user.');
        }

        return $account;
    }

    /**
     * Upload media to WhatsApp with correct MIME type.
     * Uses user's stored credentials for the API call.
     *
     * @throws WhatsAppNotConnectedException
     */
    private function uploadMediaToWhatsApp(UploadedFile $file): array
    {
        $account = $this->getUserWhatsAppAccount();
        $phoneNumberId = $account->phone_number_id;
        $accessToken = $account->access_token;

        $response = Http::withToken($accessToken)
            ->attach(
                'file',
                file_get_contents($file->getRealPath()),
                $file->getClientOriginalName(),
                ['Content-Type' => $file->getMimeType()]
            )
            ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/media", [
                'messaging_product' => 'whatsapp',
                'type' => $file->getMimeType(),
            ]);

        if (! $response->successful()) {
            throw new \Exception('Failed to upload media: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Get WhatsApp account instance for the authenticated user.
     * Uses WhatsAppAccountService to retrieve user's connected account.
     *
     * @throws WhatsAppNotConnectedException
     */
    private function getWhatsAppAccount(): WhatsAppAccount
    {
        if (! $this->whatsappAccount) {
            $this->whatsappAccount = $this->getUserWhatsAppAccount();
        }

        return $this->whatsappAccount;
    }

    /**
     * Get or create contact record
     */
    private function getOrCreateContact($phoneNumber)
    {
        // Clean phone number (remove +, spaces, etc)
        $cleanNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        $userId = auth()->id();

        if (! $userId) {
            throw new WhatsAppNotConnectedException('Authentication required');
        }

        return WhatsAppContact::firstOrCreate(
            [
                'user_id' => $userId,
                'wa_id' => $cleanNumber,
            ],
            [
                'name' => $phoneNumber, // Will be updated when they reply
            ]
        );
    }

    /**
     * Save message to database
     */
    private function saveMessage($contact, $type, $content, $response, $templateName = null, $templateLanguage = null)
    {
        $responseBody = $response->decodedBody();
        $userId = auth()->id() ?? $contact->user_id ?? 1;

        // For template messages, include template_name in content
        if ($type === 'template' && $templateName) {
            $contentData = is_string($content) ? json_decode($content, true) : $content;
            if (is_array($contentData)) {
                $contentData['template_name'] = $templateName;
                $contentData['language'] = $templateLanguage;
            } else {
                $contentData = [
                    'template_name' => $templateName,
                    'language' => $templateLanguage,
                    'original_content' => $content,
                ];
            }
            $content = $contentData;
        }

        $message = WhatsAppMessage::create([
            'user_id' => $userId,
            'contact_id' => $contact->id,
            'message_id' => $responseBody['messages'][0]['id'] ?? null,
            'direction' => 'outgoing',
            'status' => 'sent',
            'type' => $type,
            'content' => is_array($content) ? json_encode($content) : $content,
            'metadata' => [
                'template_name' => $templateName,
                'template_language' => $templateLanguage,
            ],
            'sent_at' => now(),
        ]);

        // Update contact's last message info
        $contact->update([
            'last_message_at' => now(),
            'last_message_text' => $this->getLastMessageText($type, $content, $templateName),
        ]);

        // Broadcast the new message event (include sender so message appears in their UI)
        broadcast(new \App\Events\NewWhatsAppMessage($message->load('contact'), $contact));

        return $message;
    }

    /**
     * Get last message text for contact preview
     */
    private function getLastMessageText($type, $content, $templateName = null)
    {
        switch ($type) {
            case 'text':
                return is_string($content) ? $content : 'Text message';
            case 'template':
                return '📋 Template: '.($templateName ?? 'Template');
            case 'image':
                return '📷 Image';
            case 'video':
                return '🎥 Video';
            case 'audio':
                return '🎵 Audio';
            case 'document':
                $filename = is_array($content) ? ($content['filename'] ?? 'Document') : 'Document';

                return '📄 '.$filename;
            case 'location':
                return '📍 Location';
            case 'interactive':
                return '🔘 Interactive message';
            case 'contact':
            case 'contacts':
                return '👤 Contact card';
            default:
                return ucfirst($type).' message';
        }
    }

    /**
     * Send text message
     */
    public function sendTextMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $response = $whatsapp->sendTextMessage(
                $request->to,
                $request->message
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $this->saveMessage($contact, 'text', $request->message, $response);

            return response()->json([
                'success' => true,
                'message' => 'Text message sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send template message
     */
    public function sendTemplateMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'template_name' => 'required|string',
            'language' => 'required|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();

            // For simple template without parameters (like hello_world)
            if (! $request->has('header_params') && ! $request->has('body_params') && ! $request->has('button_params')) {
                $response = $whatsapp->sendTemplate(
                    $request->to,
                    $request->template_name,
                    $request->language
                );
            } else {
                // For templates with parameters
                $component_header = [];
                $component_body = [];
                $component_buttons = [];

                // Add components if provided
                if ($request->has('header_params')) {
                    $component_header = [new Component($request->header_params)];
                }

                if ($request->has('body_params')) {
                    $component_body = [new Component($request->body_params)];
                }

                if ($request->has('button_params')) {
                    $component_buttons = [new Component($request->button_params)];
                }

                $response = $whatsapp->sendTemplate(
                    $request->to,
                    $request->template_name,
                    $request->language,
                    $component_header,
                    $component_body,
                    $component_buttons
                );
            }

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $this->saveMessage(
                $contact,
                'template',
                json_encode($request->except(['to'])),
                $response,
                $request->template_name,
                $request->language
            );

            return response()->json([
                'success' => true,
                'message' => 'Template message sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send image message
     */
    public function sendImageMessage(Request $request)
    {
        $maxSizeKb = (int) (config('whatsapp.media_limits.image', 5 * 1024 * 1024) / 1024);
        $request->validate([
            'to' => 'required|string',
            'file' => "required_without:image_url|file|mimes:jpeg,jpg,png|max:{$maxSizeKb}",
            'image_url' => 'required_without:file|string|nullable',
            'caption' => 'nullable|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $imageUrl = $request->image_url;

            // If file is uploaded, upload to WhatsApp first
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Store using MediaStorageService
                $storageResult = $this->mediaStorageService->store($file, 'image');
                $imageUrl = $storageResult['url'];

                // Upload to WhatsApp
                $uploadData = $this->uploadMediaToWhatsApp($file);

                if (! isset($uploadData['id'])) {
                    throw new \Exception('Failed to upload media to WhatsApp');
                }

                // Use MediaObjectID for uploaded media
                $media_id = new MediaObjectID($uploadData['id']);

                $response = $whatsapp->sendImage(
                    $request->to,
                    $media_id,
                    $request->caption ?? ''
                );
            } else {
                $link_id = new LinkID($request->image_url);

                $response = $whatsapp->sendImage(
                    $request->to,
                    $link_id,
                    $request->caption ?? ''
                );
            }

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'url' => $imageUrl,
                'caption' => $request->caption ?? '',
            ];
            $this->saveMessage($contact, 'image', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Image sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send document message
     */
    public function sendDocumentMessage(Request $request)
    {
        $maxSizeKb = (int) (config('whatsapp.media_limits.document', 25 * 1024 * 1024) / 1024);
        $request->validate([
            'to' => 'required|string',
            'file' => "required_without:document_url|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt|max:{$maxSizeKb}",
            'document_url' => 'required_without:file|string|nullable',
            'filename' => 'nullable|string',
            'caption' => 'nullable|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $documentUrl = $request->document_url;
            $filename = $request->filename;

            // If file is uploaded, upload to WhatsApp first
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $filename = $filename ?? $file->getClientOriginalName();

                // Store using MediaStorageService
                $storageResult = $this->mediaStorageService->store($file, 'document');
                $documentUrl = $storageResult['url'];

                $uploadData = $this->uploadMediaToWhatsApp($file);

                if (! isset($uploadData['id'])) {
                    throw new \Exception('Failed to upload media to WhatsApp');
                }

                // Use MediaObjectID for uploaded media
                $media_id = new MediaObjectID($uploadData['id']);

                $response = $whatsapp->sendDocument(
                    $request->to,
                    $media_id,
                    $filename,
                    $request->caption ?? ''
                );
            } else {
                $link_id = new LinkID($request->document_url);

                $response = $whatsapp->sendDocument(
                    $request->to,
                    $link_id,
                    $filename ?? 'document',
                    $request->caption ?? ''
                );
            }

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'url' => $documentUrl,
                'filename' => $filename,
                'caption' => $request->caption ?? '',
            ];
            $this->saveMessage($contact, 'document', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Document sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send document',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send audio message
     */
    public function sendAudioMessage(Request $request)
    {
        $maxSizeKb = (int) (config('whatsapp.media_limits.audio', 16 * 1024 * 1024) / 1024);
        $request->validate([
            'to' => 'required|string',
            'file' => "required_without:audio_url|file|mimes:mp3,ogg,amr,aac,m4a|max:{$maxSizeKb}",
            'audio_url' => 'required_without:file|string|nullable',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $audioUrl = $request->audio_url;

            // If file is uploaded, upload to WhatsApp first
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Store using MediaStorageService
                $storageResult = $this->mediaStorageService->store($file, 'audio');
                $audioUrl = $storageResult['url'];

                $uploadData = $this->uploadMediaToWhatsApp($file);

                if (! isset($uploadData['id'])) {
                    throw new \Exception('Failed to upload media to WhatsApp');
                }

                // Use MediaObjectID for uploaded media
                $media_id = new MediaObjectID($uploadData['id']);

                $response = $whatsapp->sendAudio(
                    $request->to,
                    $media_id
                );
            } else {
                $link_id = new LinkID($request->audio_url);

                $response = $whatsapp->sendAudio(
                    $request->to,
                    $link_id
                );
            }

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = ['url' => $audioUrl];
            $this->saveMessage($contact, 'audio', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Audio sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send audio',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send video message
     */
    public function sendVideoMessage(Request $request)
    {
        $maxSizeKb = (int) (config('whatsapp.media_limits.video', 16 * 1024 * 1024) / 1024);
        $request->validate([
            'to' => 'required|string',
            'file' => "required_without:video_url|file|mimes:mp4,3gp|max:{$maxSizeKb}",
            'video_url' => 'required_without:file|string|nullable',
            'caption' => 'nullable|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $videoUrl = $request->video_url;

            // If file is uploaded, upload to WhatsApp first
            if ($request->hasFile('file')) {
                $file = $request->file('file');

                // Store using MediaStorageService
                $storageResult = $this->mediaStorageService->store($file, 'video');
                $videoUrl = $storageResult['url'];

                $uploadData = $this->uploadMediaToWhatsApp($file);

                if (! isset($uploadData['id'])) {
                    throw new \Exception('Failed to upload media to WhatsApp');
                }

                // Use MediaObjectID for uploaded media
                $media_id = new MediaObjectID($uploadData['id']);

                $response = $whatsapp->sendVideo(
                    $request->to,
                    $media_id,
                    $request->caption ?? ''
                );
            } else {
                $link_id = new LinkID($request->video_url);

                $response = $whatsapp->sendVideo(
                    $request->to,
                    $link_id,
                    $request->caption ?? ''
                );
            }

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'url' => $videoUrl,
                'caption' => $request->caption ?? '',
            ];
            $this->saveMessage($contact, 'video', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Video sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send video',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send location message
     */
    public function sendLocationMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'longitude' => 'required|numeric',
            'latitude' => 'required|numeric',
            'name' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $response = $whatsapp->sendLocation(
                $request->to,
                $request->longitude,
                $request->latitude,
                $request->name ?? '',
                $request->address ?? ''
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'name' => $request->name ?? '',
                'address' => $request->address ?? '',
            ];
            $this->saveMessage($contact, 'location', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Location sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send location',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send contact message
     */
    public function sendContactMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'contacts' => 'required|array',
            'contacts.*.name' => 'required|array',
            'contacts.*.name.formatted_name' => 'required|string',
            'contacts.*.phones' => 'required|array',
            'contacts.*.phones.*.phone' => 'required|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();

            // Format contacts for WhatsApp API
            $contacts = $request->contacts;
            $firstContact = $contacts[0];

            $response = $whatsapp->sendContact(
                $request->to,
                $firstContact['name']['formatted_name'],
                $firstContact['phones'][0]['phone']
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = ['contacts' => $contacts];
            $this->saveMessage($contact, 'contacts', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Contact sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send interactive button message
     */
    public function sendButtonMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'body' => 'required|string',
            'buttons' => 'required|array',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();

            $buttonObjects = [];
            $buttonData = [];
            foreach ($request->buttons as $index => $button) {
                $id = $button['id'] ?? 'btn_'.($index + 1);
                $title = $button['title'] ?? (is_string($button) ? $button : 'Button '.($index + 1));
                $buttonObjects[] = new Button($id, $title);
                $buttonData[] = ['id' => $id, 'title' => $title];
            }

            $buttonAction = new ButtonAction($buttonObjects);

            $response = $whatsapp->sendButton(
                $request->to,
                $request->body,
                $buttonAction,
                $request->header ?? null,
                $request->footer ?? null
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'body' => $request->body,
                'buttons' => $buttonData,
                'header' => $request->header ?? null,
                'footer' => $request->footer ?? null,
            ];
            $this->saveMessage($contact, 'interactive', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Button message sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send button message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send interactive list message
     */
    public function sendListMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'body' => 'required|string',
            'button_text' => 'required|string',
            'sections' => 'required|array',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();

            // Build sections with Row and Section objects
            $sectionObjects = [];
            foreach ($request->sections as $section) {
                $rows = [];
                foreach ($section['rows'] as $row) {
                    $rows[] = new ListRow(
                        $row['id'] ?? uniqid('row_'),
                        $row['title'],
                        $row['description'] ?? null
                    );
                }
                $sectionObjects[] = new ListSection(
                    $section['title'] ?? 'Options',
                    $rows
                );
            }

            // Create Action object
            $action = new ListAction($request->button_text, $sectionObjects);

            $response = $whatsapp->sendList(
                $request->to,
                $request->header ?? '',
                $request->body,
                $request->footer ?? '',
                $action
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'body' => $request->body,
                'button_text' => $request->button_text,
                'sections' => $request->sections,
                'header' => $request->header ?? null,
                'footer' => $request->footer ?? null,
            ];
            $this->saveMessage($contact, 'interactive', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'List message sent successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send list message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Request $request)
    {
        $request->validate([
            'message_id' => 'required|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $response = $whatsapp->markMessageAsRead($request->message_id);

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get media URL
     */
    public function getMediaUrl(Request $request)
    {
        $request->validate([
            'media_id' => 'required|string',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $response = $whatsapp->downloadMedia($request->media_id);

            return response()->json([
                'success' => true,
                'message' => 'Media URL retrieved successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get media URL',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload media
     */
    public function uploadMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
            'type' => 'required|in:image,document,audio,video',
        ]);

        try {
            $whatsapp = $this->getWhatsAppClient();
            $file = $request->file('file');
            $path = $file->getRealPath();

            $response = $whatsapp->uploadMedia($path);

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => $response->decodedBody(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload media',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get business profile
     */
    public function getBusinessProfile()
    {
        try {
            $account = $this->getWhatsAppAccount();
            $whatsapp = $this->getWhatsAppClient();

            // First check if we have profile data in database
            $localProfile = [
                'about' => $account->about,
                'address' => $account->address,
                'description' => $account->description,
                'email' => $account->email,
                'vertical' => $account->vertical,
                'websites' => $account->websites ?? [],
                'profile_picture_url' => $account->profile_picture_url,
            ];

            // Check if we have any data stored locally
            $hasLocalData = $account->about || $account->address || $account->description ||
                            $account->email || $account->vertical || ! empty($account->websites);

            if ($hasLocalData) {
                // Return local data
                return response()->json([
                    'success' => true,
                    'message' => 'Business profile retrieved successfully',
                    'data' => $localProfile,
                    'source' => 'database',
                ], 200);
            }

            // If no local data, fetch from WhatsApp API and save to database
            $fields = 'about,address,description,email,profile_picture_url,websites,vertical';
            $response = $whatsapp->businessProfile($fields);
            $apiData = $response->decodedBody();

            // Save API data to database for future use
            if (! empty($apiData['data'][0])) {
                $profileData = $apiData['data'][0];
                $account->update([
                    'about' => $profileData['about'] ?? null,
                    'address' => $profileData['address'] ?? null,
                    'description' => $profileData['description'] ?? null,
                    'email' => $profileData['email'] ?? null,
                    'vertical' => $profileData['vertical'] ?? null,
                    'websites' => $profileData['websites'] ?? [],
                    'profile_picture_url' => $profileData['profile_picture_url'] ?? null,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Business profile retrieved successfully',
                'data' => $apiData['data'][0] ?? $apiData,
                'source' => 'whatsapp_api',
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update business profile
     */
    public function updateBusinessProfile(Request $request)
    {
        try {
            $request->validate([
                'about' => 'nullable|string|max:139',
                'address' => 'nullable|string|max:256',
                'description' => 'nullable|string|max:512',
                'email' => 'nullable|email|max:128',
                'vertical' => 'nullable|in:UNDEFINED,OTHER,AUTO,BEAUTY,APPAREL,EDU,ENTERTAIN,EVENT_PLAN,FINANCE,GROCERY,GOVT,HOTEL,HEALTH,NONPROFIT,PROF_SERVICES,RETAIL,TRAVEL,RESTAURANT,NOT_A_BIZ',
                'websites' => 'nullable|array',
                'websites.*' => 'url',
            ]);

            $whatsapp = $this->getWhatsAppClient();

            $data = $request->only([
                'about',
                'address',
                'description',
                'email',
                'vertical',
                'websites',
            ]);

            // Remove null values but keep empty strings
            $data = array_filter($data, function ($value) {
                return $value !== null;
            });

            // Update WhatsApp API
            $response = $whatsapp->updateBusinessProfile($data);

            // Save to database
            $account = $this->getWhatsAppAccount();
            $account->update([
                'about' => $data['about'] ?? $account->about,
                'address' => $data['address'] ?? $account->address,
                'description' => $data['description'] ?? $account->description,
                'email' => $data['email'] ?? $account->email,
                'vertical' => $data['vertical'] ?? $account->vertical,
                'websites' => $data['websites'] ?? $account->websites,
            ]);

            // Broadcast profile update event
            $userId = auth()->id();
            if ($userId) {
                broadcast(new \App\Events\ProfileUpdated($userId, $data, 'business_profile'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Business profile updated successfully',
                'data' => $data,
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business profile',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get phone number info and registration status
     */
    public function getPhoneNumberInfo()
    {
        try {
            $account = $this->getUserWhatsAppAccount();
            $phoneNumberId = $account->phone_number_id;
            $accessToken = $account->access_token;

            // Call Graph API directly to get phone number details
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,name_status,code_verification_status',
                ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve phone number info: '.$response->body());
            }

            return response()->json([
                'success' => true,
                'message' => 'Phone number info retrieved successfully',
                'data' => $response->json(),
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get phone number info',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get WhatsApp Business Account details
     * Gets account info via phone number endpoint (works with Standard Access)
     */
    public function getBusinessAccount()
    {
        try {
            $account = $this->getUserWhatsAppAccount();
            $phoneNumberId = $account->phone_number_id;
            $businessAccountId = $account->waba_id ?? $account->business_account_id;
            $accessToken = $account->access_token;

            // Get phone numbers associated with the business account
            // This endpoint works with Standard Access (no BSP required)
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$businessAccountId}/phone_numbers");

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve business account info: '.$response->body());
            }

            $data = $response->json();

            // Combine with current phone number info
            $phoneInfo = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,name_status,code_verification_status,is_official_business_account',
                ])
                ->json();

            return response()->json([
                'success' => true,
                'message' => 'Business account retrieved successfully',
                'data' => [
                    'business_account_id' => $businessAccountId,
                    'phone_numbers' => $data['data'] ?? [],
                    'current_phone' => $phoneInfo,
                    'total_phone_numbers' => count($data['data'] ?? []),
                ],
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business account',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all WhatsApp Business Accounts from Business Portfolio
     * Simplified approach: Query from Meta App's subscribed WABAs
     */
    public function getAllBusinessAccounts()
    {
        try {
            $account = $this->getUserWhatsAppAccount();
            $accessToken = $account->access_token;
            $appId = config('whatsapp.app_id');

            if (! $appId) {
                throw new \Exception('WhatsApp App ID is required. Please add WHATSAPP_APP_ID to .env file');
            }

            // Get all WABAs subscribed to this app
            $appWABAsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$appId}/subscribed_apps");

            $allWABAs = [];

            // Fallback: Try to get from current business account and find related accounts
            // Get current WABA details from user's account
            $currentWABAId = $account->waba_id ?? $account->business_account_id;

            // Try multiple approaches to find all WABAs
            // Approach 1: Get phone numbers from current WABA
            $phoneResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$currentWABAId}/phone_numbers", [
                    'fields' => 'id,verified_name,display_phone_number,quality_rating,code_verification_status,name_status',
                ]);

            if ($phoneResponse->successful()) {
                $phones = $phoneResponse->json()['data'] ?? [];

                // Get WABA details
                $wabaResponse = Http::withToken($accessToken)
                    ->get("https://graph.facebook.com/v21.0/{$currentWABAId}", [
                        'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status',
                    ]);

                if ($wabaResponse->successful()) {
                    $waba = $wabaResponse->json();
                    $waba['phone_numbers'] = $phones;
                    $allWABAs[] = $waba;
                }
            }

            // Approach 2: Try to find other WABAs by querying phone number's owner
            // Get on_behalf_of_business_info from current phone
            $phoneNumberId = $account->phone_number_id;
            $phoneInfoResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,account_mode,certificate,code_verification_status,quality_rating,name_status',
                ]);

            $phoneInfo = $phoneInfoResponse->successful() ? $phoneInfoResponse->json() : null;

            // Manual approach: If you have multiple WABA IDs, add them to config
            $additionalWABAIds = config('whatsapp.additional_waba_ids', []);

            foreach ($additionalWABAIds as $wabaId) {
                if ($wabaId === $currentWABAId) {
                    continue;
                } // Skip current WABA

                $phoneResponse = Http::withToken($accessToken)
                    ->get("https://graph.facebook.com/v21.0/{$wabaId}/phone_numbers", [
                        'fields' => 'id,verified_name,display_phone_number,quality_rating,code_verification_status,name_status',
                    ]);

                if ($phoneResponse->successful()) {
                    $phones = $phoneResponse->json()['data'] ?? [];

                    $wabaResponse = Http::withToken($accessToken)
                        ->get("https://graph.facebook.com/v21.0/{$wabaId}", [
                            'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status',
                        ]);

                    if ($wabaResponse->successful()) {
                        $waba = $wabaResponse->json();
                        $waba['phone_numbers'] = $phones;
                        $allWABAs[] = $waba;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'WhatsApp Business Accounts retrieved successfully',
                'data' => [
                    'whatsapp_accounts' => $allWABAs,
                    'total_waba' => count($allWABAs),
                    'current_waba_id' => $currentWABAId,
                    'current_phone' => $phoneInfo,
                    'note' => count($allWABAs) === 1
                        ? 'Only current WABA found. To list other WABAs, add their IDs to WHATSAPP_ADDITIONAL_WABA_IDS in .env (comma-separated)'
                        : null,
                ],
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business accounts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Current totals
            $totalContacts = WhatsAppContact::where('user_id', $userId)->count();
            $totalMessages = WhatsAppMessage::where('user_id', $userId)->count();
            $unreadMessages = WhatsAppContact::where('user_id', $userId)->sum('unread_count');

            // Contacts growth (this month vs last month)
            $contactsThisMonth = WhatsAppContact::where('user_id', $userId)
                ->where('created_at', '>=', now()->startOfMonth())
                ->count();
            $contactsLastMonth = WhatsAppContact::where('user_id', $userId)
                ->whereBetween('created_at', [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()])
                ->count();
            $contactsGrowth = $contactsLastMonth > 0
                ? round((($contactsThisMonth - $contactsLastMonth) / $contactsLastMonth) * 100, 1)
                : ($contactsThisMonth > 0 ? 100 : 0);

            // Messages growth (this week vs last week)
            $messagesThisWeek = WhatsAppMessage::where('user_id', $userId)
                ->where('created_at', '>=', now()->startOfWeek())
                ->count();
            $messagesLastWeek = WhatsAppMessage::where('user_id', $userId)
                ->whereBetween('created_at', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])
                ->count();
            $messagesGrowth = $messagesLastWeek > 0
                ? round((($messagesThisWeek - $messagesLastWeek) / $messagesLastWeek) * 100, 1)
                : ($messagesThisWeek > 0 ? 100 : 0);

            $stats = [
                'total_contacts' => $totalContacts,
                'total_messages' => $totalMessages,
                'unread_messages' => $unreadMessages,
                'contacts_growth' => $contactsGrowth,
                'messages_growth' => $messagesGrowth,
                'contacts_this_month' => $contactsThisMonth,
                'contacts_last_month' => $contactsLastMonth,
                'messages_this_week' => $messagesThisWeek,
                'messages_last_week' => $messagesLastWeek,
                'incoming_messages' => WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'incoming')->count(),
                'outgoing_messages' => WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'outgoing')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get weekly chart data for dashboard
     */
    public function getWeeklyChartData()
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            // Get data for the last 7 days
            $chartData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dayName = $date->format('D'); // Mon, Tue, Wed, etc.
                $fullDate = $date->format('Y-m-d');

                $incoming = WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'incoming')
                    ->whereDate('created_at', $date)
                    ->count();

                $outgoing = WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'outgoing')
                    ->whereDate('created_at', $date)
                    ->count();

                $chartData[] = [
                    'day' => $dayName,
                    'date' => $fullDate,
                    'incoming' => $incoming,
                    'outgoing' => $outgoing,
                    'total' => $incoming + $outgoing,
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $chartData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve weekly chart data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all messages
     */
    public function getMessages(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $perPage = $request->get('per_page', 15);
            $limit = $request->get('limit');

            $query = WhatsAppMessage::with(['contact', 'user'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc');

            // If limit is specified, get that many without pagination
            if ($limit) {
                $messages = $query->limit($limit)->get();

                return response()->json([
                    'success' => true,
                    'data' => $messages,
                ], 200);
            }

            // Otherwise paginate
            $messages = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $messages->items(),
                'total' => $messages->total(),
                'pagination' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single message
     */
    public function getMessage($id)
    {
        try {
            $message = WhatsAppMessage::with(['contact', 'user'])
                ->where('user_id', auth()->id())
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $message,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Get all contacts
     */
    public function getContacts(Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $perPage = $request->get('per_page', 15);

            $contacts = WhatsAppContact::where('user_id', $userId)
                ->withCount('messages')
                ->orderBy('last_message_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $contacts->items(),
                'total' => $contacts->total(),
                'pagination' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contacts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get messages for a specific contact
     */
    public function getContactMessages($contactId, Request $request)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $perPage = $request->get('per_page', 100);

            $messages = WhatsAppMessage::with(['contact'])
                ->where('user_id', $userId)
                ->where('contact_id', $contactId)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contact messages',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark all messages from a contact as read
     */
    public function markContactMessagesAsRead($contactId)
    {
        try {
            $userId = auth()->id();

            if (! $userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                ], 401);
            }

            $whatsapp = $this->getWhatsAppClient();

            // Get all unread incoming messages from this contact
            $unreadMessages = WhatsAppMessage::where('user_id', $userId)
                ->where('contact_id', $contactId)
                ->where('direction', 'incoming')
                ->where('is_read', false)
                ->get();

            $markedCount = 0;

            // Mark each message as read in WhatsApp API
            foreach ($unreadMessages as $message) {
                try {
                    // Call WhatsApp API to mark message as read
                    $whatsapp->markMessageAsRead($message->message_id);

                    // Update in database
                    $message->is_read = true;
                    $message->read_at = now();
                    $message->status = 'read';
                    $message->save();

                    $markedCount++;

                    \Log::info('Message marked as read', [
                        'message_id' => $message->message_id,
                        'contact_id' => $contactId,
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to mark message as read in WhatsApp API', [
                        'message_id' => $message->message_id,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue marking other messages even if one fails
                }
            }

            // Update contact's unread count
            $contact = WhatsAppContact::find($contactId);
            if ($contact) {
                $contact->unread_count = 0;
                $contact->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read',
                'updated_count' => $markedCount,
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of WhatsApp message templates
     */
    public function getTemplates(Request $request)
    {
        try {
            $status = $request->get('status'); // APPROVED, PENDING, REJECTED
            $refresh = $request->get('refresh', false); // Force refresh from API
            $account = $this->getWhatsAppAccount();

            // Check if we need to sync from API (no templates in DB or refresh requested)
            $templateCount = WhatsAppTemplate::where('whatsapp_account_id', $account->id)->count();

            if ($templateCount === 0 || $refresh) {
                $this->syncTemplatesFromApi($account);
            }

            // Get templates from database
            $query = WhatsAppTemplate::where('whatsapp_account_id', $account->id);

            if ($status) {
                $query->where('status', strtoupper($status));
            }

            $templates = $query->orderBy('created_at', 'desc')->get();

            // Format templates
            $formattedTemplates = $templates->map(function ($template) {
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'status' => $template->status,
                    'category' => $template->category,
                    'language' => $template->language,
                    'header' => $template->header,
                    'header_type' => $template->header_type,
                    'body' => $template->body,
                    'footer' => $template->footer,
                    'buttons' => $template->buttons ? json_decode($template->buttons, true) : [],
                    'quality_score' => $template->quality_score,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedTemplates,
                'total' => count($formattedTemplates),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync templates from WhatsApp API to database
     */
    private function syncTemplatesFromApi($account)
    {
        $wabaId = $account->waba_id ?? $account->business_account_id;
        $accessToken = $account->access_token;

        $response = Http::withToken($accessToken)
            ->get("https://graph.facebook.com/v21.0/{$wabaId}/message_templates", [
                'limit' => 100,
                'fields' => 'name,status,category,language,components,id,quality_score',
            ]);

        if ($response->failed()) {
            \Log::error('Failed to fetch templates from Meta API', [
                'response' => $response->body(),
            ]);

            return;
        }

        $templates = $response->json()['data'] ?? [];

        foreach ($templates as $templateData) {
            $components = $templateData['components'] ?? [];

            // Extract component details
            $header = null;
            $headerType = null;
            $body = null;
            $footer = null;
            $buttons = [];

            foreach ($components as $component) {
                if ($component['type'] === 'HEADER') {
                    $headerType = $component['format'] ?? 'TEXT';
                    $header = $component['text'] ?? null;
                } elseif ($component['type'] === 'BODY') {
                    $body = $component['text'] ?? null;
                } elseif ($component['type'] === 'FOOTER') {
                    $footer = $component['text'] ?? null;
                } elseif ($component['type'] === 'BUTTONS') {
                    $buttons = $component['buttons'] ?? [];
                }
            }

            WhatsAppTemplate::updateOrCreate(
                [
                    'whatsapp_account_id' => $account->id,
                    'name' => $templateData['name'],
                    'language' => $templateData['language'],
                ],
                [
                    'template_id' => $templateData['id'] ?? null,
                    'status' => $templateData['status'],
                    'category' => $templateData['category'],
                    'header' => $header,
                    'header_type' => $headerType,
                    'body' => $body,
                    'footer' => $footer,
                    'buttons' => ! empty($buttons) ? json_encode($buttons) : null,
                    'components' => $components,
                    'quality_score' => $templateData['quality_score']['score'] ?? null,
                ]
            );
        }

        \Log::info('Templates synced from WhatsApp API', ['count' => count($templates)]);
    }

    /**
     * Get template detail by name
     */
    public function getTemplateByName($name, Request $request)
    {
        try {
            $account = $this->getUserWhatsAppAccount();
            $wabaId = $account->waba_id ?? $account->business_account_id;
            $accessToken = $account->access_token;

            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$wabaId}/message_templates", [
                    'name' => $name,
                    'fields' => 'name,status,category,language,components,id,rejected_reason',
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve template',
                    'error' => $response->json(),
                ], $response->status());
            }

            $templates = $response->json()['data'] ?? [];

            if (empty($templates)) {
                return response()->json([
                    'success' => false,
                    'message' => "Template '{$name}' not found",
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $templates,
            ], 200);

        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new WhatsApp message template
     */
    public function createTemplate(Request $request): JsonResponse
    {
        try {
            // Validate request data using TemplateService
            $data = $request->all();

            // Validate required fields
            $requiredValidation = $this->templateService->validateRequiredFields($data);
            if (! $requiredValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $requiredValidation['errors'],
                ], 422);
            }

            // Validate template name
            $nameValidation = $this->templateService->validateTemplateName($data['name']);
            if (! $nameValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['name' => $nameValidation['error']],
                ], 422);
            }

            // Validate body
            $bodyText = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
            $bodyValidation = $this->templateService->validateBody($bodyText);
            if (! $bodyValidation['valid']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => ['body' => $bodyValidation['error']],
                ], 422);
            }

            // Validate footer if present
            if (! empty($data['footer'])) {
                $footerText = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
                $footerValidation = $this->templateService->validateFooter($footerText);
                if (! $footerValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => ['footer' => $footerValidation['error']],
                    ], 422);
                }
            }

            // Validate buttons if present
            if (! empty($data['buttons'])) {
                $buttonsValidation = $this->templateService->validateButtons($data['buttons']);
                if (! $buttonsValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => ['buttons' => $buttonsValidation['error']],
                    ], 422);
                }
            }

            // Set user's credentials for multi-tenant support
            $account = $this->getWhatsAppAccount();
            $this->templateService->setCredentials(
                $account->access_token,
                $account->waba_id
            );

            // Call TemplateService to create template via WhatsApp API
            $result = $this->templateService->createTemplate($data);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create template',
                    'error' => $result['error'],
                ], 400);
            }

            // Store result in database
            $account = $this->getWhatsAppAccount();
            $components = $this->templateService->buildComponents($data);

            // Extract component details for storage
            $header = null;
            $headerType = null;
            $footer = null;
            $buttons = null;

            if (! empty($data['header'])) {
                $headerType = $data['header']['type'] ?? 'TEXT';
                $header = $data['header']['text'] ?? null;
            }

            if (! empty($data['footer'])) {
                $footer = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
            }

            if (! empty($data['buttons'])) {
                $buttons = json_encode($data['buttons']);
            }

            $template = WhatsAppTemplate::create([
                'whatsapp_account_id' => $account->id,
                'template_id' => $result['data']['id'] ?? null,
                'name' => $data['name'],
                'language' => $data['language'],
                'category' => $data['category'],
                'status' => $result['data']['status'] ?? 'PENDING',
                'header' => $header,
                'header_type' => $headerType,
                'body' => $bodyText,
                'footer' => $footer,
                'buttons' => $buttons,
                'components' => $components,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Template created successfully',
                'data' => [
                    'id' => $template->id,
                    'template_id' => $template->template_id,
                    'name' => $template->name,
                    'status' => $template->status,
                ],
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Template creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing WhatsApp message template
     *
     * @param  string  $id  Template ID (local database ID)
     */
    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        try {
            // Find template in database
            $account = $this->getWhatsAppAccount();
            $template = WhatsAppTemplate::where('whatsapp_account_id', $account->id)
                ->where('id', $id)
                ->first();

            if (! $template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            $data = $request->all();

            // Validate body if present
            if (! empty($data['body'])) {
                $bodyText = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
                $bodyValidation = $this->templateService->validateBody($bodyText);
                if (! $bodyValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => ['body' => $bodyValidation['error']],
                    ], 422);
                }
            }

            // Validate footer if present
            if (! empty($data['footer'])) {
                $footerText = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
                $footerValidation = $this->templateService->validateFooter($footerText);
                if (! $footerValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => ['footer' => $footerValidation['error']],
                    ], 422);
                }
            }

            // Validate buttons if present
            if (! empty($data['buttons'])) {
                $buttonsValidation = $this->templateService->validateButtons($data['buttons']);
                if (! $buttonsValidation['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => ['buttons' => $buttonsValidation['error']],
                    ], 422);
                }
            }

            // Set user's credentials for multi-tenant support
            $account = $this->getWhatsAppAccount();
            $this->templateService->setCredentials(
                $account->access_token,
                $account->waba_id
            );

            // Call TemplateService to update template via WhatsApp API
            $result = $this->templateService->updateTemplate($template->template_id, $data);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update template',
                    'error' => $result['error'],
                ], 400);
            }

            // Update database record
            $components = $this->templateService->buildComponents($data);

            $updateData = [
                'components' => $components,
            ];

            if (! empty($data['header'])) {
                $updateData['header_type'] = $data['header']['type'] ?? 'TEXT';
                $updateData['header'] = $data['header']['text'] ?? null;
            }

            if (! empty($data['body'])) {
                $updateData['body'] = is_array($data['body']) ? ($data['body']['text'] ?? '') : $data['body'];
            }

            if (isset($data['footer'])) {
                $updateData['footer'] = is_array($data['footer']) ? ($data['footer']['text'] ?? '') : $data['footer'];
            }

            if (isset($data['buttons'])) {
                $updateData['buttons'] = ! empty($data['buttons']) ? json_encode($data['buttons']) : null;
            }

            $template->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Template updated successfully',
                'data' => [
                    'id' => $template->id,
                    'template_id' => $template->template_id,
                    'name' => $template->name,
                    'status' => $template->status,
                ],
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Template update failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a WhatsApp message template
     *
     * @param  string  $name  Template name
     */
    public function deleteTemplate(string $name): JsonResponse
    {
        try {
            // Find template in database
            $account = $this->getWhatsAppAccount();
            $template = WhatsAppTemplate::where('whatsapp_account_id', $account->id)
                ->where('name', $name)
                ->first();

            if (! $template) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            // Set user's credentials for multi-tenant support
            $this->templateService->setCredentials(
                $account->access_token,
                $account->waba_id
            );

            // Call TemplateService to delete template via WhatsApp API
            // Pass template_id (hsm_id) for more reliable deletion
            $result = $this->templateService->deleteTemplate($name, $template->template_id);

            if (! $result['success']) {
                // Check if template was not found on WhatsApp (error_subcode 2593002)
                // In this case, we should still delete from local database
                $isTemplateNotFound = str_contains($result['error'] ?? '', 'Invalid parameter') ||
                                      str_contains($result['error'] ?? '', 'not found');

                if ($isTemplateNotFound) {
                    // Template doesn't exist on WhatsApp, remove from local database
                    $template->delete();

                    return response()->json([
                        'success' => true,
                        'message' => 'Template removed from local database (was not found on WhatsApp)',
                    ], 200);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete template',
                    'error' => $result['error'],
                ], 400);
            }

            // Remove from database on success
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Template deletion failed', [
                'name' => $name,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload business profile picture
     */
    public function uploadProfilePicture(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpeg,jpg,png|max:5120', // Max 5MB
        ]);

        try {
            $account = $this->getUserWhatsAppAccount();
            $file = $request->file('file');
            $phoneNumberId = $account->phone_number_id;
            $accessToken = $account->access_token;
            $appId = config('whatsapp.app_id');

            $fileContent = file_get_contents($file->getRealPath());
            $fileSize = strlen($fileContent);
            $mimeType = $file->getMimeType();

            // Step 1: Create upload session to get upload handle
            $sessionResponse = Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v21.0/{$appId}/uploads", [
                    'file_length' => $fileSize,
                    'file_type' => $mimeType,
                    'file_name' => $file->getClientOriginalName(),
                ]);

            if (! $sessionResponse->successful()) {
                throw new \Exception('Failed to create upload session: '.$sessionResponse->body());
            }

            $uploadSessionId = $sessionResponse->json()['id'] ?? null;

            if (! $uploadSessionId) {
                throw new \Exception('Failed to get upload session ID');
            }

            // Step 2: Upload the file content
            $uploadResponse = Http::withHeaders([
                'Authorization' => 'OAuth '.$accessToken,
                'file_offset' => '0',
            ])
                ->withBody($fileContent, $mimeType)
                ->post("https://graph.facebook.com/v21.0/{$uploadSessionId}");

            if (! $uploadResponse->successful()) {
                throw new \Exception('Failed to upload file: '.$uploadResponse->body());
            }

            $handle = $uploadResponse->json()['h'] ?? null;

            if (! $handle) {
                throw new \Exception('Failed to get file handle from upload');
            }

            // Step 3: Update business profile with the handle
            $updateResponse = Http::withToken($accessToken)
                ->post("https://graph.facebook.com/v21.0/{$phoneNumberId}/whatsapp_business_profile", [
                    'messaging_product' => 'whatsapp',
                    'profile_picture_handle' => $handle,
                ]);

            if (! $updateResponse->successful()) {
                throw new \Exception('Failed to update profile picture: '.$updateResponse->body());
            }

            // Store locally using MediaStorageService
            $storageResult = $this->mediaStorageService->store($file, 'image');

            // Update database
            $account = $this->getWhatsAppAccount();
            $account->update([
                'profile_picture_url' => $storageResult['url'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Profile picture updated successfully',
                'data' => [
                    'profile_picture_url' => $storageResult['url'],
                ],
            ], 200);
        } catch (WhatsAppNotConnectedException $e) {
            return response()->json([
                'success' => false,
                'error_code' => $e->getErrorCode(),
                'message' => $e->getMessage(),
            ], $e->getCode());
        } catch (\Exception $e) {
            \Log::error('Profile picture upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
