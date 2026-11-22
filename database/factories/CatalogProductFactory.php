<?php

namespace Database\Factories;

use App\Models\CatalogProduct;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CatalogProduct>
 */
class CatalogProductFactory extends Factory
{
    protected $model = CatalogProduct::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'catalog_id' => (string) fake()->numerify('################'),
            'meta_product_id' => (string) fake()->numerify('################'),
            'retailer_id' => 'SKU-'.strtoupper(fake()->bothify('????-####')),
            'name' => fake()->randomElement(['Nasi Goreng', 'Ayam Bakar Madu', 'Iga Bakar', 'Es Teh', 'Tuna Bakar']),
            'price' => fake()->numberBetween(15000, 100000),
            'currency' => 'IDR',
            'stock_quantity' => fake()->numberBetween(0, 100),
            'is_available' => true,
            'availability' => 'in stock',
            'category' => fake()->randomElement(['Makanan', 'Minuman']),
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
            'availability' => 'out of stock',
        ]);
    }
}
