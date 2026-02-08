<?php

namespace Database\Factories;

use App\Models\SubMerchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubMerchantFactory extends Factory
{
    protected $model = SubMerchant::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => fake()->company(),
            'is_active' => true,
            'verified_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'verified_at' => null,
        ]);
    }
}
