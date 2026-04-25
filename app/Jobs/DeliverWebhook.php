<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $webhookId,
        public string $eventType,
        public array $payload,
        public ?int $deliveryId = null,
    ) {
    }

    public function handle(): void
    {
        $webhook = Webhook::find($this->webhookId);
        if (!$webhook || !$webhook->isActive()) {
            return;
        }

        $delivery = $this->deliveryId
            ? WebhookDelivery::find($this->deliveryId)
            : WebhookDelivery::create([
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
                'payload' => $this->payload,
                'status' => 'pending',
            ]);

        if (!$delivery) {
            return;
        }

        try {
            $this->sendWebhook($webhook, $delivery);
        } catch (\Exception $e) {
            $delivery->markFailed($e->getMessage());
            $delivery->scheduleRetry();

            Log::warning('Webhook delivery failed', [
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWebhook(Webhook $webhook, WebhookDelivery $delivery): void
    {
        $payloadJson = json_encode($this->payload);
        $signature = hash_hmac('sha256', $payloadJson, $webhook->getSecret());
        $timestamp = now()->unix();

        $response = Http::timeout(30)
            ->withHeaders([
                'X-Webhook-Event' => $this->eventType,
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Timestamp' => $timestamp,
                'Content-Type' => 'application/json',
            ])
            ->post($webhook->url, $this->payload);

        if ($response->successful()) {
            $delivery->markDelivered();
            Log::info('Webhook delivered successfully', [
                'webhook_id' => $this->webhookId,
                'event_type' => $this->eventType,
            ]);
        } else {
            throw new \Exception("HTTP {$response->status()}: {$response->body()}");
        }
    }
}
