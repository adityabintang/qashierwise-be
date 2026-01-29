<?php

namespace Tests\Unit\Models;

use App\Models\Category;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Category model
 *
 * Feature: point-of-sale
 */
class CategoryPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 2: Category Serialization Round-Trip
     * Validates: Requirements 2.5, 2.6
     *
     * For any valid Category object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Category object with identical field values.
     */
    #[Test]
    public function category_serialization_round_trip_preserves_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),
                Generators::string(),
                Generators::string(),
                Generators::bool()
            )
            ->then(function (string $name, string $slug, string $description, bool $isActive) {
                // Create a Category instance with generated data
                $category = new Category([
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $description,
                    'is_active' => $isActive,
                ]);

                // Serialize to JSON
                $json = $category->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Category from decoded data
                $restoredCategory = new Category($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($category->name, $restoredCategory->name, 'Name should be preserved');
                $this->assertEquals($category->slug, $restoredCategory->slug, 'Slug should be preserved');
                $this->assertEquals($category->description, $restoredCategory->description, 'Description should be preserved');
                $this->assertEquals($category->is_active, $restoredCategory->is_active, 'is_active should be preserved');
            });
    }
}
