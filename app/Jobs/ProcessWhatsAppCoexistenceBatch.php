<?php

namespace App\Jobs;

use App\Services\WhatsAppCoexistenceImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWhatsAppCoexistenceBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [10, 60, 180];

    public function __construct(public int $batchId) {}

    public function handle(WhatsAppCoexistenceImportService $service): void
    {
        try {
            $service->processBatch($this->batchId);
        } catch (\Throwable) {
            throw new \RuntimeException('Could not process WhatsApp import batch '.$this->batchId);
        }
    }
}
