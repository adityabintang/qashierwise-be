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
        'expires_at',
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
        ];
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
    public function addMessage(string $role, string $content): void
    {
        $messages = $this->messages ?? [];

        // Add new message
        $messages[] = [
            'role' => $role,
            'content' => $content,
            'timestamp' => now()->toIso8601String(),
        ];

        // Keep only last 10 messages (sliding window)
        if (count($messages) > 10) {
            $messages = array_slice($messages, -10);
        }

        $this->messages = $messages;

        // Extend expiration by 24 hours from now
        $this->expires_at = now()->addHours(24);

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
     * Get pending order from context.
     */
    public function getPendingOrder(): ?array
    {
        return $this->order_context['pending_order'] ?? null;
    }

    /**
     * Set pending order in context.
     */
    public function setPendingOrder(array $items): void
    {
        $orderContext = $this->order_context ?? [];
        $orderContext['pending_order'] = [
            'items' => $items,
            'created_at' => now()->toIso8601String(),
        ];

        $this->order_context = $orderContext;
        $this->save();
    }

    /**
     * Clear pending order from context.
     */
    public function clearPendingOrder(): void
    {
        $orderContext = $this->order_context ?? [];
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
    }
}
