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
        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'is_active' => true,
            'guest_options' => [2, 4, 6, 8, 10],
            'dp_percentage' => 50.00,
            'allow_full_payment' => true,
            'allow_dp_payment' => true,
            'reminder_enabled' => false,
            'reminder_template' => null,
            'reminder_template_language' => null,
            'reminder_timing' => [],
            'reminder_param_mapping' => [],
        ];
    }

    /**
     * Indicate that reminders are enabled and configured.
     */
    public function withReminders(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_enabled' => true,
            'reminder_template' => 'reservation_reminder',
            'reminder_template_language' => 'id',
            'reminder_timing' => [60, 1440],
            'reminder_param_mapping' => ['1' => 'customer_name', '2' => 'reservation_date'],
        ]);
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
