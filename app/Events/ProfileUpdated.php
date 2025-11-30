<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $userId;

    public $profileData;

    public $changeType;

    /**
     * Create a new event instance.
     */
    public function __construct(int $userId, array $profileData, string $changeType = 'updated')
    {
        $this->userId = $userId;
        $this->profileData = $profileData;
        $this->changeType = $changeType;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('whatsapp.'.$this->userId),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'profile.updated';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'change_type' => $this->changeType,
            'profile' => $this->profileData,
            'updated_at' => now()->toISOString(),
        ];
    }
}
