<?php

namespace App\Helpers;

use Sbsaga\Toon\Facades\Toon;

/**
 * ToonFormatter - Wrapper untuk sbsaga/toon package
 *
 * Token-Optimized Object Notation untuk komunikasi LLM yang hemat token.
 * Menggunakan sbsaga/toon package yang memberikan ~67% pengurangan ukuran.
 *
 * @see https://github.com/sbsaga/toon
 */
class ToonFormatter
{
    /**
     * Encode data to TOON format menggunakan sbsaga/toon.
     *
     * @param  mixed  $data  The data to encode (array or object)
     * @return string TOON-formatted string
     */
    public static function encode(mixed $data): string
    {
        if (is_null($data)) {
            return 'null';
        }

        if (is_scalar($data)) {
            return (string) $data;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        return Toon::convert($data);
    }

    /**
     * Decode TOON format back to PHP array.
     *
     * @param  string  $toon  TOON-formatted string
     * @return array Decoded data
     */
    public static function decode(string $toon): array
    {
        return Toon::decode($toon);
    }

    /**
     * Estimate token count for given content.
     *
     * @param  string  $content  Content to estimate
     * @return array Token statistics
     */
    public static function estimateTokens(string $content): array
    {
        return Toon::estimateTokens($content);
    }

    /**
     * Encode product list specifically optimized for AI Agent.
     *
     * @param  array  $products  Array of products with id, name, price, stock
     * @return string TOON-formatted product list
     */
    public static function encodeProducts(array $products): string
    {
        if (empty($products)) {
            return 'products[0]:';
        }

        // Normalize product data
        $normalized = array_map(function ($product) {
            return [
                'id' => $product['id'] ?? $product['product_id'] ?? 0,
                'name' => $product['name'] ?? $product['product_name'] ?? '',
                'price' => $product['price'] ?? 0,
                'stock' => $product['stock'] ?? $product['stock_quantity'] ?? 0,
            ];
        }, $products);

        return Toon::convert(['products' => $normalized]);
    }

    /**
     * Encode cart items for AI Agent.
     *
     * @param  array  $cart  Array of cart items
     * @return string TOON-formatted cart
     */
    public static function encodeCart(array $cart): string
    {
        if (empty($cart)) {
            return 'cart[0]:';
        }

        // Normalize cart data
        $normalized = array_map(function ($item) {
            $price = $item['price'] ?? 0;
            $qty = $item['quantity'] ?? 1;

            return [
                'id' => $item['product_id'] ?? 0,
                'name' => $item['product_name'] ?? '',
                'price' => $price,
                'qty' => $qty,
                'subtotal' => $price * $qty,
            ];
        }, $cart);

        return Toon::convert(['cart' => $normalized]);
    }

    /**
     * Encode conversation summary in TOON format.
     *
     * @param  array  $summary  Summary data
     * @return string TOON-formatted summary
     */
    public static function encodeSummary(array $summary): string
    {
        $data = [
            'intent' => $summary['intent'] ?? 'unknown',
            'summary' => $summary['summary'] ?? '',
        ];

        if (! empty($summary['key_data'])) {
            $data['key_data'] = $summary['key_data'];
        }

        if (! empty($summary['missing_information'])) {
            $data['missing'] = $summary['missing_information'];
        }

        return Toon::convert($data);
    }

    /**
     * Encode order data for AI Agent.
     *
     * @param  array  $order  Order data
     * @return string TOON-formatted order
     */
    public static function encodeOrder(array $order): string
    {
        return Toon::convert(['order' => $order]);
    }

    /**
     * Helper to wrap TOON content in code block for LLM prompts.
     *
     * @param  string  $toon  TOON-formatted content
     * @return string Content wrapped in ```toon code block
     */
    public static function wrapInCodeBlock(string $toon): string
    {
        return "```toon\n{$toon}\n```";
    }

    /**
     * Compare JSON vs TOON size for analytics.
     *
     * @param  array  $data  Data to compare
     * @return array Comparison statistics
     */
    public static function compareSizes(array $data): array
    {
        $jsonSize = strlen(json_encode($data, JSON_PRETTY_PRINT));
        $toonContent = Toon::convert($data);
        $toonSize = strlen($toonContent);

        return [
            'json_size_bytes' => $jsonSize,
            'toon_size_bytes' => $toonSize,
            'saving_percent' => round(100 - ($toonSize / $jsonSize * 100), 2),
            'tokens_estimate' => Toon::estimateTokens($toonContent),
        ];
    }
}
