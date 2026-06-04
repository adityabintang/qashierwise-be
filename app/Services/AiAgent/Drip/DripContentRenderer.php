<?php

namespace App\Services\AiAgent\Drip;

use App\Models\AiAgentConversation;
use App\Models\AiAgentDripSchedule;
use App\Services\CatalogOrderFlowService;
use Illuminate\Support\Facades\Log;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\Button;
use Netflie\WhatsAppCloudApi\Message\ButtonReply\ButtonAction;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Renders and pushes a drip message. Lives outside the job so the firing
 * logic (locking, status transitions) stays separate from "what does the
 * message look like".
 *
 * Returns true when a message was sent, false when send failed at the
 * WhatsApp API level (job will mark the row as skipped, not retried).
 */
class DripContentRenderer
{
    public function send(AiAgentConversation $conversation, AiAgentDripSchedule $row): bool
    {
        return match ($row->sequence) {
            AiAgentDripSchedule::SEQ_ABANDONED_CART => $this->sendAbandonedCart($conversation, $row),
            default => false,
        };
    }

    protected function sendAbandonedCart(AiAgentConversation $conversation, AiAgentDripSchedule $row): bool
    {
        $cart = $conversation->getCart();
        if (empty($cart)) {
            return false;
        }

        $itemCount = count($cart);
        $subtotal = 0;
        foreach ($cart as $item) {
            $subtotal += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
        }
        $subtotalStr = 'Rp '.number_format($subtotal, 0, ',', '.');

        $body = $row->step === 1
            ? "🛒 Cart kamu masih disimpan ya — {$itemCount} item, {$subtotalStr}. Mau lanjutkan?"
            : "🛒 Pesanan kamu masih menunggu ({$itemCount} item, {$subtotalStr}). Lanjutkan checkout atau hapus cart?";

        $agent = $conversation->aiAgent;
        $account = $agent?->whatsappAccount()->withoutGlobalScope('userAccounts')->first();
        $contact = $conversation->whatsappContact()->withoutGlobalScopes()->first();
        if (! $agent || ! $account || ! $contact) {
            return false;
        }

        try {
            $client = new WhatsAppCloudApi([
                'from_phone_number_id' => $account->phone_number_id,
                'access_token' => $account->access_token,
            ]);

            $buttons = [
                new Button(CatalogOrderFlowService::BTN_RESUME_CHECKOUT, '✅ Checkout'),
            ];
            if ($row->step >= 2) {
                $buttons[] = new Button(CatalogOrderFlowService::BTN_CLEAR_CART, '🗑️ Hapus Cart');
            }
            $buttons[] = new Button(CatalogOrderFlowService::BTN_STOP_DRIP, '🔕 Stop Reminder');

            $client->sendButton(
                $contact->wa_id,
                $body,
                new ButtonAction($buttons),
                null,
                null
            );

            return true;
        } catch (\Throwable $e) {
            Log::error('Drip send failed', [
                'schedule_id' => $row->id,
                'sequence' => $row->sequence,
                'step' => $row->step,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
