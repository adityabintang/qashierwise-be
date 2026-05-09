<?php

namespace App\Jobs;

use App\Models\CapiEvent;
use App\Services\MetaConversionsApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCapiEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public readonly int $userId,
        public readonly string $eventName,
        public readonly array $userData,
        public readonly array $customData = [],
        public readonly ?string $eventId = null,
        public readonly string $actionSource = 'other',
        public readonly string $messagingChannel = 'whatsapp',
        public readonly ?int $contactId = null
    ) {}

    public function handle(MetaConversionsApiService $capiService): void
    {
        $capiService->sendEvent(
            userId: $this->userId,
            eventName: $this->eventName,
            userData: $this->userData,
            customData: $this->customData,
            eventId: $this->eventId,
            actionSource: $this->actionSource,
            messagingChannel: $this->messagingChannel,
            contactId: $this->contactId
        );
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SendCapiEventJob permanently failed', [
            'user_id' => $this->userId,
            'event_name' => $this->eventName,
            'event_id' => $this->eventId,
            'error' => $exception->getMessage(),
        ]);

        // Mark the pending capi_event record as failed if it exists
        if ($this->eventId) {
            CapiEvent::where('event_id', $this->eventId)
                ->where('status', 'pending')
                ->update([
                    'status' => 'failed',
                    'meta_response' => ['error' => $exception->getMessage()],
                ]);
        }
    }
}
