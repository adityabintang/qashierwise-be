<?php

namespace App\Jobs;

use App\Models\AiAgentConversation;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Terminal step of the follow-up flow.
 *
 * Triggered by SendFollowupJob once `followup_count >= followup_max_count` and
 * the merchant has `followup_auto_cancel = true`. Cancels the in-flight order
 * (if any), expires the QRIS link (if any), wipes the conversation flow state,
 * and tells the customer the order was cancelled because they went silent.
 *
 * Like SendFollowupJob, this re-validates state on entry — if the customer
 * replied or the flow moved on between dispatch and execution, it exits.
 */
class AutoCancelConversationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $conversationId,
        public string $scheduledForState,
        public string $cycleStartedAtIso,
    ) {}

    public function handle(): void
    {
        $conversation = AiAgentConversation::find($this->conversationId);
        if (! $conversation) {
            return;
        }

        if (! $this->stillValid($conversation)) {
            Log::info('Auto-cancel stale, skipping', [
                'conversation_id' => $this->conversationId,
                'expected_state' => $this->scheduledForState,
                'current_state' => $conversation->getFlowState(),
            ]);

            return;
        }

        $agent = $conversation->aiAgent;
        $account = $agent?->whatsappAccount()->withoutGlobalScope('userAccounts')->first();
        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();

        $orderId = null;
        $order = null;
        if ($conversation->current_order_id) {
            $order = Order::find($conversation->current_order_id);
            if ($order && $order->status !== Order::STATUS_CANCELLED) {
                $order->status = Order::STATUS_CANCELLED;
                $order->save();
                $orderId = $order->id;
            }
        }

        $qris = $conversation->getCurrentQrisTransaction();
        if ($qris && ! $qris->isExpired()) {
            $qris->markAsExpired();
            $qris->save();
        }

        // Order matters here: clear flow state LAST so any read at this moment
        // can still see the soon-to-be-cleared state for logging.
        $conversation->clearCatalogItems();
        $conversation->clearDeliveryContext();
        $conversation->clearPaymentContext();
        $conversation->clearPendingOrder();
        $conversation->clearCart();
        $conversation->clearFlowState();
        $conversation->resetFollowup();

        Log::info('Follow-up auto-cancel triggered', [
            'conversation_id' => $this->conversationId,
            'state' => $this->scheduledForState,
            'order_id' => $orderId,
            'qris_id' => $qris?->id,
            'reason' => 'no_response_after_max_followups',
        ]);

        if ($agent && $account && $contact) {
            try {
                $client = new \Netflie\WhatsAppCloudApi\WhatsAppCloudApi([
                    'from_phone_number_id' => $account->phone_number_id,
                    'access_token' => $account->access_token,
                ]);

                $orderRef = $order?->order_number ? " (No. {$order->order_number})" : '';
                $client->sendTextMessage(
                    $contact->wa_id,
                    "❌ Pesanan dibatalkan{$orderRef} karena tidak ada respons. "
                    .'Tap *Lihat Menu* untuk pesan ulang kapan saja.'
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send auto-cancel notice', [
                    'conversation_id' => $this->conversationId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

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
}
