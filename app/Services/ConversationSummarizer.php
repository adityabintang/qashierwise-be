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
     * @param AiAgentConversation $conversation The conversation to check
     * @return bool True if summarization is needed
     */
    public function shouldSummarize(AiAgentConversation $conversation): bool
    {
        // Check if summarization is enabled
        if (!config('conversation.summarization.enabled', true)) {
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
     * @param array $messages Array of conversation messages
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

            if (!$response) {
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

            if (!$summary) {
                Log::warning('Failed to parse JSON response from LLM', ['response' => $response]);
                
                // Retry with stricter prompt
                Log::info('Retrying summarization with stricter prompt');
                $stricterPrompt = $this->buildStricterSummarizationPrompt();
                $retryResponse = $this->callLLMForSummarization($stricterPrompt, $messages);
                
                if ($retryResponse) {
                    $summary = $this->parseJsonResponse($retryResponse);
                }

                if (!$summary) {
                    Log::error('Summary generation failed', [
                        'reason' => 'json_parse_failed_after_retry',
                        'retry_response' => $retryResponse,
                        'message_count' => count($messages),
                        'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                    ]);
                    return null;
                }
            }

            // Validate response using SummaryValidator
            if (!$this->summaryValidator->validate($summary)) {
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
     * @param string $systemPrompt The system prompt
     * @param array $messages The conversation messages
     * @return string|null The LLM response content or null if failed
     */
    protected function callLLMForSummarization(string $systemPrompt, array $messages): ?string
    {
        try {
            $config = config('services.byteplus_ark');
            $url = $config['base_url'] . '/chat/completions';

            // Build messages array for LLM
            $llmMessages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            foreach ($messages as $msg) {
                $role = match($msg['type'] ?? 'user') {
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
                'Authorization' => 'Bearer ' . $config['api_key'],
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
     * @param string $response The LLM response
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

        return null;
    }

    /**
     * Get context for LLM call (either summary or full messages).
     *
     * @param AiAgentConversation $conversation The conversation
     * @return array Context array with messages or summary
     */
    public function getContextForLLM(AiAgentConversation $conversation): array
    {
        try {
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
            
            // Fallback: return full message history if no summary or error
            return $conversation->messages ?? [];
            
        } catch (\Exception $e) {
            Log::error('Error getting context for LLM', [
                'conversation_id' => $conversation->id,
                'error' => $e->getMessage(),
            ]);
            
            // Fallback to full messages on error
            return $conversation->messages ?? [];
        }
    }

    /**
     * Format summary for use as context in LLM call.
     *
     * @param array $summary The summary array
     * @return string Formatted summary text
     */
    protected function formatSummaryForContext(array $summary): string
    {
        $text = "=== Ringkasan Percakapan Sebelumnya ===\n\n";
        
        // Add summary
        if (isset($summary['summary'])) {
            $text .= "Ringkasan: {$summary['summary']}\n\n";
        }
        
        // Add intent
        if (isset($summary['intent'])) {
            $text .= "Intent: {$summary['intent']}\n\n";
        }
        
        // Add key data if present
        if (isset($summary['key_data']) && is_array($summary['key_data'])) {
            $keyData = $summary['key_data'];
            
            if (!empty($keyData['products'])) {
                $text .= "Produk yang diminati: " . implode(', ', $keyData['products']) . "\n";
            }
            
            if (!empty($keyData['order_items'])) {
                $text .= "Item pesanan:\n";
                foreach ($keyData['order_items'] as $item) {
                    $product = $item['product'] ?? 'Unknown';
                    $quantity = $item['quantity'] ?? 1;
                    $text .= "  - {$product} x{$quantity}\n";
                }
            }
            
            if (isset($keyData['reservation_date'])) {
                $text .= "Tanggal reservasi: {$keyData['reservation_date']}\n";
            }
            
            if (isset($keyData['reservation_time'])) {
                $text .= "Waktu reservasi: {$keyData['reservation_time']}\n";
            }
            
            if (isset($keyData['people_count'])) {
                $text .= "Jumlah orang: {$keyData['people_count']}\n";
            }
            
            if (isset($keyData['total_estimate'])) {
                $text .= "Estimasi total: Rp " . number_format($keyData['total_estimate'], 0, ',', '.') . "\n";
            }
        }
        
        // Add missing information if present
        if (isset($summary['missing_information']) && is_array($summary['missing_information']) && !empty($summary['missing_information'])) {
            $text .= "\nInformasi yang masih kurang: " . implode(', ', $summary['missing_information']) . "\n";
        }
        
        $text .= "\n=== Lanjutan Percakapan ===\n";
        
        return $text;
    }

    /**
     * Store summary in conversation.
     *
     * @param AiAgentConversation $conversation The conversation
     * @param array $summary The summary to store
     * @return void
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
     * @param AiAgentConversation $conversation The conversation
     * @return void
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
