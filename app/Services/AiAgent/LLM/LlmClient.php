<?php

namespace App\Services\AiAgent\LLM;

use App\Models\AiAgent;
use App\Services\AiPromptAnalytics;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BytePlus ARK chat-completions client.
 *
 * Two entry points:
 *   - call()                — first turn, may attach tools, retries 3× with backoff
 *   - callWithToolResults() — follow-up turn including tool outputs, no retry
 *
 * Returns one of:
 *   ['content'    => string]
 *   ['tool_calls' => array]
 *
 * Lives here (not as a generic HTTP wrapper) because BytePlus has provider-
 * specific message-shape requirements + analytics hooks tied to AiAgent.
 */
class LlmClient
{
    /**
     * First-turn call. Retries up to 3× with [10, 30, 60]s backoff. On
     * persistent failure, throws — callers fall back to non-LLM behavior.
     */
    public function call(string $systemPrompt, array $messages, ?array $tools = null, ?AiAgent $aiAgent = null): array
    {
        $config = config('services.byteplus_ark');
        $url = $config['base_url'].'/chat/completions';

        $llmMessages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($messages as $msg) {
            $llmMessages[] = [
                'role' => $this->mapRole($msg),
                'content' => $msg['content'],
            ];
        }

        $payload = [
            'model' => $config['model'],
            'messages' => $llmMessages,
            'temperature' => 0.7,
            'max_tokens' => 500,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if ($aiAgent && $aiAgent->enable_prompt_caching) {
            $payload['metadata'] = ['prompt_caching_enabled' => true];
        }

        $maxAttempts = 3;
        $backoff = [10, 30, 60];
        $startTime = microtime(true);

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            try {
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer '.$config['api_key'],
                    'Content-Type' => 'application/json',
                ])->timeout(30)->post($url, $payload);

                if ($response->successful()) {
                    return $this->parseResponse($response->json(), $aiAgent, (int) ((microtime(true) - $startTime) * 1000));
                }

                Log::warning('LLM API call failed', [
                    'status' => $response->status(),
                    'attempt' => $attempt + 1,
                ]);
            } catch (\Exception $e) {
                Log::error('LLM API exception', [
                    'attempt' => $attempt + 1,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($attempt + 1 < $maxAttempts) {
                sleep($backoff[$attempt]);
            }
        }

        throw new \Exception('LLM API failed after '.$maxAttempts.' attempts');
    }

    /**
     * Continuation call after tool execution. No retry — the first turn
     * already absorbed transient errors, and a stuck tool-result turn is
     * better surfaced than silently retried.
     */
    public function callWithToolResults(string $systemPrompt, array $messages, ?array $tools = null): array
    {
        $config = config('services.byteplus_ark');
        $url = $config['base_url'].'/chat/completions';

        $llmMessages = [['role' => 'system', 'content' => $systemPrompt]];
        foreach ($messages as $msg) {
            if (isset($msg['role']) && $msg['role'] === 'tool') {
                $llmMessages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $msg['tool_call_id'],
                    'content' => $msg['content'],
                ];
            } elseif (isset($msg['tool_calls'])) {
                $llmMessages[] = [
                    'role' => 'assistant',
                    'content' => $msg['content'],
                    'tool_calls' => $msg['tool_calls'],
                ];
            } else {
                $llmMessages[] = [
                    'role' => $this->mapRole($msg),
                    'content' => $msg['content'],
                ];
            }
        }

        $payload = [
            'model' => $config['model'],
            'messages' => $llmMessages,
            'temperature' => 0.7,
            'max_tokens' => 500,
        ];

        if ($tools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$config['api_key'],
            'Content-Type' => 'application/json',
        ])->timeout(30)->post($url, $payload);

        if (! $response->successful()) {
            throw new \Exception('LLM API call failed: '.$response->body());
        }

        $data = $response->json();
        $choice = $data['choices'][0] ?? null;

        if ($choice && isset($choice['message']['tool_calls'])) {
            return ['tool_calls' => $choice['message']['tool_calls']];
        }

        return ['content' => $choice['message']['content'] ?? ''];
    }

    /**
     * Translate the local message envelope (`{type: 'human'|'ai', ...}`) into
     * the OpenAI-style `{role: 'user'|'assistant'}` shape the API expects.
     */
    protected function mapRole(array $msg): string
    {
        return match ($msg['type'] ?? $msg['role'] ?? 'user') {
            'human' => 'user',
            'ai' => 'assistant',
            default => $msg['type'] ?? $msg['role'] ?? 'user',
        };
    }

    /**
     * Pull either the content string or tool_calls list out of the JSON body.
     * Tracks token usage as a side effect so the analytics page stays current
     * without each caller having to remember.
     */
    protected function parseResponse(array $data, ?AiAgent $aiAgent, int $responseTimeMs): array
    {
        $choice = $data['choices'][0] ?? null;
        if (! $choice) {
            Log::error('Invalid LLM response format: no choices', ['response_data' => $data]);
            throw new \Exception('Invalid LLM response format: no choices in response');
        }
        if (! isset($choice['message'])) {
            Log::error('Invalid LLM response format: no message', ['choice' => $choice]);
            throw new \Exception('Invalid LLM response format: no message in choice');
        }

        $this->trackAnalytics($aiAgent, $data, $responseTimeMs);

        if (isset($choice['message']['tool_calls'])) {
            Log::info('LLM returned tool calls', [
                'tool_calls_count' => count($choice['message']['tool_calls']),
                'tool_calls' => array_map(fn ($tc) => [
                    'id' => $tc['id'] ?? null,
                    'function' => $tc['function']['name'] ?? 'unknown',
                    'arguments' => $tc['function']['arguments'] ?? '{}',
                ], $choice['message']['tool_calls']),
            ]);

            return ['tool_calls' => $choice['message']['tool_calls']];
        }

        $content = $choice['message']['content'] ?? '';
        if (trim($content) === '') {
            Log::warning('LLM returned empty content', ['choice' => $choice]);
        }

        return ['content' => $content];
    }

    protected function trackAnalytics(?AiAgent $aiAgent, array $data, int $responseTimeMs): void
    {
        if (! $aiAgent) {
            return;
        }

        $promptType = $aiAgent->enable_prompt_caching
            ? 'cached'
            : ($aiAgent->use_optimized_prompt ? 'optimized' : 'full');

        AiPromptAnalytics::trackTokenUsage(
            $aiAgent->id,
            $data['usage']['total_tokens'] ?? 0,
            $promptType,
            (bool) $aiAgent->enable_prompt_caching,
            $responseTimeMs,
            $data['usage']['prompt_tokens'] ?? null,
            $data['usage']['completion_tokens'] ?? null,
        );
    }
}
