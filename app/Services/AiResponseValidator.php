<?php

namespace App\Services;

class AiResponseValidator
{
    private array $errors = [];

    private array $warnings = [];

    /**
     * Validate AI response
     */
    public function validate(string $response, array $toolResults = []): bool
    {
        $this->errors = [];
        $this->warnings = [];

        $this->checkProductIdExposure($response);
        $this->checkManualCalculation($response);
        $this->checkHallucinatedProducts($response, $toolResults);
        $this->checkOffTopicResponse($response);

        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Check if response exposes product IDs
     */
    private function checkProductIdExposure(string $response): void
    {
        if (preg_match('/\[ID:\d+\]/', $response)) {
            $this->errors[] = 'Product ID exposed to user';
        }
    }

    /**
     * Check if response contains manual price calculations
     */
    private function checkManualCalculation(string $response): void
    {
        // Detect patterns like: 40.000 x 2 = 80.000
        if (preg_match('/[\d.,]+\s*[x×]\s*\d+\s*=\s*[\d.,]+/', $response)) {
            $this->errors[] = 'Manual calculation detected';
        }

        // Detect "total" calculations without tool result
        if (preg_match('/total\s*[:\-]?\s*rp\s*[\d.,]+/i', $response)) {
            $this->warnings[] = 'Total amount mentioned - verify from tool result';
        }
    }

    /**
     * Check if response mentions products not in tool results
     */
    private function checkHallucinatedProducts(string $response, array $toolResults): void
    {
        if (empty($toolResults)) {
            return;
        }

        $knownProducts = $this->extractKnownProducts($toolResults);
        $mentionedProducts = $this->extractMentionedProducts($response);

        $hallucinated = array_diff($mentionedProducts, $knownProducts);

        if (! empty($hallucinated)) {
            $this->errors[] = 'Possible hallucinated products: '.implode(', ', $hallucinated);
        }
    }

    /**
     * Check if response is off-topic
     */
    private function checkOffTopicResponse(string $response): void
    {
        $offTopicIndicators = [
            'chatgpt', 'claude', 'AI model', 'language model',
            'ibu kota', 'presiden', 'sejarah',
            'politik', 'pemilu',
        ];

        foreach ($offTopicIndicators as $indicator) {
            if (stripos($response, $indicator) !== false) {
                $this->warnings[] = 'Possible off-topic response detected';
                break;
            }
        }
    }

    /**
     * Extract known product names from tool results
     */
    private function extractKnownProducts(array $toolResults): array
    {
        $products = [];

        foreach ($toolResults as $result) {
            if (isset($result['products']) && is_array($result['products'])) {
                foreach ($result['products'] as $product) {
                    if (isset($product['name'])) {
                        $products[] = strtolower(trim($product['name']));
                    }
                }
            }

            if (isset($result['product']['name'])) {
                $products[] = strtolower(trim($result['product']['name']));
            }
        }

        return array_unique($products);
    }

    /**
     * Extract product names mentioned in response
     */
    private function extractMentionedProducts(string $response): array
    {
        $products = [];

        // Pattern: Product name followed by price
        // Example: "Dimsum Keju - Rp 40.000"
        preg_match_all('/([A-Z][a-z\s]+(?:[A-Z][a-z\s]+)*)\s*[-–]\s*Rp\s*[\d.,]+/u', $response, $matches);

        if (! empty($matches[1])) {
            foreach ($matches[1] as $match) {
                $products[] = strtolower(trim($match));
            }
        }

        return array_unique($products);
    }

    /**
     * Sanitize response by removing product IDs
     */
    public function sanitize(string $response): string
    {
        // Remove [ID:123] patterns
        $response = preg_replace('/\[ID:\d+\]/', '', $response);

        return $response;
    }
}
