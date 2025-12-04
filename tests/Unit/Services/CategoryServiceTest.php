<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests for CategoryService
 * 
 * Feature: point-of-sale
 */
class CategoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CategoryService();
    }

    #[Test]
    public function it_creates_a_category_with_auto_generated_slug(): void
    {
        $category = $this->service->create([
            'name' => 'Beverages',
            'description' => 'All kinds of drinks',
        ]);

        $this->assertNotNull($category->id);
        $this->assertEquals('Beverages', $category->name);
        $this->assertEquals('beverages', $category->slug);
        $this->assertTrue($category->is_active);
    }

    #[Test]
    public function it_creates_a_category_with_custom_slug(): void
    {
        $category = $this->service->create([
            'name' => 'Hot Drinks',
            'slug' => 'custom-hot-drinks',
        ]);

        $this->assertEquals('custom-hot-drinks', $category->slug);
    }

    #[Test]
    public function it_generates_unique_slug_for_duplicate_names(): void
    {
        $category1 = $this->service->create(['name' => 'Food']);
        $category2 = $this->service->create(['name' => 'Food']);

        $this->assertEquals('food', $category1->slug);
        $this->assertEquals('food-1', $category2->slug);
    }

    #[Test]
    public function it_generates_slug_with_special_characters(): void
    {
        $category = $this->service->create([
            'name' => 'Hot & Cold Drinks!',
        ]);

        $this->assertEquals('hot-cold-drinks', $category->slug);
    }

    #[Test]
    public function it_prevents_deletion_of_category_with_products(): void
    {
        $category = $this->service->create(['name' => 'Electronics']);

        Product::create([
            'name' => 'Phone Charger',
            'category_id' => $category->id,
            'price' => 50000,
            'stock_quantity' => 20,
            'sku' => 'ELEC-CHG-001',
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot delete category with products');

        $this->service->delete($category);
    }

    #[Test]
    public function it_allows_deletion_of_empty_category(): void
    {
        $category = $this->service->create(['name' => 'Empty Category']);

        $result = $this->service->delete($category);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    #[Test]
    public function it_updates_category_and_regenerates_slug(): void
    {
        $category = $this->service->create(['name' => 'Old Name']);

        $updated = $this->service->update($category, ['name' => 'New Name']);

        $this->assertEquals('New Name', $updated->name);
        $this->assertEquals('new-name', $updated->slug);
    }

    #[Test]
    public function it_gets_all_categories_with_product_counts(): void
    {
        $category1 = $this->service->create(['name' => 'Category 1']);
        $category2 = $this->service->create(['name' => 'Category 2']);

        Product::create([
            'name' => 'Product 1',
            'category_id' => $category1->id,
            'price' => 10000,
            'stock_quantity' => 10,
            'sku' => 'PROD-001',
            'is_active' => true,
        ]);

        Product::create([
            'name' => 'Product 2',
            'category_id' => $category1->id,
            'price' => 20000,
            'stock_quantity' => 20,
            'sku' => 'PROD-002',
            'is_active' => true,
        ]);

        $categories = $this->service->getAllWithProductCounts();

        $cat1 = $categories->firstWhere('id', $category1->id);
        $cat2 = $categories->firstWhere('id', $category2->id);

        $this->assertEquals(2, $cat1->products_count);
        $this->assertEquals(0, $cat2->products_count);
    }
}
