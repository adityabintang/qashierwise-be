<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    protected $model = WhatsAppAccount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone_number_id' => $this->faker->numerify('##############'),
            'business_account_id' => $this->faker->numerify('##############'),
            'waba_id' => $this->faker->numerify('##############'),
            'access_token' => $this->faker->sha256(),
            'token_expires_at' => now()->addDays(60),
            'is_active' => true,
            'coexistence_enabled' => false,
            'connection_method' => 'embedded_signup',
            'display_phone_number' => $this->faker->e164PhoneNumber(),
            'quality_rating' => 'GREEN',
            'name' => $this->faker->company(),
        ];
    }

    /**
     * Indicate that the account is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
