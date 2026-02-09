<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for ProductService
 *
 * Feature: point-of-sale
 */
class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $service;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProductService;
        $this->user = User::factory()->create();
    }

    #[Test]
    public function it_creates_a_product_with_auto_generated_sku(): void
    {
        $category = Category::create([
            'name' => 'Beverages',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'user_id' => $this->user->id,
            'name' => 'Coffee Latte',
            'category_id' => $category->id,
            'price' => 25000,
            'stock_quantity' => 100,
            'description' => 'Delicious coffee latte',
        ]);

        $this->assertNotNull($product->id);
        $this->assertNotNull($product->sku);
        $this->assertEquals('Coffee Latte', $product->name);
        $this->assertEquals(25000, $product->price);
        $this->assertEquals(100, $product->stock_quantity);
        $this->assertTrue($product->is_active);
    }

    #[Test]
    public function it_creates_a_product_with_custom_sku(): void
    {
        $category = Category::create([
            'name' => 'Food',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'user_id' => $this->user->id,
            'name' => 'Burger',
            'category_id' => $category->id,
            'price' => 35000,
            'stock_quantity' => 50,
            'sku' => 'CUSTOM-SKU-001',
        ]);

        $this->assertEquals('CUSTOM-SKU-001', $product->sku);
    }

    #[Test]
    public function it_updates_a_product(): void
    {
        $category = Category::create([
            'name' => 'Snacks',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'name' => 'Chips',
            'category_id' => $category->id,
            'price' => 15000,
            'stock_quantity' => 200,
        ]);

        $updatedProduct = $this->service->update($product, [
            'name' => 'Premium Chips',
            'price' => 20000,
        ]);

        $this->assertEquals('Premium Chips', $updatedProduct->name);
        $this->assertEquals(20000, $updatedProduct->price);
        $this->assertEquals(200, $updatedProduct->stock_quantity);
    }

    #[Test]
    public function it_soft_deletes_a_product(): void
    {
        $category = Category::create([
            'name' => 'Desserts',
            'is_active' => true,
        ]);

        $product = $this->service->create([
            'name' => 'Ice Cream',
            'category_id' => $category->id,
            'price' => 18000,
            'stock_quantity' => 30,
        ]);

        $result = $this->service->delete($product);

        $this->assertTrue($result);
        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    #[Test]
    public function it_searches_products_by_name(): void
    {
        $category = Category::create([
            'name' => 'Drinks',
            'is_active' => true,
        ]);

        $this->service->create([
            'name' => 'Orange Juice',
            'category_id' => $category->id,
            'price' => 12000,
            'stock_quantity' => 50,
        ]);

        $this->service->create([
            'name' => 'Apple Juice',
            'category_id' => $category->id,
            'price' => 12000,
            'stock_quantity' => 50,
        ]);

        $this->service->create([
            'name' => 'Coffee',
            'category_id' => $category->id,
            'price' => 15000,
            'stock_quantity' => 50,
        ]);

        $results = $this->service->search('Juice', $this->user->id);

        $this->assertCount(2, $results);
    }

    #[Test]
    public function it_searches_products_by_sku(): void
    {
        $category = Category::create([
            'name' => 'Electronics',
            'is_active' => true,
        ]);

        $this->service->create([
            'user_id' => $this->user->id,
            'name' => 'Phone Charger',
            'category_id' => $category->id,
            'price' => 50000,
            'stock_quantity' => 20,
            'sku' => 'ELEC-CHG-001',
        ]);

        $this->service->create([
            'user_id' => $this->user->id,
            'name' => 'USB Cable',
            'category_id' => $category->id,
            'price' => 25000,
            'stock_quantity' => 100,
            'sku' => 'ELEC-USB-002',
        ]);

        $results = $this->service->search('ELEC-CHG', $this->user->id);

        $this->assertCount(1, $results);
        $this->assertEquals('Phone Charger', $results->first()->name);
    }

    #[Test]
    public function it_generates_unique_sku(): void
    {
        $category = Category::create([
            'name' => 'Test Category',
            'is_active' => true,
        ]);

        $sku1 = $this->service->generateSku('Test Product', $category->id);

        // Create a product with this SKU
        Product::create([
            'user_id' => $this->user->id,
            'name' => 'Test Product',
            'category_id' => $category->id,
            'price' => 10000,
            'stock_quantity' => 10,
            'sku' => $sku1,
            'is_active' => true,
        ]);

        $sku2 = $this->service->generateSku('Test Product', $category->id);

        $this->assertNotEquals($sku1, $sku2);
    }

    #[Test]
    public function it_generates_sku_with_category_prefix(): void
    {
        $category = Category::create([
            'name' => 'Beverages',
            'is_active' => true,
        ]);

        $sku = $this->service->generateSku('Coffee', $category->id);

        // SKU should contain category prefix (BEV)
        $this->assertStringContainsString('BEV', $sku);
    }

    #[Test]
    public function it_generates_sku_without_category(): void
    {
        $sku = $this->service->generateSku('Standalone Product', null);

        // SKU should still be generated without category prefix
        $this->assertNotEmpty($sku);
        $this->assertStringContainsString('STA', $sku);
    }

    #[Test]
    public function search_excludes_inactive_products(): void
    {
        $category = Category::create([
            'name' => 'Mixed',
            'is_active' => true,
        ]);

        $this->service->create([
            'user_id' => $this->user->id,
            'name' => 'Active Product',
            'category_id' => $category->id,
            'price' => 10000,
            'stock_quantity' => 10,
            'is_active' => true,
        ]);

        Product::create([
            'user_id' => $this->user->id,
            'name' => 'Inactive Product',
            'category_id' => $category->id,
            'price' => 10000,
            'stock_quantity' => 10,
            'sku' => 'INACTIVE-001',
            'is_active' => false,
        ]);

        $results = $this->service->search('Product', $this->user->id);

        $this->assertCount(1, $results);
        $this->assertEquals('Active Product', $results->first()->name);
    }
}
