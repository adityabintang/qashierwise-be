<?php

namespace App\Services;

use App\Jobs\DeliverWebhook;
use App\Models\UserWebhook;

class WebhookForwardingService
{
    public function dispatch(int $userId, string $eventType, array $data): void
    {
        $webhooks = UserWebhook::where('user_id', $userId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (! $webhook->subscribesTo($eventType)) {
                continue;
            }

            $payload = [
                'event' => $eventType,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ];

            DeliverWebhook::dispatch($webhook, $eventType, $payload)
                ->onQueue('webhooks');
        }
    }
}
