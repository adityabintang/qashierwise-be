<?php

namespace App\Events;

use App\Jobs\DeliverWebhook;
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

        $this->dispatchWebhooks();
    }

    private function dispatchWebhooks(): void
    {
        $webhooks = $this->message->user()->first()?->webhooks()
            ->active()
            ->whereJsonContains('events', 'message.incoming')
            ->get();

        if (!$webhooks || $webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => 'message.incoming',
            'timestamp' => now()->unix(),
            'data' => [
                'id' => $this->message->id,
                'message_id' => $this->message->message_id,
                'contact' => [
                    'name' => $this->contact->name,
                    'phone' => $this->contact->wa_id,
                ],
                'type' => $this->message->type,
                'content' => $this->message->content,
                'body' => $this->message->body,
                'media_url' => $this->message->media_url,
                'caption' => $this->message->caption,
                'filename' => $this->message->filename,
                'template_name' => $this->message->template_name,
                'buttons' => $this->message->buttons,
                'list_sections' => $this->message->list_sections,
                'button_text' => $this->message->button_text,
                'latitude' => $this->message->latitude,
                'longitude' => $this->message->longitude,
                'location_name' => $this->message->location_name,
                'location_address' => $this->message->location_address,
                'metadata' => $this->message->metadata,
                'status' => $this->message->status,
                'received_at' => $this->message->created_at->toIso8601String(),
            ],
        ];

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch(
                $webhook->id,
                'message.incoming',
                $payload
            );
        }
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
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
            'message' => [
                'id' => $this->message->id,
                'message_id' => $this->message->message_id,
                'contact_id' => $this->message->contact_id,
                'direction' => $this->message->direction,
                'type' => $this->message->type,
                'content' => $this->message->content,
                'body' => $this->message->body,
                'media_url' => $this->message->media_url,
                'caption' => $this->message->caption,
                'filename' => $this->message->filename,
                'template_name' => $this->message->template_name,
                'buttons' => $this->message->buttons,
                'list_sections' => $this->message->list_sections,
                'button_text' => $this->message->button_text,
                'latitude' => $this->message->latitude,
                'longitude' => $this->message->longitude,
                'location_name' => $this->message->location_name,
                'location_address' => $this->message->location_address,
                'metadata' => $this->message->metadata,
                'status' => $this->message->status,
                'created_at' => $this->message->created_at->toISOString(),
            ],
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
}
