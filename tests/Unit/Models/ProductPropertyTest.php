<?php

namespace Tests\Unit\Models;

use App\Models\Product;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Product model
 *
 * Feature: point-of-sale
 */
class ProductPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 1: Product Serialization Round-Trip
     * Validates: Requirements 1.6, 1.7
     *
     * For any valid Product object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Product object with identical field values.
     */
    #[Test]
    public function product_serialization_round_trip_preserves_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::pos(),
                Generators::string(),
                Generators::suchThat(
                    fn ($s) => strlen($s) > 0 && strlen($s) <= 50,
                    Generators::string()
                ),
                Generators::string(),
                Generators::choose(100, 1000000),
                Generators::choose(0, 10000),
                Generators::bool()
            )
            ->then(function (int $categoryId, string $name, string $sku, string $description, int $priceInCents, int $stockQuantity, bool $isActive) {
                // Convert cents to decimal price
                $price = $priceInCents / 100;

                // Create a Product instance with generated data
                $product = new Product([
                    'category_id' => $categoryId,
                    'name' => $name,
                    'sku' => $sku,
                    'description' => $description,
                    'price' => $price,
                    'stock_quantity' => $stockQuantity,
                    'is_active' => $isActive,
                ]);

                // Serialize to JSON
                $json = $product->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Product from decoded data
                $restoredProduct = new Product($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($product->category_id, $restoredProduct->category_id, 'category_id should be preserved');
                $this->assertEquals($product->name, $restoredProduct->name, 'Name should be preserved');
                $this->assertEquals($product->sku, $restoredProduct->sku, 'SKU should be preserved');
                $this->assertEquals($product->description, $restoredProduct->description, 'Description should be preserved');
                $this->assertEquals($product->price, $restoredProduct->price, 'Price should be preserved');
                $this->assertEquals($product->stock_quantity, $restoredProduct->stock_quantity, 'stock_quantity should be preserved');
                $this->assertEquals($product->is_active, $restoredProduct->is_active, 'is_active should be preserved');
            });
    }
}
