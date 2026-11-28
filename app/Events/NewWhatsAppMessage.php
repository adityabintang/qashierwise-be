<?php

namespace App\Events;

use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewWhatsAppMessage implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public $contact;

    /**
     * Create a new event instance.
     */
    public function __construct(WhatsAppMessage $message, WhatsAppContact $contact)
    {
        $this->message = $message;
        $this->contact = $contact;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to user's private channel
        return [
            new PrivateChannel('whatsapp.'.$this->message->user_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.new';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'message' => self::messagePayload($this->message),
            'contact' => [
                'id' => $this->contact->id,
                'wa_id' => $this->contact->wa_id,
                'name' => $this->contact->name,
                'profile_pic_url' => $this->contact->profile_pic_url,
                'last_message_at' => $this->contact->last_message_at?->toISOString(),
                'last_message_text' => $this->contact->last_message_text,
                'unread_count' => $this->contact->unread_count,
            ],
        ];
    }

    public static function messagePayload(WhatsAppMessage $message): array
    {
        return [
            'id' => $message->id,
            'message_id' => $message->message_id,
            'contact_id' => $message->contact_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'content' => $message->content,
            'body' => $message->body,
            'is_revoked' => $message->is_revoked,
            'media_url' => $message->media_url,
            'caption' => $message->caption,
            'filename' => $message->filename,
            'template_name' => $message->template_name,
            'buttons' => $message->buttons,
            'list_sections' => $message->list_sections,
            'button_text' => $message->button_text,
            'interactive_details' => $message->interactive_details,
            'latitude' => $message->latitude,
            'longitude' => $message->longitude,
            'location_name' => $message->location_name,
            'location_address' => $message->location_address,
            'metadata' => $message->metadata,
            'status' => $message->status,
            'is_read' => $message->is_read,
            'is_history_import' => $message->is_history_import ?? false,
            'sent_at' => $message->sent_at?->toISOString(),
            'created_at' => $message->created_at->toISOString(),
            'delivered_at' => $message->delivered_at?->toISOString(),
            'read_at' => $message->read_at?->toISOString(),
        ];
    }
}
