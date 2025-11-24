<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Netflie\WhatsAppCloudApi\Message\Media\LinkID;
use Netflie\WhatsAppCloudApi\Message\Media\MediaObjectID;
use Netflie\WhatsAppCloudApi\Message\Template\Component;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Models\WhatsAppMedia;

class WhatsAppController extends Controller
{
    protected $whatsapp;
    protected $whatsappAccount;

    public function __construct()
    {
        $this->whatsapp = new WhatsAppCloudApi([
            'from_phone_number_id' => config('whatsapp.phone_number_id'),
            'access_token' => config('whatsapp.access_token'),
        ]);
    }

    /**
     * Get WhatsApp account instance
     */
    private function getWhatsAppAccount()
    {
        if (!$this->whatsappAccount) {
            $this->whatsappAccount = WhatsAppAccount::firstOrCreate(
                ['phone_number_id' => config('whatsapp.phone_number_id')],
                [
                    'user_id' => auth()->id() ?? 1,
                    'business_account_id' => config('whatsapp.business_account_id', 'default'),
                    'access_token' => config('whatsapp.access_token'),
                    'is_active' => true,
                ]
            );
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
        $userId = auth()->id() ?? 1;

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
            'last_message_text' => is_string($content) ? $content : 'Media message',
        ]);

        // Broadcast the new message event
        broadcast(new \App\Events\NewWhatsAppMessage($message->load('contact'), $contact))->toOthers();

        return $message;
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
            $response = $this->whatsapp->sendTextMessage(
                $request->to,
                $request->message
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $this->saveMessage($contact, 'text', $request->message, $response);

            return response()->json([
                'success' => true,
                'message' => 'Text message sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send message',
                'error' => $e->getMessage()
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
            // For simple template without parameters (like hello_world)
            if (!$request->has('header_params') && !$request->has('body_params') && !$request->has('button_params')) {
                $response = $this->whatsapp->sendTemplate(
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

                $response = $this->whatsapp->sendTemplate(
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
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send image message
     */
    public function sendImageMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'image_url' => 'required|string',
        ]);

        try {
            $link_id = new LinkID($request->image_url);

            $response = $this->whatsapp->sendImage(
                $request->to,
                $link_id,
                $request->caption ?? ''
            );

            // Save to database
            $contact = $this->getOrCreateContact($request->to);
            $content = [
                'url' => $request->image_url,
                'caption' => $request->caption ?? ''
            ];
            $this->saveMessage($contact, 'image', $content, $response);

            return response()->json([
                'success' => true,
                'message' => 'Image sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send document message
     */
    public function sendDocumentMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'document_url' => 'required|string',
            'filename' => 'required|string',
        ]);

        try {
            $link_id = new LinkID($request->document_url);

            $response = $this->whatsapp->sendDocument(
                $request->to,
                $link_id,
                $request->filename,
                $request->caption ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'Document sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send audio message
     */
    public function sendAudioMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'audio_url' => 'required|string',
        ]);

        try {
            $link_id = new LinkID($request->audio_url);

            $response = $this->whatsapp->sendAudio(
                $request->to,
                $link_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Audio sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send audio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send video message
     */
    public function sendVideoMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'video_url' => 'required|string',
        ]);

        try {
            $link_id = new LinkID($request->video_url);

            $response = $this->whatsapp->sendVideo(
                $request->to,
                $link_id,
                $request->caption ?? ''
            );

            return response()->json([
                'success' => true,
                'message' => 'Video sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send video',
                'error' => $e->getMessage()
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
            'name' => 'required|string',
            'address' => 'required|string',
        ]);

        try {
            $response = $this->whatsapp->sendLocation(
                $request->to,
                $request->longitude,
                $request->latitude,
                $request->name,
                $request->address
            );

            return response()->json([
                'success' => true,
                'message' => 'Location sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send location',
                'error' => $e->getMessage()
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
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
        ]);

        try {
            $response = $this->whatsapp->sendContact(
                $request->to,
                $request->contact_name,
                $request->contact_phone
            );

            return response()->json([
                'success' => true,
                'message' => 'Contact sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send contact',
                'error' => $e->getMessage()
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
            $buttons = [];
            foreach ($request->buttons as $button) {
                $buttons[] = [
                    'type' => 'reply',
                    'reply' => [
                        'id' => $button['id'],
                        'title' => $button['title']
                    ]
                ];
            }

            $response = $this->whatsapp->sendButton(
                $request->to,
                $request->body,
                $buttons,
                $request->header ?? null,
                $request->footer ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Button message sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send button message',
                'error' => $e->getMessage()
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
            $response = $this->whatsapp->sendList(
                $request->to,
                $request->header ?? '',
                $request->body,
                $request->footer ?? '',
                $request->button_text,
                $request->sections
            );

            return response()->json([
                'success' => true,
                'message' => 'List message sent successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send list message',
                'error' => $e->getMessage()
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
            $response = $this->whatsapp->markMessageAsRead($request->message_id);

            return response()->json([
                'success' => true,
                'message' => 'Message marked as read',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark message as read',
                'error' => $e->getMessage()
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
            $response = $this->whatsapp->downloadMedia($request->media_id);

            return response()->json([
                'success' => true,
                'message' => 'Media URL retrieved successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get media URL',
                'error' => $e->getMessage()
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
            $file = $request->file('file');
            $path = $file->getRealPath();

            $response = $this->whatsapp->uploadMedia($path);

            return response()->json([
                'success' => true,
                'message' => 'Media uploaded successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload media',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get business profile
     */
    public function getBusinessProfile()
    {
        try {
            // Specify fields as comma-separated string (not array!)
            $fields = 'about,address,description,email,profile_picture_url,websites,vertical';

            $response = $this->whatsapp->businessProfile($fields);

            return response()->json([
                'success' => true,
                'message' => 'Business profile retrieved successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business profile',
                'error' => $e->getMessage()
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
                'websites.*' => 'url'
            ]);

            $data = $request->only([
                'about',
                'address',
                'description',
                'email',
                'vertical',
                'websites'
            ]);

            // Remove null values
            $data = array_filter($data, function($value) {
                return $value !== null;
            });

            $response = $this->whatsapp->updateBusinessProfile($data);

            return response()->json([
                'success' => true,
                'message' => 'Business profile updated successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get phone number info and registration status
     */
    public function getPhoneNumberInfo()
    {
        try {
            $phoneNumberId = config('whatsapp.phone_number_id');
            $accessToken = config('whatsapp.access_token');

            // Call Graph API directly to get phone number details
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,name_status,code_verification_status'
                ]);

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve phone number info: ' . $response->body());
            }

            return response()->json([
                'success' => true,
                'message' => 'Phone number info retrieved successfully',
                'data' => $response->json()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get phone number info',
                'error' => $e->getMessage()
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
            $phoneNumberId = config('whatsapp.phone_number_id');
            $businessAccountId = config('whatsapp.business_account_id');
            $accessToken = config('whatsapp.access_token');

            // Get phone numbers associated with the business account
            // This endpoint works with Standard Access (no BSP required)
            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$businessAccountId}/phone_numbers");

            if ($response->failed()) {
                throw new \Exception('Failed to retrieve business account info: ' . $response->body());
            }

            $data = $response->json();

            // Combine with current phone number info
            $phoneInfo = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,quality_rating,name_status,code_verification_status,is_official_business_account'
                ])
                ->json();

            return response()->json([
                'success' => true,
                'message' => 'Business account retrieved successfully',
                'data' => [
                    'business_account_id' => $businessAccountId,
                    'phone_numbers' => $data['data'] ?? [],
                    'current_phone' => $phoneInfo,
                    'total_phone_numbers' => count($data['data'] ?? [])
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business account',
                'error' => $e->getMessage()
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
            $accessToken = config('whatsapp.access_token');
            $appId = config('whatsapp.app_id');

            if (!$appId) {
                throw new \Exception('WhatsApp App ID is required. Please add WHATSAPP_APP_ID to .env file');
            }

            // Get all WABAs subscribed to this app
            $appWABAsResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$appId}/subscribed_apps");

            $allWABAs = [];

            // Fallback: Try to get from current business account and find related accounts
            // Get current WABA details
            $currentWABAId = config('whatsapp.business_account_id');

            // Try multiple approaches to find all WABAs
            // Approach 1: Get phone numbers from current WABA
            $phoneResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$currentWABAId}/phone_numbers", [
                    'fields' => 'id,verified_name,display_phone_number,quality_rating,code_verification_status,name_status'
                ]);

            if ($phoneResponse->successful()) {
                $phones = $phoneResponse->json()['data'] ?? [];

                // Get WABA details
                $wabaResponse = Http::withToken($accessToken)
                    ->get("https://graph.facebook.com/v21.0/{$currentWABAId}", [
                        'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status'
                    ]);

                if ($wabaResponse->successful()) {
                    $waba = $wabaResponse->json();
                    $waba['phone_numbers'] = $phones;
                    $allWABAs[] = $waba;
                }
            }

            // Approach 2: Try to find other WABAs by querying phone number's owner
            // Get on_behalf_of_business_info from current phone
            $phoneNumberId = config('whatsapp.phone_number_id');
            $phoneInfoResponse = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$phoneNumberId}", [
                    'fields' => 'verified_name,display_phone_number,account_mode,certificate,code_verification_status,quality_rating,name_status'
                ]);

            $phoneInfo = $phoneInfoResponse->successful() ? $phoneInfoResponse->json() : null;

            // Manual approach: If you have multiple WABA IDs, add them to config
            $additionalWABAIds = config('whatsapp.additional_waba_ids', []);

            foreach ($additionalWABAIds as $wabaId) {
                if ($wabaId === $currentWABAId) continue; // Skip current WABA

                $phoneResponse = Http::withToken($accessToken)
                    ->get("https://graph.facebook.com/v21.0/{$wabaId}/phone_numbers", [
                        'fields' => 'id,verified_name,display_phone_number,quality_rating,code_verification_status,name_status'
                    ]);

                if ($phoneResponse->successful()) {
                    $phones = $phoneResponse->json()['data'] ?? [];

                    $wabaResponse = Http::withToken($accessToken)
                        ->get("https://graph.facebook.com/v21.0/{$wabaId}", [
                            'fields' => 'id,name,currency,timezone_id,message_template_namespace,account_review_status'
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
                        : null
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get business accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getDashboardStats()
    {
        try {
            $userId = auth()->id() ?? 1;

            $stats = [
                'total_contacts' => WhatsAppContact::where('user_id', $userId)->count(),
                'total_messages' => WhatsAppMessage::where('user_id', $userId)->count(),
                'unread_messages' => WhatsAppContact::where('user_id', $userId)->sum('unread_count'),
                'incoming_messages' => WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'incoming')->count(),
                'outgoing_messages' => WhatsAppMessage::where('user_id', $userId)
                    ->where('direction', 'outgoing')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all messages
     */
    public function getMessages(Request $request)
    {
        try {
            $userId = auth()->id() ?? 1; // Default to user 1
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
                    'data' => $messages
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
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve messages',
                'error' => $e->getMessage()
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
                'data' => $message
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Message not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get all contacts
     */
    public function getContacts(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $userId = auth()->id() ?? 1; // Default to user 1 if not authenticated

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
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contacts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get messages for a specific contact
     */
    public function getContactMessages($contactId, Request $request)
    {
        try {
            $perPage = $request->get('per_page', 100);
            $userId = auth()->id() ?? 1;

            $messages = WhatsAppMessage::with(['contact'])
                ->where('user_id', $userId)
                ->where('contact_id', $contactId)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $messages
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contact messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all messages from a contact as read
     */
    public function markContactMessagesAsRead($contactId)
    {
        try {
            $userId = auth()->id() ?? 1;

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
                    $this->whatsapp->markMessageAsRead($message->message_id);

                    // Update in database
                    $message->is_read = true;
                    $message->read_at = now();
                    $message->status = 'read';
                    $message->save();

                    $markedCount++;

                    \Log::info('Message marked as read', [
                        'message_id' => $message->message_id,
                        'contact_id' => $contactId
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to mark message as read in WhatsApp API', [
                        'message_id' => $message->message_id,
                        'error' => $e->getMessage()
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
                'updated_count' => $markedCount
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark messages as read',
                'error' => $e->getMessage()
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
            $account = $this->getWhatsAppAccount();

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
                'total' => count($formattedTemplates)
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get template detail by name
     */
    public function getTemplateByName($name, Request $request)
    {
        try {
            $wabaId = config('whatsapp.business_account_id');
            $accessToken = config('whatsapp.access_token');

            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$wabaId}/message_templates", [
                    'name' => $name,
                    'fields' => 'name,status,category,language,components,id,rejected_reason'
                ]);

            if ($response->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to retrieve template',
                    'error' => $response->json()
                ], $response->status());
            }

            $templates = $response->json()['data'] ?? [];

            if (empty($templates)) {
                return response()->json([
                    'success' => false,
                    'message' => "Template '{$name}' not found"
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $templates
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve template',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
