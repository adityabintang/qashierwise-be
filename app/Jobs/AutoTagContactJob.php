<?php

namespace App\Jobs;

use App\Models\WhatsAppContact;
use App\Services\MlTaggingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class AutoTagContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int    $tries   = 2;
    public int    $timeout = 15;

    public function __construct(
        private readonly int    $contactId,
        private readonly string $messageText,
    ) {}

    public function handle(MlTaggingService $mlTaggingService): void
    {
        $contact = WhatsAppContact::withoutGlobalScopes()->find($this->contactId);

        if (! $contact) {
            Log::warning('AutoTagContactJob: contact not found', ['contact_id' => $this->contactId]);
            return;
        }

        $mlTaggingService->applyTagToContact($contact, $this->messageText);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('AutoTagContactJob failed', [
            'contact_id' => $this->contactId,
            'error'      => $e->getMessage(),
        ]);
    }
}
