<?php

namespace App\Jobs;

use App\Models\WhatsAppBroadcast;
use App\Services\WhatsAppBroadcastSender;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendWhatsAppBroadcast implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout = 70;

    public int $uniqueFor = 600;

    public function __construct(public int $broadcastId) {}

    public function uniqueId(): string
    {
        return (string) $this->broadcastId;
    }

    public function handle(WhatsAppBroadcastSender $sender): void
    {
        $broadcast = WhatsAppBroadcast::find($this->broadcastId);
        if ($broadcast?->recipients()->where('status', 'queued')->exists()
            && $sender->process($broadcast)
            && $broadcast->recipients()->where('status', 'queued')->exists()
            && config('queue.connections.'.($this->connection ?? config('queue.default')).'.driver') !== 'sync') {
            self::dispatch($broadcast->id)->delay(now()->addSeconds(2));
        }
    }
}
