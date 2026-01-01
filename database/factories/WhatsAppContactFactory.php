<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WhatsAppContact>
 */
class WhatsAppContactFactory extends Factory
{
    protected $model = WhatsAppContact::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wa_id' => $this->faker->numerify('62812########'),
            'name' => $this->faker->name(),
            'profile_pic_url' => null,
            'last_message_at' => now(),
            'last_message_text' => $this->faker->sentence(),
            'unread_count' => 0,
        ];
    }
}
