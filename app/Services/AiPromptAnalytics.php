<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AiPromptAnalytics
{
    /**
     * Track prompt token usage with detailed metrics
     */
    public static function trackTokenUsage(
        int $agentId,
        int $tokensUsed,
        string $promptType,
        bool $cacheHit = false,
        ?int $responseTimeMs = null,
        ?int $promptTokens = null,
        ?int $completionTokens = null
    ): void {
        try {
            DB::table('ai_prompt_analytics')->insert([
                'ai_agent_id' => $agentId,
                'tokens_used' => $tokensUsed,
                'prompt_type' => $promptType,
                'cache_hit' => $cacheHit,
                'response_time_ms' => $responseTimeMs,
                'prompt_tokens' => $promptTokens,
                'completion_tokens' => $completionTokens,
                'created_at' => now(),
            ]);

            Log::info('AI Prompt Analytics tracked', [
                'agent_id' => $agentId,
                'tokens_used' => $tokensUsed,
                'prompt_type' => $promptType,
                'cache_hit' => $cacheHit,
            ]);
        } catch (\Exception $e) {
            // Silently fail - analytics should not break the main flow
            Log::warning('Failed to track token usage', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get average token usage
     */
    public static function getAverageTokens(int $agentId, int $days = 7): float
    {
        try {
            return DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->where('created_at', '>=', now()->subDays($days))
                ->avg('tokens_used') ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache hit rate
     */
    public static function getCacheHitRate(int $agentId, int $days = 7): float
    {
        try {
            $total = DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->where('created_at', '>=', now()->subDays($days))
                ->count();

            if ($total === 0) {
                return 0;
            }

            $cacheHits = DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->where('created_at', '>=', now()->subDays($days))
                ->where('cache_hit', true)
                ->count();

            return round(($cacheHits / $total) * 100, 2);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get token savings from optimization
     */
    public static function getTokenSavings(int $agentId): array
    {
        try {
            $beforeOptimization = DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->where('prompt_type', 'full')
                ->avg('tokens_used') ?? 0;

            $afterOptimization = DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->whereIn('prompt_type', ['optimized', 'cached'])
                ->avg('tokens_used') ?? 0;

            $savings = $beforeOptimization - $afterOptimization;
            $savingsPercent = $beforeOptimization > 0
                ? ($savings / $beforeOptimization) * 100
                : 0;

            return [
                'before' => round($beforeOptimization),
                'after' => round($afterOptimization),
                'savings' => round($savings),
                'savings_percent' => round($savingsPercent, 2),
            ];
        } catch (\Exception $e) {
            return [
                'before' => 0,
                'after' => 0,
                'savings' => 0,
                'savings_percent' => 0,
            ];
        }
    }

    /**
     * Get analytics summary for an agent
     */
    public static function getSummary(int $agentId, int $days = 7): array
    {
        try {
            $stats = DB::table('ai_prompt_analytics')
                ->where('ai_agent_id', $agentId)
                ->where('created_at', '>=', now()->subDays($days))
                ->selectRaw('
                    COUNT(*) as total_requests,
                    SUM(tokens_used) as total_tokens,
                    AVG(tokens_used) as avg_tokens,
                    AVG(response_time_ms) as avg_response_time,
                    SUM(CASE WHEN cache_hit = 1 THEN 1 ELSE 0 END) as cache_hits
                ')
                ->first();

            $cacheHitRate = $stats->total_requests > 0
                ? round(($stats->cache_hits / $stats->total_requests) * 100, 2)
                : 0;

            return [
                'total_requests' => $stats->total_requests ?? 0,
                'total_tokens' => $stats->total_tokens ?? 0,
                'avg_tokens' => round($stats->avg_tokens ?? 0),
                'avg_response_time_ms' => round($stats->avg_response_time ?? 0),
                'cache_hit_rate' => $cacheHitRate,
                'period_days' => $days,
            ];
        } catch (\Exception $e) {
            Log::warning('Failed to get analytics summary', [
                'agent_id' => $agentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'total_requests' => 0,
                'total_tokens' => 0,
                'avg_tokens' => 0,
                'avg_response_time_ms' => 0,
                'cache_hit_rate' => 0,
                'period_days' => $days,
            ];
        }
    }
}
