<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_name' => 'pro',
            'status' => 'active',
            'midtrans_subscription_id' => 'sub_'.$this->faker->uuid(),
            'midtrans_customer_id' => 'cust_'.$this->faker->uuid(),
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the subscription is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'current_period_end' => now()->addMonth(),
            'cancelled_at' => null,
        ]);
    }

    /**
     * Indicate that the subscription is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now()->subDays(5),
        ]);
    }

    /**
     * Indicate that the subscription is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'current_period_end' => now()->subDays(10),
        ]);
    }

    /**
     * Indicate that the subscription is on trial.
     */
    public function trial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trial',
            'plan_name' => 'free_trial',
            'current_period_end' => now()->addDays(14),
        ]);
    }

    /**
     * Indicate that the subscription uses Midtrans provider.
     */
    public function midtrans(): static
    {
        return $this->state(fn (array $attributes) => [
            'midtrans_subscription_id' => 'sub_'.$this->faker->uuid(),
            'midtrans_customer_id' => 'cust_'.$this->faker->uuid(),
        ]);
    }
}
