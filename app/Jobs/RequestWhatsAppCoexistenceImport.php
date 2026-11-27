<?php

namespace App\Jobs;

use App\Services\WhatsAppCoexistenceImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class RequestWhatsAppCoexistenceImport implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $importId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->importId))->releaseAfter(45)->expireAfter(90)];
    }

    public function handle(WhatsAppCoexistenceImportService $service): void
    {
        $service->requestSync($this->importId);
    }
}
