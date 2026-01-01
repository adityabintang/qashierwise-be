<?php

namespace App\Helpers;

/**
 * ToonFormatter - Token-Oriented Object Notation Encoder
 *
 * Encodes PHP arrays to TOON format for token-efficient LLM communication.
 * TOON uses ~40% fewer tokens than JSON while maintaining/improving accuracy.
 *
 * @see https://github.com/toon-format/toon
 */
class ToonFormatter
{
    /**
     * Encode data to TOON format.
     *
     * @param  mixed  $data  The data to encode (array or object)
     * @param  int  $indent  Current indentation level
     * @return string TOON-formatted string
     */
    public static function encode(mixed $data, int $indent = 0): string
    {
        if (is_null($data)) {
            return 'null';
        }

        if (is_bool($data)) {
            return $data ? 'true' : 'false';
        }

        if (is_numeric($data)) {
            return (string) $data;
        }

        if (is_string($data)) {
            return self::encodeString($data);
        }

        if (is_array($data)) {
            return self::encodeArray($data, $indent);
        }

        if (is_object($data)) {
            return self::encodeArray((array) $data, $indent);
        }

        return (string) $data;
    }

    /**
     * Encode a string value, escaping if necessary.
     */
    protected static function encodeString(string $value): string
    {
        // Check if string needs quoting (contains special chars)
        if (preg_match('/[\n\r,:\[\]{}]/', $value) || trim($value) !== $value) {
            return '"'.addslashes($value).'"';
        }

        return $value;
    }

    /**
     * Encode an array to TOON format.
     */
    protected static function encodeArray(array $data, int $indent = 0): string
    {
        if (empty($data)) {
            return '';
        }

        $prefix = str_repeat('  ', $indent);

        // Check if it's a simple indexed array (list)
        if (self::isIndexedArray($data)) {
            return self::encodeList($data, $indent);
        }

        // Associative array (object)
        return self::encodeObject($data, $indent);
    }

    /**
     * Check if array is purely indexed (list-like).
     */
    protected static function isIndexedArray(array $data): bool
    {
        if (empty($data)) {
            return true;
        }

        return array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * Check if all items in array are objects with same keys (tabular).
     */
    protected static function isTabularArray(array $data): bool
    {
        if (count($data) < 1) {
            return false;
        }

        $firstItem = $data[0];
        if (! is_array($firstItem) || self::isIndexedArray($firstItem)) {
            return false;
        }

        $keys = array_keys($firstItem);

        foreach ($data as $item) {
            if (! is_array($item) || self::isIndexedArray($item)) {
                return false;
            }
            if (array_keys($item) !== $keys) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encode a simple list (indexed array).
     */
    protected static function encodeList(array $data, int $indent = 0): string
    {
        $count = count($data);

        // Check if it's a tabular array (uniform objects)
        if (self::isTabularArray($data)) {
            return self::encodeTabular($data, $indent);
        }

        // Check if all items are simple scalars
        $allScalar = true;
        foreach ($data as $item) {
            if (is_array($item) || is_object($item)) {
                $allScalar = false;
                break;
            }
        }

        if ($allScalar) {
            // Simple list: [N]: val1,val2,val3
            $values = array_map(fn ($v) => self::encodeString((string) $v), $data);

            return "[{$count}]: ".implode(',', $values);
        }

        // Mixed list - encode each item on separate line
        $lines = [];
        $lines[] = "[{$count}]:";
        foreach ($data as $item) {
            $encoded = self::encode($item, $indent + 1);
            $lines[] = str_repeat('  ', $indent + 1).$encoded;
        }

        return implode("\n", $lines);
    }

    /**
     * Encode tabular array (uniform objects) - TOON's most efficient format.
     * Format: key[N]{field1,field2,...}:
     *           val1,val2,...
     *           val1,val2,...
     */
    protected static function encodeTabular(array $data, int $indent = 0): string
    {
        if (empty($data)) {
            return '';
        }

        $count = count($data);
        $fields = array_keys($data[0]);
        $prefix = str_repeat('  ', $indent);

        $header = "[{$count}]{".implode(',', $fields).'}:';
        $lines = [$header];

        foreach ($data as $row) {
            $values = [];
            foreach ($fields as $field) {
                $val = $row[$field] ?? null;
                $values[] = self::encodeValue($val);
            }
            $lines[] = $prefix.'  '.implode(',', $values);
        }

        return implode("\n", $lines);
    }

    /**
     * Encode a simple value for tabular format.
     */
    protected static function encodeValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // Escape commas and special chars in tabular values
            if (str_contains($value, ',') || str_contains($value, "\n")) {
                return '"'.addslashes($value).'"';
            }

            return $value;
        }

        if (is_array($value) || is_object($value)) {
            // Nested structure - fall back to compact representation
            return json_encode($value);
        }

        return (string) $value;
    }

    /**
     * Encode an associative array (object).
     */
    protected static function encodeObject(array $data, int $indent = 0): string
    {
        $lines = [];
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value) && ! empty($value)) {
                if (self::isTabularArray($value)) {
                    // Tabular array with key prefix
                    $tabular = self::encodeTabular($value, $indent);
                    $lines[] = $prefix.$key.$tabular;
                } elseif (self::isIndexedArray($value)) {
                    // Simple list
                    $list = self::encodeList($value, $indent);
                    $lines[] = $prefix.$key.$list;
                } else {
                    // Nested object
                    $lines[] = $prefix.$key.':';
                    $nested = self::encodeObject($value, $indent + 1);
                    $lines[] = $nested;
                }
            } else {
                $encoded = self::encodeValue($value);
                $lines[] = $prefix.$key.': '.$encoded;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Encode product list specifically optimized for AI Agent.
     * This is the most token-efficient format for product data.
     *
     * @param  array  $products  Array of products with id, name, price, stock
     * @return string TOON-formatted product list
     */
    public static function encodeProducts(array $products): string
    {
        if (empty($products)) {
            return 'products[0]:';
        }

        $count = count($products);
        $lines = ["products[{$count}]{id,name,price,stock}:"];

        foreach ($products as $product) {
            $id = $product['id'] ?? $product['product_id'] ?? 0;
            $name = $product['name'] ?? $product['product_name'] ?? '';
            $price = $product['price'] ?? 0;
            $stock = $product['stock'] ?? $product['stock_quantity'] ?? 0;

            // Escape name if contains comma
            if (str_contains($name, ',')) {
                $name = '"'.$name.'"';
            }

            $lines[] = "  {$id},{$name},{$price},{$stock}";
        }

        return implode("\n", $lines);
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

        $count = count($cart);
        $lines = ["cart[{$count}]{id,name,price,qty,subtotal}:"];

        foreach ($cart as $item) {
            $id = $item['product_id'] ?? 0;
            $name = $item['product_name'] ?? '';
            $price = $item['price'] ?? 0;
            $qty = $item['quantity'] ?? 1;
            $subtotal = $price * $qty;

            if (str_contains($name, ',')) {
                $name = '"'.$name.'"';
            }

            $lines[] = "  {$id},{$name},{$price},{$qty},{$subtotal}";
        }

        return implode("\n", $lines);
    }

    /**
     * Encode conversation summary in TOON format.
     *
     * @param  array  $summary  Summary data
     * @return string TOON-formatted summary
     */
    public static function encodeSummary(array $summary): string
    {
        $lines = [];

        $lines[] = 'intent: '.($summary['intent'] ?? 'unknown');
        $lines[] = 'summary: '.($summary['summary'] ?? '');

        if (! empty($summary['key_data'])) {
            $lines[] = 'key_data:';
            $keyData = $summary['key_data'];

            if (! empty($keyData['products'])) {
                $products = $keyData['products'];
                $lines[] = '  products['.count($products).']: '.implode(',', $products);
            }

            if (! empty($keyData['order_items'])) {
                $items = $keyData['order_items'];
                $lines[] = '  order_items['.count($items).']: '.implode(',', array_map(fn ($i) => is_array($i) ? json_encode($i) : $i, $items));
            }

            foreach (['reservation_date', 'reservation_time', 'people_count', 'total_estimate'] as $field) {
                if (! empty($keyData[$field])) {
                    $lines[] = "  {$field}: ".$keyData[$field];
                }
            }
        }

        if (! empty($summary['missing_information'])) {
            $missing = $summary['missing_information'];
            $lines[] = 'missing['.count($missing).']: '.implode(',', $missing);
        }

        return implode("\n", $lines);
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
}
