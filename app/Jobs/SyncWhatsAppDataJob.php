<?php

namespace App\Jobs;

use App\Services\WhatsAppDataSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncWhatsAppDataJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var int
     */
    public $tries = 3;

    /**
     * @var int
     */
    public $timeout = 1800;

    /**
     * @var array<int>
     */
    public $backoff = [30, 90, 180];

    protected int $consentId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $consentId)
    {
        $this->consentId = $consentId;
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsAppDataSyncService $whatsAppDataSyncService): void
    {
        $whatsAppDataSyncService->syncByConsentId($this->consentId);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncWhatsAppDataJob failed permanently', [
            'consent_id' => $this->consentId,
            'error' => $exception->getMessage(),
        ]);
    }
}
