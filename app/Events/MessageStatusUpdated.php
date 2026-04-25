<?php

namespace App\Events;

use App\Jobs\DeliverWebhook;
use App\Models\WhatsAppMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MessageStatusUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    /**
     * Create a new event instance.
     */
    public function __construct(WhatsAppMessage $message)
    {
        $this->message = $message;

        // Log broadcast attempt for debugging
        Log::info('MessageStatusUpdated event created', [
            'message_id' => $message->id,
            'wa_message_id' => $message->message_id,
            'user_id' => $message->user_id,
            'status' => $message->status,
            'has_user_id' => ! empty($message->user_id),
        ]);

        $this->dispatchWebhooks();
    }

    private function dispatchWebhooks(): void
    {
        $webhooks = $this->message->user()?->webhooks()
            ->active()
            ->whereJsonContains('events', 'message.status_updated')
            ->get();

        if (!$webhooks || $webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => 'message.status_updated',
            'timestamp' => now()->unix(),
            'data' => [
                'message_id' => $this->message->id,
                'wa_message_id' => $this->message->message_id,
                'contact_id' => $this->message->contact_id,
                'status' => $this->message->status,
                'delivered_at' => $this->message->delivered_at?->toIso8601String(),
                'read_at' => $this->message->read_at?->toIso8601String(),
            ],
        ];

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch(
                $webhook->id,
                'message.status_updated',
                $payload
            );
        }
    }

    /**
     * Determine if this event should broadcast.
     */
    public function broadcastWhen(): bool
    {
        // Only broadcast if user_id exists
        if (empty($this->message->user_id)) {
            Log::warning('MessageStatusUpdated broadcast skipped: missing user_id', [
                'message_id' => $this->message->id,
                'wa_message_id' => $this->message->message_id,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channel = 'whatsapp.'.$this->message->user_id;

        Log::info('MessageStatusUpdated broadcasting to channel', [
            'channel' => $channel,
            'message_id' => $this->message->id,
            'status' => $this->message->status,
        ]);

        return [
            new PrivateChannel($channel),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.status';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'message_id' => $this->message->message_id,
            'contact_id' => $this->message->contact_id,
            'status' => $this->message->status,
            'delivered_at' => $this->message->delivered_at?->toISOString(),
            'read_at' => $this->message->read_at?->toISOString(),
        ];
    }
}
