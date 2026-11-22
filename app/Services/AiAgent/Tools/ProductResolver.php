<?php

namespace App\Services\AiAgent\Tools;

use App\Models\Product;

/**
 * Turns a customer-supplied product name into one of three outcomes:
 * matched, ambiguous, or not_found. Keeps the LLM from blindly adding the
 * wrong item when the customer wording is loose.
 *
 * Resolution priority:
 *   1. Exact match (case-insensitive)
 *   2. Single LIKE %name% candidate → use it
 *   3. Multiple LIKE candidates → check similarity to the query:
 *        a. One candidate scores ≥ AMBIGUITY_LEAD pts above the rest → use it
 *        b. Otherwise return "ambiguous" with top candidates
 *   4. No LIKE candidates → return "not_found" with top alternatives by similarity
 */
class ProductResolver
{
    public const AMBIGUITY_LEAD = 15.0;

    public const ALTERNATIVE_MIN_SCORE = 30.0;

    /**
     * Resolve a product name. Returns one of:
     *   ['status' => 'matched',    'product' => Product]
     *   ['status' => 'ambiguous',  'candidates' => Product[]]   // ≤ 3
     *   ['status' => 'not_found',  'alternatives' => Product[]] // ≤ 3
     */
    public function resolve(int $userId, string $rawName): array
    {
        $query = trim(strtolower($rawName));
        if ($query === '') {
            return ['status' => 'not_found', 'alternatives' => []];
        }

        $base = Product::where('user_id', $userId)
            ->where('is_active', true);

        $exact = (clone $base)
            ->whereRaw('LOWER(name) = ?', [$query])
            ->first(['id', 'name', 'price', 'stock_quantity']);

        if ($exact) {
            return ['status' => 'matched', 'product' => $exact];
        }

        $likeMatches = (clone $base)
            ->whereRaw('LOWER(name) LIKE ?', ['%'.$query.'%'])
            ->limit(8)
            ->get(['id', 'name', 'price', 'stock_quantity']);

        if ($likeMatches->count() === 1) {
            return ['status' => 'matched', 'product' => $likeMatches->first()];
        }

        if ($likeMatches->count() > 1) {
            $ranked = $this->rank($likeMatches->all(), $query);
            $lead = $ranked[0]['score'] - ($ranked[1]['score'] ?? 0);
            if ($lead >= self::AMBIGUITY_LEAD) {
                return ['status' => 'matched', 'product' => $ranked[0]['product']];
            }

            return [
                'status' => 'ambiguous',
                'candidates' => array_map(fn ($e) => $e['product'], array_slice($ranked, 0, 3)),
            ];
        }

        // No LIKE candidates — pull a small sample and rank by similarity so
        // the customer gets useful alternatives instead of a hard "not found".
        $sample = (clone $base)
            ->limit(50)
            ->get(['id', 'name', 'price', 'stock_quantity']);

        $ranked = $this->rank($sample->all(), $query);

        return [
            'status' => 'not_found',
            'alternatives' => array_map(
                fn ($e) => $e['product'],
                array_slice(array_filter($ranked, fn ($e) => $e['score'] >= self::ALTERNATIVE_MIN_SCORE), 0, 3)
            ),
        ];
    }

    /**
     * Sort products by descending similar_text percent against $query.
     * Each entry: ['product' => Product, 'score' => float].
     */
    public function rank(array $products, string $query): array
    {
        $scored = [];
        foreach ($products as $product) {
            similar_text(strtolower($product->name), $query, $percent);
            $scored[] = ['product' => $product, 'score' => $percent];
        }
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    /**
     * Render a comma-separated list of product names for an error message.
     * Returns an empty string when the list is empty.
     */
    public function formatSuggestions(array $products): string
    {
        $names = array_map(fn ($p) => "'{$p->name}'", $products);

        return implode(', ', $names);
    }
}
