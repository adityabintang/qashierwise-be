<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessAiAgentMessage implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [10, 30, 60];

    /**
     * The WhatsApp account.
     */
    protected WhatsAppAccount $account;

    /**
     * The WhatsApp contact.
     */
    protected WhatsAppContact $contact;

    /**
     * The message text.
     */
    protected string $messageText;

    /**
     * Create a new job instance.
     */
    public function __construct(
        WhatsAppAccount $account,
        WhatsAppContact $contact,
        string $messageText
    ) {
        $this->account = $account;
        $this->contact = $contact;
        $this->messageText = $messageText;
    }

    /**
     * Execute the job.
     */
    public function handle(AiAgentService $aiAgentService): void
    {
        try {
            $aiAgentService->processMessage(
                $this->account,
                $this->contact,
                $this->messageText
            );
        } catch (\Exception $e) {
            Log::error('ProcessAiAgentMessage job failed', [
                'account_id' => $this->account->id,
                'contact_id' => $this->contact->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAiAgentMessage job failed permanently', [
            'account_id' => $this->account->id,
            'contact_id' => $this->contact->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
