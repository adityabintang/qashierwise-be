<?php

namespace App\Jobs;

use App\Models\AiAgentDripSchedule;
use App\Services\AiAgent\Drip\DripContentRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Fires a single drip row. Re-validates that the underlying condition
 * still holds (cart still abandoned, etc) before sending — the row might
 * have been queued hours ago and a lot can change in between.
 */
class SendDripJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public int $scheduleId) {}

    public function handle(DripContentRenderer $renderer): void
    {
        DB::transaction(function () use ($renderer) {
            // Pessimistic lock prevents a concurrent worker from firing the
            // same row twice.
            $row = AiAgentDripSchedule::lockForUpdate()->find($this->scheduleId);
            if (! $row) {
                return;
            }
            if ($row->status !== AiAgentDripSchedule::STATUS_PENDING) {
                return;
            }
            if ($row->fire_at?->gt(now())) {
                return;
            }

            $conversation = $row->conversation;
            if (! $conversation) {
                $row->update([
                    'status' => AiAgentDripSchedule::STATUS_SKIPPED,
                    'fired_at' => now(),
                ]);

                return;
            }

            $skipReason = $this->skipReason($conversation, $row);
            if ($skipReason !== null) {
                Log::info('Drip skipped', [
                    'schedule_id' => $row->id,
                    'conversation_id' => $conversation->id,
                    'sequence' => $row->sequence,
                    'step' => $row->step,
                    'reason' => $skipReason,
                ]);
                $row->update([
                    'status' => AiAgentDripSchedule::STATUS_SKIPPED,
                    'fired_at' => now(),
                ]);

                return;
            }

            $sent = $renderer->send($conversation, $row);
            $row->update([
                'status' => $sent
                    ? AiAgentDripSchedule::STATUS_SENT
                    : AiAgentDripSchedule::STATUS_SKIPPED,
                'fired_at' => now(),
            ]);

            if ($sent) {
                $conversation->last_drip_at = now();
                $conversation->save();
            }

            Log::info('Drip processed', [
                'schedule_id' => $row->id,
                'conversation_id' => $conversation->id,
                'sequence' => $row->sequence,
                'step' => $row->step,
                'sent' => $sent,
            ]);
        });
    }

    /**
     * Pre-send guards. Returns a short reason string when the drip should be
     * skipped, or null when it's clear to send.
     */
    protected function skipReason($conversation, AiAgentDripSchedule $row): ?string
    {
        $agent = $conversation->aiAgent;
        if (! $agent || ! $agent->is_active) {
            return 'agent_inactive';
        }

        $settings = $agent->settings ?? [];
        if (! ($settings['drip_enabled'] ?? true)) {
            return 'drip_disabled';
        }

        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();
        if (! $contact) {
            return 'no_contact';
        }
        if ($contact->drips_unsubscribed_at !== null) {
            return 'unsubscribed';
        }
        if ($contact->drips_paused_until !== null && now()->lt($contact->drips_paused_until)) {
            return 'paused';
        }

        // Daily cap (configurable; default 3).
        $cap = (int) ($settings['max_drips_per_24h'] ?? 3);
        $sent24h = AiAgentDripSchedule::where('conversation_id', $conversation->id)
            ->where('status', AiAgentDripSchedule::STATUS_SENT)
            ->where('fired_at', '>=', now()->subDay())
            ->count();
        if ($sent24h >= $cap) {
            return 'daily_cap_reached';
        }

        // Sequence-specific freshness check.
        if ($row->sequence === AiAgentDripSchedule::SEQ_ABANDONED_CART) {
            if (! empty($conversation->getCart()) && $conversation->getFlowState() === null) {
                return null;
            }

            return 'cart_no_longer_abandoned';
        }

        return null;
    }
}
