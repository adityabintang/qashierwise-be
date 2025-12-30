<?php

namespace App\Services;

use App\Models\AiAgentConversation;

class IntentTracker
{
    /**
     * Valid intent values.
     */
    private const VALID_INTENTS = [
        'browse_menu',
        'order_food',
        'reservation',
        'payment',
        'general_question',
        'unknown',
    ];

    /**
     * Get current intent from conversation context.
     *
     * @param AiAgentConversation $conversation The conversation to get intent from
     * @return string|null Current intent or null if not set
     */
    public function getCurrentIntent(AiAgentConversation $conversation): ?string
    {
        $orderContext = $conversation->order_context ?? [];
        
        return $orderContext['last_intent'] ?? null;
    }

    /**
     * Check if intent has changed.
     *
     * @param AiAgentConversation $conversation The conversation to check
     * @param string $newIntent The new intent to compare
     * @return bool True if intent has changed
     */
    public function hasIntentChanged(AiAgentConversation $conversation, string $newIntent): bool
    {
        $currentIntent = $this->getCurrentIntent($conversation);
        
        // If no current intent, it's not a change (it's the first intent)
        if ($currentIntent === null) {
            return false;
        }
        
        // Check if the new intent is different from current
        return $currentIntent !== $newIntent;
    }

    /**
     * Update intent in conversation context.
     *
     * @param AiAgentConversation $conversation The conversation to update
     * @param string $intent The new intent to store
     * @return void
     */
    public function updateIntent(AiAgentConversation $conversation, string $intent): void
    {
        $orderContext = $conversation->order_context ?? [];
        $orderContext['last_intent'] = $intent;
        
        $conversation->order_context = $orderContext;
        $conversation->save();
    }

    /**
     * Check if intent is valid.
     *
     * @param string $intent The intent to validate
     * @return bool True if intent is valid
     */
    public function isValidIntent(string $intent): bool
    {
        return in_array($intent, self::VALID_INTENTS, true);
    }

    /**
     * Get list of valid intents.
     *
     * @return array List of valid intent values
     */
    public function getValidIntents(): array
    {
        return self::VALID_INTENTS;
    }
}
