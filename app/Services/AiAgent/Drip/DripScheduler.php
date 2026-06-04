<?php

namespace App\Services\AiAgent\Drip;

use App\Models\AiAgentConversation;
use App\Models\AiAgentDripSchedule;
use Illuminate\Support\Facades\Log;

/**
 * Owns the lifecycle of drip-schedule rows. Callers ask for an event ("cart
 * went non-empty", "conversation finished") and this service decides whether
 * to add or cancel rows.
 *
 * Sequence-specific timing is hardcoded here — drips are operator-driven
 * (not merchant-driven) by design, so per-merchant config doesn't apply.
 *
 * NOT a sender. Firing is the job's responsibility (SendDripJob).
 */
class DripScheduler
{
    /**
     * Steps for Sequence A — cart abandoned (POS mode, no state machine).
     * Step 3 (24 h) intentionally omitted from MVP because it must be a Meta
     * template message; will be added once the template is approved.
     */
    protected const ABANDONED_CART_STEPS = [
        ['step' => 1, 'delay_minutes' => 30],
        ['step' => 2, 'delay_minutes' => 240],
    ];

    /**
     * Cart went non-empty (or had items added). Cancels any pending Sequence A
     * row for the conversation and queues a fresh one. Idempotent — calling
     * twice in a row simply refreshes the queue.
     */
    public function onCartUpdated(AiAgentConversation $conversation): void
    {
        if (! $this->shouldSchedule($conversation)) {
            return;
        }

        if (empty($conversation->getCart())) {
            $this->cancelSequence($conversation, AiAgentDripSchedule::SEQ_ABANDONED_CART);

            return;
        }

        // Don't queue drips while the state machine is actively driving the
        // conversation — follow-up handles those states.
        if ($conversation->getFlowState() !== null) {
            $this->cancelSequence($conversation, AiAgentDripSchedule::SEQ_ABANDONED_CART);

            return;
        }

        $this->cancelSequence($conversation, AiAgentDripSchedule::SEQ_ABANDONED_CART);

        foreach (self::ABANDONED_CART_STEPS as $stepInfo) {
            AiAgentDripSchedule::create([
                'conversation_id' => $conversation->id,
                'sequence' => AiAgentDripSchedule::SEQ_ABANDONED_CART,
                'step' => $stepInfo['step'],
                'fire_at' => now()->addMinutes($stepInfo['delay_minutes']),
                'status' => AiAgentDripSchedule::STATUS_PENDING,
            ]);
        }

        Log::info('Drip scheduled: abandoned cart', [
            'conversation_id' => $conversation->id,
            'steps' => count(self::ABANDONED_CART_STEPS),
        ]);
    }

    /**
     * Customer engaged (sent a message, completed the flow, etc). Cancel any
     * pending drips so they don't fire stale.
     */
    public function onConversationResumed(AiAgentConversation $conversation): void
    {
        $cancelled = AiAgentDripSchedule::where('conversation_id', $conversation->id)
            ->where('status', AiAgentDripSchedule::STATUS_PENDING)
            ->update(['status' => AiAgentDripSchedule::STATUS_CANCELLED]);

        if ($cancelled > 0) {
            Log::info('Drips cancelled (conversation resumed)', [
                'conversation_id' => $conversation->id,
                'count' => $cancelled,
            ]);
        }
    }

    /**
     * Cancel pending rows for one specific sequence on a conversation.
     */
    public function cancelSequence(AiAgentConversation $conversation, string $sequence): void
    {
        AiAgentDripSchedule::where('conversation_id', $conversation->id)
            ->where('sequence', $sequence)
            ->where('status', AiAgentDripSchedule::STATUS_PENDING)
            ->update(['status' => AiAgentDripSchedule::STATUS_CANCELLED]);
    }

    /**
     * Whether drips are wired up at all for this conversation. Merchant or
     * platform admin can disable, and individual contacts can opt out.
     */
    protected function shouldSchedule(AiAgentConversation $conversation): bool
    {
        $agent = $conversation->aiAgent;
        if (! $agent || ! $agent->is_active) {
            return false;
        }

        $settings = $agent->settings ?? [];
        if (! ($settings['drip_enabled'] ?? true)) {
            return false;
        }

        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();
        if (! $contact) {
            return false;
        }
        if ($contact->drips_unsubscribed_at !== null) {
            return false;
        }
        if ($contact->drips_paused_until !== null && now()->lt($contact->drips_paused_until)) {
            return false;
        }

        return true;
    }
}
