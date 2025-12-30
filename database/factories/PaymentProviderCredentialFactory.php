<?php

namespace Database\Factories;

use App\Models\PaymentProviderCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentProviderCredentialFactory extends Factory
{
    protected $model = PaymentProviderCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'midtrans',
            'credentials_encrypted' => encrypt(json_encode([
                'server_key' => 'test-server-key-' . fake()->uuid(),
                'client_key' => 'test-client-key-' . fake()->uuid(),
            ])),
            'is_active' => false,
            'connection_status' => 'pending',
            'last_validated_at' => null,
            'validation_error' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => 'valid',
            'last_validated_at' => now(),
            'validation_error' => null,
        ]);
    }

    public function invalid(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => 'invalid',
            'last_validated_at' => now(),
            'validation_error' => 'Invalid credentials',
        ]);
    }

    public function xendit(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'xendit',
            'credentials_encrypted' => encrypt(json_encode([
                'api_key' => 'test-api-key-' . fake()->uuid(),
                'webhook_token' => 'test-webhook-token-' . fake()->uuid(),
            ])),
        ]);
    }

    public function midtrans(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'midtrans',
            'credentials_encrypted' => encrypt(json_encode([
                'server_key' => 'test-server-key-' . fake()->uuid(),
                'client_key' => 'test-client-key-' . fake()->uuid(),
            ])),
        ]);
    }
}
