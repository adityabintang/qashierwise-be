<?php

namespace App\Jobs;

use App\Models\AiAgentConversation;
use App\Services\AiAgent\Followup\FollowupScheduler;
use App\Services\CatalogOrderFlowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Sends a single follow-up reminder, increments the counter, and either
 * re-schedules itself for the next tick or triggers terminal auto-cancel.
 *
 * Cancellation is implicit: the conversation's `followup_state_started_at`
 * works as an idempotency key. If a customer reply (or state transition)
 * changes that value between dispatch and execution, the job exits at the
 * re-validation step without sending anything.
 */
class SendFollowupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $conversationId,
        public string $scheduledForState,
        public string $cycleStartedAtIso,
    ) {}

    public function handle(
        FollowupScheduler $scheduler,
        CatalogOrderFlowService $catalogFlow,
    ): void {
        $conversation = AiAgentConversation::find($this->conversationId);
        if (! $conversation) {
            return;
        }

        if (! $this->stillValid($conversation)) {
            Log::info('Follow-up job stale, skipping', [
                'conversation_id' => $this->conversationId,
                'expected_state' => $this->scheduledForState,
                'current_state' => $conversation->getFlowState(),
                'expected_cycle' => $this->cycleStartedAtIso,
                'current_cycle' => $conversation->followup_state_started_at?->toIso8601String(),
            ]);

            return;
        }

        $agent = $conversation->aiAgent;
        if (! $agent || ! $agent->is_active) {
            return;
        }

        $settings = $agent->settings ?? [];
        if (! ($settings['followup_enabled'] ?? true)) {
            return;
        }

        if ($this->outsideSessionWindow($conversation)) {
            Log::info('Follow-up skipped: outside 24h session window', [
                'conversation_id' => $this->conversationId,
            ]);

            return;
        }

        $deferUntil = $this->deferForQuietHours($settings);
        if ($deferUntil !== null) {
            Log::info('Follow-up deferred for quiet hours', [
                'conversation_id' => $this->conversationId,
                'defer_until' => $deferUntil->toIso8601String(),
            ]);

            self::dispatch(
                $this->conversationId,
                $this->scheduledForState,
                $this->cycleStartedAtIso,
            )->onQueue('ai-agent')->delay($deferUntil);

            return;
        }

        $max = (int) ($settings['followup_max_count'] ?? 3);
        $autoCancel = (bool) ($settings['followup_auto_cancel'] ?? true);

        if ($conversation->followup_count >= $max) {
            if ($autoCancel) {
                AutoCancelConversationJob::dispatch(
                    $this->conversationId,
                    $this->scheduledForState,
                    $this->cycleStartedAtIso,
                )->onQueue('ai-agent');
                Log::info('Follow-up max reached → dispatching auto-cancel', [
                    'conversation_id' => $this->conversationId,
                    'state' => $this->scheduledForState,
                    'count' => $conversation->followup_count,
                    'max' => $max,
                ]);
            } else {
                Log::info('Follow-up max reached, auto_cancel off', [
                    'conversation_id' => $this->conversationId,
                    'state' => $this->scheduledForState,
                ]);
            }

            return;
        }

        // Re-fetch the related models we need to push a WhatsApp message.
        $account = $agent->whatsappAccount()->withoutGlobalScope('userAccounts')->first();
        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();
        if (! $account || ! $contact) {
            Log::warning('Follow-up skipped: missing account or contact', [
                'conversation_id' => $this->conversationId,
                'account_id' => $account?->id,
                'contact_id' => $contact?->id,
            ]);

            return;
        }

        $sent = $catalogFlow->sendStateReminder($account, $contact, $conversation, $agent);
        if (! $sent) {
            Log::warning('Follow-up: state has no reminder template', [
                'conversation_id' => $this->conversationId,
                'state' => $this->scheduledForState,
            ]);

            return;
        }

        $newCount = $conversation->recordFollowupSent();
        Log::info('Follow-up sent', [
            'conversation_id' => $this->conversationId,
            'state' => $this->scheduledForState,
            'count' => $newCount,
            'max' => $max,
        ]);

        // Schedule the next tick. The terminal auto-cancel branch above will
        // catch the run that exceeds $max — we always schedule one more tick.
        self::dispatch(
            $this->conversationId,
            $this->scheduledForState,
            $this->cycleStartedAtIso,
        )
            ->onQueue('ai-agent')
            ->delay(now()->addSeconds($scheduler->intervalSeconds($conversation)));
    }

    /**
     * The job is stale if the conversation has since transitioned to a
     * different state, started a brand new cycle, or cleared its flow state.
     * Either condition means the customer (or state machine) has moved on
     * and the reminder is no longer relevant.
     */
    protected function stillValid(AiAgentConversation $conversation): bool
    {
        if ($conversation->getFlowState() !== $this->scheduledForState) {
            return false;
        }

        $started = $conversation->followup_state_started_at;
        if (! $started) {
            return false;
        }

        return $started->equalTo(Carbon::parse($this->cycleStartedAtIso));
    }

    /**
     * WhatsApp Business API only allows free-form replies within 24 hours of
     * the customer's last inbound message. After that, only approved templates
     * work — and follow-ups don't use templates.
     */
    protected function outsideSessionWindow(AiAgentConversation $conversation): bool
    {
        $last = $conversation->last_activity_at ?? $conversation->updated_at;
        if (! $last) {
            return false;
        }

        return $last->lt(now()->subHours(24));
    }

    /**
     * Returns the next moment outside the merchant's quiet hours, or null
     * when the current moment is already inside the active window. Quiet
     * hours are interpreted in the application timezone.
     */
    protected function deferForQuietHours(array $settings): ?Carbon
    {
        $start = $settings['quiet_hours_start'] ?? null;
        $end = $settings['quiet_hours_end'] ?? null;
        if (! $start || ! $end || $start === $end) {
            return null;
        }

        $now = now();
        [$sH, $sM] = array_map('intval', explode(':', $start));
        [$eH, $eM] = array_map('intval', explode(':', $end));

        $quietStart = $now->copy()->setTime($sH, $sM, 0);
        $quietEnd = $now->copy()->setTime($eH, $eM, 0);

        // Overnight quiet window (start > end means it crosses midnight).
        if ($quietStart->greaterThan($quietEnd)) {
            if ($now->greaterThanOrEqualTo($quietStart)) {
                return $quietEnd->copy()->addDay();
            }
            if ($now->lessThan($quietEnd)) {
                return $quietEnd;
            }

            return null;
        }

        // Daytime quiet window (rare, but support it).
        if ($now->greaterThanOrEqualTo($quietStart) && $now->lessThan($quietEnd)) {
            return $quietEnd;
        }

        return null;
    }
}
