<?php

namespace App\Services\AiAgent\Followup;

use App\Jobs\SendFollowupJob;
use App\Models\AiAgentConversation;
use Illuminate\Support\Facades\Log;

/**
 * Schedules and invalidates follow-up jobs for a conversation.
 *
 * Pending jobs are not actively cancelled from Redis. Instead we use
 * `followup_state_started_at` as an idempotency key: jobs read it at dispatch
 * time, store it on themselves, and bail out at execution time if the value
 * on the conversation has changed (which happens whenever the customer
 * replies or the state machine transitions to a new state).
 *
 * Side-effect surface is intentionally minimal: this service only writes
 * `followup_count`, `followup_state_started_at`, and `pending_followup_job_id`
 * via the conversation helpers — it never touches WhatsApp directly.
 */
class FollowupScheduler
{
    /**
     * Begin (or restart) a follow-up cycle for the conversation's current
     * flow_state. Called whenever the state machine enters a fresh state.
     *
     * Returns true when a job was actually dispatched.
     */
    public function schedule(AiAgentConversation $conversation): bool
    {
        if (! $this->shouldSchedule($conversation)) {
            return false;
        }

        $conversation->startFollowupCycle();
        $delay = $this->intervalSeconds($conversation);

        SendFollowupJob::dispatch(
            $conversation->id,
            $conversation->getFlowState(),
            $conversation->followup_state_started_at?->toIso8601String() ?? now()->toIso8601String(),
        )
            ->onQueue('ai-agent')
            ->delay(now()->addSeconds($delay));

        Log::info('Follow-up scheduled', [
            'conversation_id' => $conversation->id,
            'flow_state' => $conversation->getFlowState(),
            'delay_seconds' => $delay,
            'cycle_started_at' => $conversation->followup_state_started_at?->toIso8601String(),
        ]);

        return true;
    }

    /**
     * Invalidate any pending follow-up job. Done by resetting the
     * conversation's follow-up tracking — when the next scheduled job runs it
     * will detect the started_at mismatch and exit without doing anything.
     */
    public function cancel(AiAgentConversation $conversation): void
    {
        if ($conversation->followup_count === 0 && $conversation->followup_state_started_at === null) {
            return;
        }

        $conversation->resetFollowup();
        Log::info('Follow-up cancelled (cycle reset)', [
            'conversation_id' => $conversation->id,
        ]);
    }

    /**
     * Whether a follow-up should be scheduled for the conversation's current
     * state. Centralises every "should we bother" check so callers don't have
     * to know the rules.
     */
    protected function shouldSchedule(AiAgentConversation $conversation): bool
    {
        $state = $conversation->getFlowState();
        if ($state === null) {
            return false;
        }

        $agent = $conversation->aiAgent;
        if (! $agent || ! $agent->is_active) {
            return false;
        }

        $settings = $agent->settings ?? [];
        if (! ($settings['followup_enabled'] ?? true)) {
            return false;
        }

        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();
        if ($contact && property_exists($contact, 'ai_active') && $contact->ai_active === false) {
            return false;
        }

        return true;
    }

    /**
     * Seconds until the next follow-up should fire, based on merchant config.
     * Always returns a positive integer.
     */
    public function intervalSeconds(AiAgentConversation $conversation): int
    {
        $agent = $conversation->aiAgent;
        $minutes = (int) ($agent?->settings['followup_interval_minutes'] ?? 10);
        if ($minutes < 1) {
            $minutes = 10;
        }

        return $minutes * 60;
    }
}
