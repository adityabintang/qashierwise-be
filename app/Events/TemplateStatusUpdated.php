<?php

namespace App\Events;

use App\Jobs\DeliverWebhook;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateStatusUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public WhatsAppTemplate $template, public string $previousStatus)
    {
        $this->dispatchWebhooks();
    }

    private function dispatchWebhooks(): void
    {
        $webhooks = $this->template->whatsappAccount?->user()?->webhooks()
            ->active()
            ->whereJsonContains('events', 'template.status_changed')
            ->get();

        if (!$webhooks || $webhooks->isEmpty()) {
            return;
        }

        $payload = [
            'event' => 'template.status_changed',
            'timestamp' => now()->unix(),
            'data' => [
                'template_id' => $this->template->id,
                'template_name' => $this->template->name,
                'language' => $this->template->language,
                'previous_status' => $this->previousStatus,
                'status' => $this->template->status,
                'rejection_info' => $this->template->rejection_info,
                'changed_at' => $this->template->updated_at->toIso8601String(),
            ],
        ];

        foreach ($webhooks as $webhook) {
            DeliverWebhook::dispatch(
                $webhook->id,
                'template.status_changed',
                $payload
            );
        }
    }
}
