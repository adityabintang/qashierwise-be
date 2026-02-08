<?php

namespace App\Services;

use App\Models\AiAgentConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ConversationSummarizer
{
    protected TokenEstimator $tokenEstimator;

    protected SummaryValidator $summaryValidator;

    protected IntentTracker $intentTracker;

    public function __construct(
        TokenEstimator $tokenEstimator,
        SummaryValidator $summaryValidator,
        IntentTracker $intentTracker
    ) {
        $this->tokenEstimator = $tokenEstimator;
        $this->summaryValidator = $summaryValidator;
        $this->intentTracker = $intentTracker;
    }

    /**
     * Check if conversation needs summarization.
     *
     * @param  AiAgentConversation  $conversation  The conversation to check
     * @return bool True if summarization is needed
     */
    public function shouldSummarize(AiAgentConversation $conversation): bool
    {
        // Check if summarization is enabled
        if (! config('conversation.summarization.enabled', true)) {
            return false;
        }

        $messages = $conversation->messages ?? [];

        // Get thresholds from config
        $messageCountThreshold = config('conversation.summarization.message_count_threshold', 6);
        $tokenThreshold = config('conversation.summarization.token_threshold', 800);
        $minSubstantiveMessages = config('conversation.summarization.min_substantive_messages', 3);

        // Must have at least the configured number of messages
        if (count($messages) < $messageCountThreshold) {
            return false;
        }

        // Check if last message is a short confirmation (exclude from triggering)
        $lastMessage = end($messages);
        if ($lastMessage && isset($lastMessage['content'])) {
            $content = trim(strtolower($lastMessage['content']));
            $shortConfirmations = ['ok', 'ya', 'iya', 'yes', 'oke'];

            if (mb_strlen($content) <= 3 && in_array($content, $shortConfirmations, true)) {
                return false;
            }
        }

        // Count substantive messages (exclude very short messages)
        $substantiveCount = 0;
        foreach ($messages as $message) {
            if (isset($message['content'])) {
                $content = trim($message['content']);
                // Consider messages with more than 5 characters as substantive
                if (mb_strlen($content) > 5) {
                    $substantiveCount++;
                }
            }
        }

        // Must have at least the configured number of substantive messages
        if ($substantiveCount < $minSubstantiveMessages) {
            return false;
        }

        // Check token threshold
        $estimatedTokens = $this->tokenEstimator->estimateConversationTokens($messages);
        $shouldSummarize = $this->tokenEstimator->exceedsThreshold($messages, $tokenThreshold);

        if ($shouldSummarize) {
            // Log when summarization is triggered
            Log::info('Summarization triggered', [
                'conversation_id' => $conversation->id,
                'message_count' => count($messages),
                'substantive_count' => $substantiveCount,
                'estimated_tokens' => $estimatedTokens,
                'token_threshold' => $tokenThreshold,
                'trigger_reason' => 'token_threshold_exceeded',
            ]);

            return true;
        }

        // If we have enough messages and substantive messages, trigger summarization
        Log::info('Summarization triggered', [
            'conversation_id' => $conversation->id,
            'message_count' => count($messages),
            'substantive_count' => $substantiveCount,
            'estimated_tokens' => $estimatedTokens,
            'trigger_reason' => 'message_count_threshold',
        ]);

        return true;
    }

    /**
     * Generate summary from conversation messages.
     *
     * @param  array  $messages  Array of conversation messages
     * @return array|null Summary array or null if generation fails
     */
    public function generateSummary(array $messages): ?array
    {
        $startTime = microtime(true);
        $originalTokens = $this->tokenEstimator->estimateConversationTokens($messages);

        try {
            Log::info('Starting summary generation', [
                'message_count' => count($messages),
                'estimated_tokens' => $originalTokens,
            ]);

            // Build summarization system prompt
            $systemPrompt = $this->buildSummarizationPrompt();

            // Call LLM with summarization prompt
            $response = $this->callLLMForSummarization($systemPrompt, $messages);

            if (! $response) {
                Log::warning('LLM returned empty response for summarization');

                Log::error('Summary generation failed', [
                    'reason' => 'empty_llm_response',
                    'message_count' => count($messages),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return null;
            }

            // Parse JSON response
            $summary = $this->parseJsonResponse($response);

            if (! $summary) {
                Log::warning('Failed to parse JSON response from LLM', ['response' => substr($response, 0, 200)]);

                // Retry with stricter prompt
                Log::info('Retrying summarization with stricter prompt');
                $stricterPrompt = $this->buildStricterSummarizationPrompt();
                $retryResponse = $this->callLLMForSummarization($stricterPrompt, $messages);

                if ($retryResponse) {
                    $summary = $this->parseJsonResponse($retryResponse);
                }

                // If still no summary after retry, parseJsonResponse will create fallback
                if (! $summary) {
                    Log::warning('Summary generation used fallback after retry failed', [
                        'message_count' => count($messages),
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    ]);
                }
            }

            // Validate response using SummaryValidator
            if (! $this->summaryValidator->validate($summary)) {
                Log::error('Summary generation failed', [
                    'reason' => 'validation_failed',
                    'errors' => $this->summaryValidator->getErrors(),
                    'summary' => $summary,
                    'message_count' => count($messages),
                    'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                ]);

                return null;
            }

            // Add generated_at timestamp
            $summary['generated_at'] = now()->toIso8601String();

            // Calculate token savings
            $summaryTokens = $this->tokenEstimator->estimateTokens(json_encode($summary));
            $tokenSavings = $originalTokens - $summaryTokens;
            $savingsPercentage = $originalTokens > 0 ? round(($tokenSavings / $originalTokens) * 100, 2) : 0;

            // Log successful summary generation with token savings
            Log::info('Summary generation successful', [
                'message_count' => count($messages),
                'original_tokens' => $originalTokens,
                'summary_tokens' => $summaryTokens,
                'token_savings' => $tokenSavings,
                'savings_percentage' => $savingsPercentage,
                'intent' => $summary['intent'] ?? 'unknown',
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return $summary;

        } catch (\Exception $e) {
            Log::error('Summary generation failed', [
                'reason' => 'exception',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'message_count' => count($messages),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ]);

            return null;
        }
    }

    /**
     * Build summarization system prompt.
     *
     * @return string The system prompt for summarization
     */
    protected function buildSummarizationPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah modul peringkas percakapan (conversation summarizer).

Tugas kamu:
- Meringkas percakapan user dan bot menjadi informasi inti saja
- Fokus pada tujuan user dan status terkini
- HANYA gunakan informasi yang eksplisit disebutkan
- JANGAN menambahkan asumsi atau interpretasi
- JANGAN menulis ulang percakapan
- JANGAN memberi saran atau jawaban

Ambil dan ringkas hal-hal berikut jika ada:
- Tujuan utama user
- Produk / menu yang diminati
- Data penting (tanggal, jam, jumlah orang, jumlah item)
- Status proses (masih tanya, sudah pilih, menunggu pembayaran, selesai)
- Informasi penting lain yang mempengaruhi langkah berikutnya

Jika informasi belum lengkap, tulis apa yang masih kurang.

Gunakan format JSON persis seperti di bawah. Jangan menambahkan teks di luar JSON.

{
  "summary": "Ringkasan singkat 1–2 kalimat.",
  "intent": "browse_menu | order_food | reservation | payment | general_question | unknown",
  "key_data": {
    "products": [],
    "reservation_date": null,
    "reservation_time": null,
    "people_count": null,
    "order_items": [],
    "total_estimate": null
  },
  "missing_information": []
}
PROMPT;
    }

    /**
     * Build stricter summarization prompt for retry.
     *
     * @return string The stricter system prompt
     */
    protected function buildStricterSummarizationPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah modul peringkas percakapan. Tugas kamu HANYA menghasilkan JSON yang valid.

PENTING:
- Output HARUS berupa JSON yang valid
- JANGAN tambahkan teks apapun di luar JSON
- JANGAN tambahkan penjelasan atau komentar
- HANYA JSON murni

Format JSON yang HARUS kamu ikuti:

{
  "summary": "Ringkasan singkat percakapan dalam 1-2 kalimat",
  "intent": "browse_menu",
  "key_data": {
    "products": [],
    "reservation_date": null,
    "reservation_time": null,
    "people_count": null,
    "order_items": [],
    "total_estimate": null
  },
  "missing_information": []
}

Intent yang valid: browse_menu, order_food, reservation, payment, general_question, unknown

Ringkas percakapan berikut dan kembalikan HANYA JSON:
PROMPT;
    }

    /**
     * Call LLM API for summarization.
     *
     * @param  string  $systemPrompt  The system prompt
     * @param  array  $messages  The conversation messages
     * @return string|null The LLM response content or null if failed
     */
    protected function callLLMForSummarization(string $systemPrompt, array $messages): ?string
    {
        try {
            $config = config('services.byteplus_ark');
            $url = $config['base_url'].'/chat/completions';

            // Build messages array for LLM
            $llmMessages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            foreach ($messages as $msg) {
                $role = match ($msg['type'] ?? 'user') {
                    'human' => 'user',
                    'ai' => 'assistant',
                    default => 'user'
                };

                $llmMessages[] = [
                    'role' => $role,
                    'content' => $msg['content'] ?? '',
                ];
            }

            // Get LLM settings from config
            $temperature = config('conversation.summarization.llm_temperature', 0.3);
            $maxTokens = config('conversation.summarization.llm_max_tokens', 500);

            // Make API call
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$config['api_key'],
                'Content-Type' => 'application/json',
            ])->timeout(30)->post($url, [
                'model' => $config['model'],
                'messages' => $llmMessages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $choice = $data['choices'][0] ?? null;

                if ($choice && isset($choice['message']['content'])) {
                    return $choice['message']['content'];
                }
            }

            Log::warning('LLM API call failed for summarization', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('LLM API exception during summarization', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Parse JSON response from LLM.
     *
     * @param  string  $response  The LLM response
     * @return array|null Parsed JSON array or null if invalid
     */
    protected function parseJsonResponse(string $response): ?array
    {
        // Try to extract JSON from response (in case LLM adds extra text)
        $response = trim($response);

        // Look for JSON object in the response
        if (preg_match('/\{.*\}/s', $response, $matches)) {
            $jsonString = $matches[0];

            try {
                $decoded = json_decode($jsonString, true);

                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Exception $e) {
                Log::warning('JSON decode exception', ['error' => $e->getMessage()]);
            }
        }

        // Try direct decode as fallback
        try {
            $decoded = json_decode($response, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        } catch (\Exception $e) {
            Log::warning('Direct JSON decode failed', ['error' => $e->getMessage()]);
        }

        // FALLBACK: If JSON parsing fails, create summary from plain text response
        Log::info('JSON parsing failed, using plain text fallback', [
            'response_preview' => substr($response, 0, 100),
        ]);

        return $this->createSummaryFromPlainText($response);
    }

    /**
     * Create summary structure from plain text response.
     * This is a fallback when LLM doesn't return JSON.
     *
     * @param  string  $text  The plain text response from LLM
     * @return array Summary array with basic structure
     */
    protected function createSummaryFromPlainText(string $text): array
    {
        $text = trim($text);

        // Limit summary length
        $maxLength = 500;
        if (mb_strlen($text) > $maxLength) {
            $text = mb_substr($text, 0, $maxLength).'...';
        }

        // Try to detect intent from text content
        $intent = $this->detectIntentFromText($text);

        // Try to extract key information
        $keyData = $this->extractKeyDataFromText($text);

        Log::info('Created summary from plain text', [
            'detected_intent' => $intent,
            'summary_length' => mb_strlen($text),
            'has_key_data' => ! empty(array_filter($keyData)),
        ]);

        return [
            'summary' => $text,
            'intent' => $intent,
            'key_data' => $keyData,
            'missing_information' => [],
            'source' => 'plain_text_fallback',
        ];
    }

    /**
     * Detect intent from plain text content.
     *
     * @param  string  $text  The text to analyze
     * @return string Detected intent
     */
    protected function detectIntentFromText(string $text): string
    {
        $text = mb_strtolower($text);

        // Keywords for different intents
        $intentKeywords = [
            'order_food' => ['pesan', 'order', 'beli', 'mau', 'keranjang', 'checkout', 'konfirmasi'],
            'browse_menu' => ['menu', 'daftar', 'produk', 'tersedia', 'ada apa', 'lihat'],
            'payment' => ['bayar', 'pembayaran', 'qris', 'transfer', 'lunas', 'total'],
            'reservation' => ['reservasi', 'booking', 'pesan tempat', 'meja', 'tanggal'],
            'general_question' => ['tanya', 'info', 'jam', 'buka', 'tutup', 'lokasi', 'alamat'],
        ];

        // Count keyword matches for each intent
        $scores = [];
        foreach ($intentKeywords as $intent => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $score++;
                }
            }
            $scores[$intent] = $score;
        }

        // Get intent with highest score
        arsort($scores);
        $topIntent = array_key_first($scores);

        // Return top intent if it has at least one match, otherwise unknown
        return $scores[$topIntent] > 0 ? $topIntent : 'unknown';
    }

    /**
     * Extract key data from plain text.
     *
     * @param  string  $text  The text to analyze
     * @return array Key data array
     */
    protected function extractKeyDataFromText(string $text): array
    {
        $keyData = [
            'products' => [],
            'reservation_date' => null,
            'reservation_time' => null,
            'people_count' => null,
            'order_items' => [],
            'total_estimate' => null,
        ];

        // Try to extract numbers (could be quantities, prices, etc.)
        if (preg_match_all('/\d+/', $text, $matches)) {
            $numbers = $matches[0];

            // Look for price patterns (Rp followed by number)
            if (preg_match('/Rp\s*[\d.,]+/', $text, $priceMatch)) {
                $price = preg_replace('/[^\d]/', '', $priceMatch[0]);
                if ($price) {
                    $keyData['total_estimate'] = (float) $price;
                }
            }

            // Look for quantity patterns (number followed by "porsi", "pcs", etc.)
            if (preg_match('/(\d+)\s*(porsi|pcs|buah|item)/i', $text, $qtyMatch)) {
                $keyData['people_count'] = (int) $qtyMatch[1];
            }
        }

        // Try to extract product names (common food items)
        $commonProducts = [
            'nasi goreng', 'mie goreng', 'ayam goreng', 'dimsum', 'teh', 'kopi',
            'sate', 'bakso', 'soto', 'gado-gado', 'rendang', 'seafood',
        ];

        $textLower = mb_strtolower($text);
        foreach ($commonProducts as $product) {
            if (mb_strpos($textLower, $product) !== false) {
                $keyData['products'][] = $product;
            }
        }

        return $keyData;
    }

    /**
     * Get context for LLM call (either summary or full messages).
     * OPTIMIZED: Limits messages to reduce token usage to <1000
     *
     * @param  AiAgentConversation  $conversation  The conversation
     * @return array Context array with messages or summary
     */
    public function getContextForLLM(AiAgentConversation $conversation): array
    {
        try {
            // Get configured limit for recent messages (default 4 for token optimization)
            $maxRecentMessages = config('conversation.summarization.max_recent_messages', 4);

            // Check if conversation has summary
            if ($conversation->hasSummary()) {
                $summary = $conversation->getSummary();

                if ($summary && is_array($summary)) {
                    // Get configured number of recent messages for immediate context
                    $recentMessagesCount = config('conversation.summarization.recent_messages_count', 3);
                    $recentMessages = $conversation->getRecentMessages($recentMessagesCount);

                    // Build context with summary + recent messages
                    $context = [];

                    // Add summary as a system-like message
                    $summaryText = $this->formatSummaryForContext($summary);
                    $context[] = [
                        'type' => 'system',
                        'content' => $summaryText,
                        'timestamp' => $summary['generated_at'] ?? now()->toIso8601String(),
                    ];

                    // Add recent messages
                    foreach ($recentMessages as $message) {
                        $context[] = $message;
                    }

                    return $context;
                }
            }

            // OPTIMIZED: Limit messages even without summary to control token usage
            // Only return the last N messages to keep tokens under 1000
            $allMessages = $conversation->messages ?? [];

            if (count($allMessages) > $maxRecentMessages) {
                // Return only recent messages to save tokens
                return array_slice($allMessages, -$maxRecentMessages);
            }

            return $allMessages;

        } catch (\Exception $e) {
            Log::error('Error getting context for LLM', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Fallback to limited messages on error
            $allMessages = $conversation->messages ?? [];

            return array_slice($allMessages, -4);
        }
    }

    /**
     * Format summary for use as context in LLM call.
     * Ultra-compact format to minimize tokens.
     *
     * @param  array  $summary  The summary array
     * @return string Formatted summary text
     */
    protected function formatSummaryForContext(array $summary): string
    {
        $parts = [];

        // Add summary (compact)
        if (isset($summary['summary'])) {
            $parts[] = "PREV:{$summary['summary']}";
        }

        // Add intent (compact)
        if (isset($summary['intent'])) {
            $parts[] = "INTENT:{$summary['intent']}";
        }

        // Add key data if present (ultra-compact)
        if (isset($summary['key_data']) && is_array($summary['key_data'])) {
            $keyData = $summary['key_data'];

            if (! empty($keyData['products'])) {
                $parts[] = 'PRODUCTS:'.implode(',', $keyData['products']);
            }

            if (! empty($keyData['order_items'])) {
                $items = [];
                foreach ($keyData['order_items'] as $item) {
                    $product = $item['product'] ?? '?';
                    $qty = $item['quantity'] ?? 1;
                    $items[] = "{$product}x{$qty}";
                }
                $parts[] = 'CART:'.implode(',', $items);
            }

            if (! empty($keyData['total'])) {
                $parts[] = "TOTAL:{$keyData['total']}";
            }
        }

        return implode('|', $parts);
    }

    /**
     * Store summary in conversation.
     *
     * @param  AiAgentConversation  $conversation  The conversation
     * @param  array  $summary  The summary to store
     */
    public function storeSummary(AiAgentConversation $conversation, array $summary): void
    {
        try {
            // Check for intent change before storing
            $previousIntent = $this->intentTracker->getCurrentIntent($conversation);
            $newIntent = $summary['intent'] ?? 'unknown';

            // Use the model's setSummary method which handles storage
            $conversation->setSummary($summary);

            // Update intent if present in summary
            if (isset($summary['intent'])) {
                $this->intentTracker->updateIntent($conversation, $summary['intent']);
            }

            // Log intent change if detected
            if ($previousIntent && $previousIntent !== $newIntent) {
                Log::info('Intent change detected', [
                    'conversation_id' => $conversation->id,
                    'previous_intent' => $previousIntent,
                    'new_intent' => $newIntent,
                    'summary_generated_at' => $summary['generated_at'] ?? null,
                ]);
            }

            Log::info('Summary stored successfully', [
                'conversation_id' => $conversation->id,
                'intent' => $newIntent,
                'intent_changed' => $previousIntent && $previousIntent !== $newIntent,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to store summary', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - let the system continue without summary
        }
    }

    /**
     * Clear summary from conversation.
     *
     * @param  AiAgentConversation  $conversation  The conversation
     */
    public function clearSummary(AiAgentConversation $conversation): void
    {
        try {
            // Use the model's clearSummary method
            $conversation->clearSummary();

            Log::info('Summary cleared successfully', [
                'conversation_id' => $conversation->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear summary', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - let the system continue
        }
    }
}
