<?php

namespace App\Jobs;

use App\Models\AiAgentDripSchedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Poll job: every minute, find drip rows whose fire_at has elapsed and hand
 * each one off to a dedicated SendDripJob. We don't fire from inside this job
 * to keep the polling cheap and the per-row work isolated (a single hang
 * doesn't block the rest of the batch).
 */
class DispatchDueDrips implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function handle(): void
    {
        $due = AiAgentDripSchedule::where('status', AiAgentDripSchedule::STATUS_PENDING)
            ->where('fire_at', '<=', now())
            ->limit(100)
            ->get();

        if ($due->isEmpty()) {
            return;
        }

        Log::info('Drip dispatcher: found due rows', [
            'count' => $due->count(),
        ]);

        foreach ($due as $row) {
            SendDripJob::dispatch($row->id)->onQueue('ai-agent');
        }
    }
}
