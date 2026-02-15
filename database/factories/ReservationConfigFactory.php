<?php

namespace Database\Factories;

use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReservationConfig>
 */
class ReservationConfigFactory extends Factory
{
    protected $model = ReservationConfig::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate 5 available dates in the next 30 days
        $availableDays = [];
        for ($i = 1; $i <= 5; $i++) {
            $date = now()->addDays(fake()->numberBetween($i * 5, ($i + 1) * 5));
            $availableDays[] = $date->toDateString();
        }

        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'is_active' => true,
            'available_days' => $availableDays,
            'guest_options' => [2, 4, 6, 8, 10],
            'dp_percentage' => 50.00,
            'allow_full_payment' => true,
            'allow_dp_payment' => true,
            'notification_phone' => '+62'.fake()->numerify('8##########'),
        ];
    }

    /**
     * Indicate that the config is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that only full payment is allowed.
     */
    public function fullPaymentOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_full_payment' => true,
            'allow_dp_payment' => false,
        ]);
    }

    /**
     * Indicate that only DP payment is allowed.
     */
    public function dpPaymentOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'allow_full_payment' => false,
            'allow_dp_payment' => true,
        ]);
    }

    /**
     * Set custom DP percentage.
     */
    public function withDpPercentage(float $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'dp_percentage' => $percentage,
        ]);
    }
}
