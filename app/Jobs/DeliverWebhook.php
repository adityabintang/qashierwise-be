<?php

namespace App\Jobs;

use App\Models\UserWebhook;
use App\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(
        protected UserWebhook $webhook,
        protected string $eventType,
        protected array $payload,
        protected ?int $deliveryId = null
    ) {}

    public function handle(): void
    {
        $delivery = $this->resolveDelivery();

        $body = json_encode($this->payload);
        $signature = 'sha256='.hash_hmac('sha256', $body, $this->webhook->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->eventType,
                    'User-Agent' => 'QashierWise-Webhook/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($this->webhook->url);

            $delivery->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_body' => substr($response->body(), 0, 1000),
                'attempts' => $delivery->attempts + 1,
                'delivered_at' => $response->successful() ? now() : null,
            ]);

            if (! $response->successful()) {
                Log::warning('Webhook delivery failed', [
                    'webhook_id' => $this->webhook->id,
                    'delivery_id' => $delivery->id,
                    'status' => $response->status(),
                ]);

                throw new \RuntimeException("Webhook returned HTTP {$response->status()}");
            }

            Log::info('Webhook delivered successfully', [
                'webhook_id' => $this->webhook->id,
                'delivery_id' => $delivery->id,
                'event' => $this->eventType,
            ]);

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $delivery->update([
                'status' => 'failed',
                'response_body' => $e->getMessage(),
                'attempts' => $delivery->attempts + 1,
            ]);

            Log::warning('Webhook connection failed', [
                'webhook_id' => $this->webhook->id,
                'delivery_id' => $delivery->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        WebhookDelivery::where('id', $this->deliveryId)->update(['status' => 'failed']);

        Log::error('Webhook delivery permanently failed', [
            'webhook_id' => $this->webhook->id,
            'event' => $this->eventType,
            'error' => $exception->getMessage(),
        ]);
    }

    private function resolveDelivery(): WebhookDelivery
    {
        if ($this->deliveryId) {
            return WebhookDelivery::findOrFail($this->deliveryId);
        }

        $delivery = WebhookDelivery::create([
            'user_webhook_id' => $this->webhook->id,
            'event_type' => $this->eventType,
            'payload' => $this->payload,
            'status' => 'pending',
            'attempts' => 0,
        ]);

        $this->deliveryId = $delivery->id;

        return $delivery;
    }
}
