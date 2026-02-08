<?php

namespace Tests\Unit;

use App\Helpers\ToonFormatter;
use PHPUnit\Framework\TestCase;

class ToonFormatterTest extends TestCase
{
    /**
     * Test encoding products in TOON tabular format.
     */
    public function test_encode_products_tabular_format(): void
    {
        $products = [
            ['id' => 1, 'name' => 'Dimsum Keju', 'price' => 40000, 'stock' => 10],
            ['id' => 2, 'name' => 'Teh Jumbo', 'price' => 5000, 'stock' => 50],
        ];

        $toon = ToonFormatter::encodeProducts($products);

        $this->assertStringContainsString('products[2]{id,name,price,stock}:', $toon);
        $this->assertStringContainsString('1,Dimsum Keju,40000,10', $toon);
        $this->assertStringContainsString('2,Teh Jumbo,5000,50', $toon);
    }

    /**
     * Test encoding empty products array.
     */
    public function test_encode_empty_products(): void
    {
        $toon = ToonFormatter::encodeProducts([]);

        $this->assertEquals('products[0]:', $toon);
    }

    /**
     * Test encoding cart items.
     */
    public function test_encode_cart(): void
    {
        $cart = [
            ['product_id' => 1, 'product_name' => 'Dimsum Keju', 'price' => 40000, 'quantity' => 2],
            ['product_id' => 2, 'product_name' => 'Teh Jumbo', 'price' => 5000, 'quantity' => 1],
        ];

        $toon = ToonFormatter::encodeCart($cart);

        $this->assertStringContainsString('cart[2]{id,name,price,qty,subtotal}:', $toon);
        $this->assertStringContainsString('1,Dimsum Keju,40000,2,80000', $toon);
        $this->assertStringContainsString('2,Teh Jumbo,5000,1,5000', $toon);
    }

    /**
     * Test encoding conversation summary.
     */
    public function test_encode_summary(): void
    {
        $summary = [
            'intent' => 'order_food',
            'summary' => 'User ingin memesan dimsum',
            'key_data' => [
                'products' => ['dimsum', 'teh'],
            ],
            'missing_information' => ['jumlah pesanan'],
        ];

        $toon = ToonFormatter::encodeSummary($summary);

        $this->assertStringContainsString('intent: order_food', $toon);
        $this->assertStringContainsString('summary: User ingin memesan dimsum', $toon);
        $this->assertStringContainsString('products[2]: dimsum,teh', $toon);
        $this->assertStringContainsString('missing[1]: jumlah pesanan', $toon);
    }

    /**
     * Test TOON format is more token-efficient than JSON.
     */
    public function test_toon_is_more_compact_than_json(): void
    {
        $products = [
            ['id' => 1, 'name' => 'Dimsum Keju', 'price' => 40000, 'stock' => 10],
            ['id' => 2, 'name' => 'Teh Jumbo', 'price' => 5000, 'stock' => 50],
            ['id' => 3, 'name' => 'Nasi Goreng Spesial', 'price' => 35000, 'stock' => 25],
        ];

        $toon = ToonFormatter::encodeProducts($products);
        $json = json_encode(['products' => $products]);

        // TOON should be significantly shorter
        $this->assertLessThan(strlen($json), strlen($toon));

        // Calculate savings
        $savings = (1 - (strlen($toon) / strlen($json))) * 100;

        // Expect at least 30% savings
        $this->assertGreaterThan(30, $savings, "TOON savings: {$savings}%");
    }

    /**
     * Test encoding product with comma in name (escaping).
     */
    public function test_encode_product_with_comma(): void
    {
        $products = [
            ['id' => 1, 'name' => 'Dimsum, Keju Special', 'price' => 45000, 'stock' => 10],
        ];

        $toon = ToonFormatter::encodeProducts($products);

        $this->assertStringContainsString('"Dimsum, Keju Special"', $toon);
    }

    /**
     * Test wrapping TOON in code block.
     */
    public function test_wrap_in_code_block(): void
    {
        $toon = 'products[1]{id,name}: 1,Test';
        $wrapped = ToonFormatter::wrapInCodeBlock($toon);

        $this->assertStringStartsWith('```toon', $wrapped);
        $this->assertStringEndsWith('```', $wrapped);
        $this->assertStringContainsString($toon, $wrapped);
    }
}
