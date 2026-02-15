<?php

namespace Database\Factories;

use App\Models\AiAgent;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiAgent>
 */
class AiAgentFactory extends Factory
{
    protected $model = AiAgent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_account_id' => WhatsAppAccount::factory(),
            'default_store_id' => null,
            'bot_name' => $this->faker->name().' Bot',
            'system_prompt' => 'Kamu adalah asisten virtual yang ramah dan membantu.',
            'business_info' => [
                'operating_hours' => '08:00 - 22:00',
                'address' => $this->faker->address(),
                'description' => $this->faker->sentence(),
            ],
            'order_enabled' => true,
            'qris_enabled' => false,
            'reservation_enabled' => false,
            'is_active' => true,
            'settings' => [],
            'use_optimized_prompt' => true,
            'enable_prompt_caching' => false,
            'product_sample_limit' => 10,
        ];
    }

    /**
     * Indicate that prompt caching is enabled.
     */
    public function withCaching(): static
    {
        return $this->state(fn (array $attributes) => [
            'enable_prompt_caching' => true,
        ]);
    }

    /**
     * Indicate that the agent is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that QRIS is enabled.
     */
    public function withQris(): static
    {
        return $this->state(fn (array $attributes) => [
            'qris_enabled' => true,
        ]);
    }

    /**
     * Indicate that reservation is enabled.
     */
    public function withReservation(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_enabled' => true,
        ]);
    }
}
