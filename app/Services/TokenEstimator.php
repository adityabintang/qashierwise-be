<?php

namespace App\Services;

class TokenEstimator
{
    /**
     * Estimate token count for text.
     * Uses approximation: 1 token ≈ 2 chars for Indonesian, 4 chars for English.
     *
     * @param string $text The text to estimate tokens for
     * @param string $language Language code ('id' for Indonesian, 'en' for English)
     * @return int Estimated token count
     */
    public function estimateTokens(string $text, string $language = 'id'): int
    {
        $charCount = mb_strlen($text, 'UTF-8');
        
        // Use different ratios based on language
        $charsPerToken = match ($language) {
            'id' => 2, // Indonesian: 1 token ≈ 2 characters
            'en' => 4, // English: 1 token ≈ 4 characters
            default => 2, // Default to Indonesian
        };
        
        return (int) ceil($charCount / $charsPerToken);
    }

    /**
     * Estimate total tokens for conversation messages.
     *
     * @param array $messages Array of message objects with 'content' field
     * @return int Total estimated token count
     */
    public function estimateConversationTokens(array $messages): int
    {
        $totalTokens = 0;
        
        foreach ($messages as $message) {
            if (isset($message['content']) && is_string($message['content'])) {
                $totalTokens += $this->estimateTokens($message['content']);
            }
        }
        
        return $totalTokens;
    }

    /**
     * Check if token threshold is exceeded.
     *
     * @param array $messages Array of message objects
     * @param int $threshold Token threshold (default: 800)
     * @return bool True if threshold is exceeded
     */
    public function exceedsThreshold(array $messages, int $threshold = 800): bool
    {
        return $this->estimateConversationTokens($messages) >= $threshold;
    }
}
