<?php

namespace App\Jobs;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AiAgentService;
use App\Services\MessageBuffer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Process Buffered Messages Job
 * 
 * This job is dispatched after the debounce period to:
 * 1. Collect all buffered messages for a user
 * 2. Merge/deduplicate them
 * 3. Send single request to AI Agent
 * 
 * This prevents multiple AI responses when user sends rapid messages.
 */
class ProcessBufferedMessages implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [5, 15, 30];

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected WhatsAppAccount $account,
        protected WhatsAppContact $contact
    ) {}

    /**
     * Execute the job.
     */
    public function handle(MessageBuffer $messageBuffer, AiAgentService $aiAgentService): void
    {
        $phoneNumber = $this->contact->wa_id;
        
        try {
            Log::info('Processing buffered messages', [
                'phone_number' => $phoneNumber,
                'contact_id' => $this->contact->id,
                'account_id' => $this->account->id,
            ]);
            
            // Flush all buffered messages
            $messages = $messageBuffer->flush($phoneNumber);
            
            if (empty($messages)) {
                Log::info('No messages in buffer, skipping', [
                    'phone_number' => $phoneNumber,
                ]);
                return;
            }
            
            // Merge messages (deduplicate and combine)
            $mergedMessage = $messageBuffer->mergeMessages($messages);
            
            if (empty($mergedMessage)) {
                Log::info('Merged message is empty, skipping', [
                    'phone_number' => $phoneNumber,
                ]);
                return;
            }
            
            Log::info('Sending merged message to AI Agent', [
                'phone_number' => $phoneNumber,
                'original_count' => count($messages),
                'merged_message_preview' => substr($mergedMessage, 0, 100),
            ]);
            
            // Process the merged message through AI Agent
            $aiAgentService->processMessage(
                $this->account,
                $this->contact,
                $mergedMessage
            );
            
            Log::info('Buffered messages processed successfully', [
                'phone_number' => $phoneNumber,
                'message_count' => count($messages),
            ]);
            
        } catch (\Exception $e) {
            Log::error('ProcessBufferedMessages job failed', [
                'phone_number' => $phoneNumber,
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
        Log::error('ProcessBufferedMessages job failed permanently', [
            'account_id' => $this->account->id,
            'contact_id' => $this->contact->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
