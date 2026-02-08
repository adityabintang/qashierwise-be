<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\User;
use App\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Reservation>
 */
class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $reservationDate = fake()->dateTimeBetween('now', '+30 days');

        return [
            'user_id' => User::factory(),
            'whatsapp_contact_id' => null,
            'customer_name' => fake()->name(),
            'phone' => '+62'.fake()->numerify('8##########'),
            'reservation_date' => $reservationDate,
            'reservation_time' => fake()->randomElement(['11:00', '12:00', '13:00', '18:00', '19:00', '20:00']),
            'guest_count' => fake()->numberBetween(1, 10),
            'email' => fake()->optional(0.5)->safeEmail(),
            'event_type' => fake()->optional(0.3)->randomElement(['regular', 'birthday', 'meeting', 'anniversary', 'other']),
            'special_notes' => fake()->optional(0.3)->sentence(),
            'preferences' => fake()->optional(0.3)->randomElements(['window', 'quiet', 'smoking', 'baby_chair'], fake()->numberBetween(1, 2)),
            'deposit' => fake()->randomElement([0, 0, 0, 50000, 100000, 200000]),
            'deposit_paid' => false,
            'pre_order_items' => null,
            'flow_token' => null,
            'flow_id' => null,
            'status' => 'pending',
        ];
    }

    /**
     * Indicate that the reservation is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Indicate that the reservation is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the reservation is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => now(),
            'reservation_date' => fake()->dateTimeBetween('-7 days', 'yesterday'),
        ]);
    }

    /**
     * Indicate that the reservation is for today.
     */
    public function today(): static
    {
        return $this->state(fn (array $attributes) => [
            'reservation_date' => now()->toDateString(),
        ]);
    }

    /**
     * Indicate that the reservation has a WhatsApp contact.
     */
    public function withContact(): static
    {
        return $this->state(fn (array $attributes) => [
            'whatsapp_contact_id' => WhatsAppContact::factory(),
        ]);
    }

    /**
     * Indicate that the reservation is for a birthday.
     */
    public function birthday(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'birthday',
            'special_notes' => 'Birthday celebration - please prepare cake candles',
        ]);
    }

    /**
     * Indicate that the reservation requires deposit.
     */
    public function withDeposit(float $amount = 100000): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit' => $amount,
            'deposit_paid' => false,
        ]);
    }

    /**
     * Indicate that the reservation has paid deposit.
     */
    public function depositPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'deposit' => $attributes['deposit'] ?? 100000,
            'deposit_paid' => true,
        ]);
    }

    /**
     * Indicate that the reservation came from WhatsApp Flow.
     */
    public function fromFlow(string $flowId = 'FLOW_123'): static
    {
        return $this->state(fn (array $attributes) => [
            'flow_token' => fake()->uuid(),
            'flow_id' => $flowId,
        ]);
    }
}
