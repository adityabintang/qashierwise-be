<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAgentConversation extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ai_agent_id',
        'whatsapp_contact_id',
        'messages',
        'order_context',
        'current_order_id',
        'current_qris_transaction_id',
        'cache_response_id',
        'cache_expires_at',
        'expires_at',
        'last_activity_at',
        'followup_count',
        'followup_state_started_at',
        'pending_followup_job_id',
        'last_drip_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'messages' => 'array',
            'order_context' => 'array',
            'expires_at' => 'datetime',
            'cache_expires_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'followup_count' => 'integer',
            'followup_state_started_at' => 'datetime',
            'last_drip_at' => 'datetime',
        ];
    }

    /**
     * Mark conversation as active right now. Called whenever the customer
     * sends a message. Resets the follow-up counter AND invalidates the
     * cycle-id so any in-flight SendFollowupJob exits at re-validation
     * instead of pinging an already-engaged customer.
     */
    public function markActivity(): void
    {
        $this->last_activity_at = now();
        $this->followup_count = 0;
        $this->followup_state_started_at = null;
        $this->pending_followup_job_id = null;
        $this->save();

        app(\App\Services\AiAgent\Drip\DripScheduler::class)->onConversationResumed($this);
    }

    /**
     * Start a new follow-up cycle for the state the conversation just entered.
     * Counter goes back to zero and the clock that the scheduler reads from
     * is reset to now.
     */
    public function startFollowupCycle(): void
    {
        $this->followup_count = 0;
        $this->followup_state_started_at = now();
        $this->pending_followup_job_id = null;
        $this->save();
    }

    /**
     * Record that one follow-up was just sent. Returns the new count so the
     * caller can decide whether the cap has been reached.
     */
    public function recordFollowupSent(): int
    {
        $this->followup_count = ($this->followup_count ?? 0) + 1;
        $this->last_drip_at = now();
        $this->save();

        return $this->followup_count;
    }

    /**
     * Drop all follow-up tracking, e.g. when the conversation exits the state
     * machine entirely or is auto-cancelled.
     */
    public function resetFollowup(): void
    {
        $this->followup_count = 0;
        $this->followup_state_started_at = null;
        $this->pending_followup_job_id = null;
        $this->save();
    }

    /**
     * Get the AI agent that owns the conversation.
     */
    public function aiAgent(): BelongsTo
    {
        return $this->belongsTo(AiAgent::class);
    }

    /**
     * Get the WhatsApp contact for this conversation.
     */
    public function whatsappContact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class);
    }

    /**
     * Add a message to the conversation with sliding window (max 10 messages).
     */
    public function addMessage(string $type, string $content): void
    {
        $messages = $this->messages ?? [];

        // Add new message with type (human/ai) instead of role
        $messages[] = [
            'type' => $type, // 'human' or 'ai'
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 10 messages (sliding window)
        if (count($messages) > 10) {
            $messages = array_slice($messages, -10);
        }

        $this->messages = $messages;

        // Extend expiration by 24 hours from now
        // $this->expires_at = now()->addHours(24);
        $this->expires_at = now()->addHours(1);

        $this->save();
    }

    /**
     * Check if conversation is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Strip the legacy `pending_order` key from order_context. The old
     * pre-state-machine flow stored a draft order here before user
     * confirmation; today the state machine owns that handoff, but rows from
     * before the cutover may still carry stale data, so the existing call
     * sites are kept as defensive cleanup.
     */
    public function clearPendingOrder(): void
    {
        $orderContext = $this->order_context ?? [];
        if (! array_key_exists('pending_order', $orderContext)) {
            return;
        }
        unset($orderContext['pending_order']);

        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Get cart items from context.
     */
    public function getCart(): array
    {
        return $this->order_context['cart'] ?? [];
    }

    /**
     * Update cart in context.
     */
    public function updateCart(array $cart): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['cart'] = $cart;

        $this->order_context = $orderContext;
        $this->save();

        app(\App\Services\AiAgent\Drip\DripScheduler::class)->onCartUpdated($this);
    }

    /**
     * Clear cart from context.
     */
    public function clearCart(): void
    {
        $orderContext = $this->order_context ?? [];
        unset($orderContext['cart']);

        $this->order_context = $orderContext;
        $this->save();

        app(\App\Services\AiAgent\Drip\DripScheduler::class)->onCartUpdated($this);
    }

    /**
     * Get the current order associated with this conversation.
     */
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'current_order_id');
    }

    /**
     * Get the current QRIS transaction associated with this conversation.
     */
    public function currentQrisTransaction(): BelongsTo
    {
        return $this->belongsTo(QrisTransaction::class, 'current_qris_transaction_id');
    }

    /**
     * Set the current order for this conversation.
     */
    public function setCurrentOrder(int $orderId): void
    {
        $this->current_order_id = $orderId;
        $this->save();
    }

    /**
     * Get the current order model.
     */
    public function getCurrentOrder(): ?Order
    {
        return $this->currentOrder;
    }

    /**
     * Set the current QRIS transaction for this conversation.
     * Also stores the ID in order_context so it can be recovered
     * if clearPaymentContext() is called before the user checks status.
     */
    public function setCurrentQrisTransaction(int $transactionId): void
    {
        $this->current_qris_transaction_id = $transactionId;

        $orderContext = $this->order_context ?? [];
        $orderContext['last_qris_transaction_id'] = $transactionId;
        $this->order_context = $orderContext;

        $this->save();
    }

    /**
     * Get the current QRIS transaction model.
     */
    public function getCurrentQrisTransaction(): ?QrisTransaction
    {
        return $this->currentQrisTransaction;
    }

    /**
     * Clear payment context (order and QRIS transaction).
     */
    public function clearPaymentContext(): void
    {
        $this->current_order_id = null;
        $this->current_qris_transaction_id = null;
        $this->save();
    }

    /**
     * Get conversation summary from order_context.
     */
    public function getSummary(): ?array
    {
        return $this->order_context['summary'] ?? null;
    }

    /**
     * Set conversation summary in order_context.
     */
    public function setSummary(array $summary): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['summary'] = $summary;
        $orderContext['summarized_at'] = now()->toIso8601String();

        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Check if conversation has summary.
     */
    public function hasSummary(): bool
    {
        return isset($this->order_context['summary']);
    }

    /**
     * Clear summary from order_context.
     */
    public function clearSummary(): void
    {
        $orderContext = $this->order_context ?? [];
        unset($orderContext['summary']);
        unset($orderContext['summarized_at']);
        unset($orderContext['last_intent']);

        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Get message count.
     */
    public function getMessageCount(): int
    {
        return count($this->messages ?? []);
    }

    /**
     * Get recent messages (last N messages).
     */
    public function getRecentMessages(int $count = 3): array
    {
        $messages = $this->messages ?? [];

        if (empty($messages)) {
            return [];
        }

        // Return last N messages
        return array_slice($messages, -$count);
    }

    /**
     * Set cache response ID from BytePlus Responses API.
     * Cache expires in 72 hours (max allowed by BytePlus).
     */
    public function setCacheResponseId(string $responseId): void
    {
        $this->cache_response_id = $responseId;
        // BytePlus cache max retention is 72 hours
        $this->cache_expires_at = now()->addHours(72);
        $this->save();
    }

    /**
     * Get cache response ID if still valid.
     */
    public function getCacheResponseId(): ?string
    {
        // Return null if cache is expired
        if ($this->cache_expires_at && $this->cache_expires_at->isPast()) {
            return null;
        }

        return $this->cache_response_id;
    }

    /**
     * Check if cache is valid and available.
     */
    public function hasCacheResponseId(): bool
    {
        return $this->getCacheResponseId() !== null;
    }

    /**
     * Clear cache response ID.
     */
    public function clearCacheResponseId(): void
    {
        $this->cache_response_id = null;
        $this->cache_expires_at = null;
        $this->save();
    }

    public function getCurrentMenuPage(): int
    {
        return $this->order_context['current_menu_page'] ?? 1;
    }

    public function setCurrentMenuPage(int $page): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['current_menu_page'] = $page;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function clearCurrentMenuPage(): void
    {
        $orderContext = $this->order_context ?? [];
        unset($orderContext['current_menu_page']);
        $this->order_context = $orderContext;
        $this->save();
    }

    public function getDeliveryType(): ?string
    {
        return $this->order_context['delivery_type'] ?? null;
    }

    public function setDeliveryType(string $type): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['delivery_type'] = $type;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->order_context['delivery_address'] ?? null;
    }

    public function setDeliveryAddress(string $address): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['delivery_address'] = $address;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function getDeliveryNotes(): ?string
    {
        return $this->order_context['delivery_notes'] ?? null;
    }

    public function setDeliveryNotes(?string $notes): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['delivery_notes'] = $notes;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function getOngkir(): float
    {
        return (float) ($this->order_context['ongkir'] ?? 0);
    }

    public function setOngkir(float $ongkir): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['ongkir'] = $ongkir;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function clearDeliveryContext(): void
    {
        $orderContext = $this->order_context ?? [];
        unset($orderContext['delivery_type']);
        unset($orderContext['delivery_address']);
        unset($orderContext['delivery_notes']);
        unset($orderContext['delivery_raw_info']);
        unset($orderContext['ongkir']);
        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Catalog order flow state machine.
     * Possible values: null | awaiting_fulfillment_choice | awaiting_delivery_info
     *                  | confirming_delivery_info | confirming_order_summary
     */
    public function getFlowState(): ?string
    {
        return $this->order_context['flow_state'] ?? null;
    }

    public function setFlowState(?string $state): void
    {
        $previous = $this->order_context['flow_state'] ?? null;

        $orderContext = $this->order_context ?? [];
        if ($state === null) {
            unset($orderContext['flow_state']);
        } else {
            $orderContext['flow_state'] = $state;
        }
        $this->order_context = $orderContext;
        $this->save();

        if ($state === $previous) {
            return;
        }

        // Single integration point for the follow-up scheduler: when a state
        // transition lands on a non-null state, start a fresh follow-up cycle
        // and dispatch the first SendFollowupJob. When state clears, any
        // pending job becomes stale via resetFollowup() and exits on its own.
        $scheduler = app(\App\Services\AiAgent\Followup\FollowupScheduler::class);
        if ($state === null) {
            $scheduler->cancel($this);
        } else {
            $scheduler->schedule($this);
        }
    }

    public function clearFlowState(): void
    {
        $this->setFlowState(null);
    }

    /**
     * Free-text delivery info supplied by the user.
     */
    public function getDeliveryRawInfo(): ?string
    {
        return $this->order_context['delivery_raw_info'] ?? null;
    }

    public function setDeliveryRawInfo(string $info): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['delivery_raw_info'] = $info;
        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Structured delivery fields parsed from the customer's free-text input.
     * Shape: ['name' => ?string, 'phone' => ?string, 'address' => ?string, 'note' => ?string]
     */
    public function getDeliveryParsed(): ?array
    {
        return $this->order_context['delivery_parsed'] ?? null;
    }

    public function setDeliveryParsed(array $parsed): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['delivery_parsed'] = $parsed;
        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Catalog order items (raw payload from WhatsApp `order` webhook).
     * Each item: ['product_retailer_id' => string, 'product_name' => string,
     *             'quantity' => int, 'item_price' => float, 'currency' => string]
     */
    public function getCatalogItems(): array
    {
        return $this->order_context['catalog_items'] ?? [];
    }

    public function setCatalogItems(array $items): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['catalog_items'] = $items;
        $this->order_context = $orderContext;
        $this->save();
    }

    public function clearCatalogItems(): void
    {
        $orderContext = $this->order_context ?? [];
        unset($orderContext['catalog_items']);
        $this->order_context = $orderContext;
        $this->save();
    }
}
