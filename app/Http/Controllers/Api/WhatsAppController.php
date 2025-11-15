<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
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
        return WhatsAppContact::firstOrCreate(
            [
                'whatsapp_account_id' => $this->getWhatsAppAccount()->id,
                'phone_number' => $phoneNumber,
            ],
            [
                'wa_id' => $phoneNumber,
            ]
        );
    }

    /**
     * Save message to database
     */
    private function saveMessage($contact, $type, $content, $response, $templateName = null, $templateLanguage = null)
    {
        $responseBody = $response->decodedBody();

        $message = WhatsAppMessage::create([
            'whatsapp_account_id' => $this->getWhatsAppAccount()->id,
            'whatsapp_contact_id' => $contact->id,
            'message_id' => $responseBody['messages'][0]['id'] ?? null,
            'wam_id' => $responseBody['messages'][0]['id'] ?? null,
            'direction' => 'outbound',
            'status' => 'sent',
            'type' => $type,
            'content' => is_array($content) ? json_encode($content) : $content,
            'template_name' => $templateName,
            'template_language' => $templateLanguage,
            'sent_at' => now(),
        ]);

        // Update contact's last message timestamp
        $contact->update(['last_message_at' => now()]);

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
            $response = $this->whatsapp->businessProfile();

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
            $data = $request->only([
                'about',
                'address',
                'description',
                'email',
                'vertical',
                'websites'
            ]);

            $response = $this->whatsapp->updateBusinessProfile($data);

            return response()->json([
                'success' => true,
                'message' => 'Business profile updated successfully',
                'data' => $response->decodedBody()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business profile',
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
            $perPage = $request->get('per_page', 15);
            $messages = WhatsAppMessage::with(['contact', 'account'])
                ->where('whatsapp_account_id', $this->getWhatsAppAccount()->id)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $messages
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
            $message = WhatsAppMessage::with(['contact', 'account', 'mediaFiles'])
                ->where('whatsapp_account_id', $this->getWhatsAppAccount()->id)
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
            $contacts = WhatsAppContact::where('whatsapp_account_id', $this->getWhatsAppAccount()->id)
                ->withCount('messages')
                ->orderBy('last_message_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $contacts
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
            $perPage = $request->get('per_page', 15);
            $messages = WhatsAppMessage::with(['contact'])
                ->where('whatsapp_account_id', $this->getWhatsAppAccount()->id)
                ->where('whatsapp_contact_id', $contactId)
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

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
}
