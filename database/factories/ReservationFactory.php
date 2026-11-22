<?php

namespace Database\Factories;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
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
        $guestCount = fake()->numberBetween(2, 8);
        $totalAmount = fake()->numberBetween(200000, 1000000);
        $paymentType = fake()->randomElement(['dp', 'full']);
        $dpPercentage = 50;

        $paidAmount = $paymentType === 'full' ? $totalAmount : ($totalAmount * $dpPercentage / 100);
        $remainingAmount = $totalAmount - $paidAmount;

        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'customer_name' => fake()->name(),
            'phone' => '+62'.fake()->numerify('8##########'),
            'email' => fake()->safeEmail(),
            'reservation_date' => $reservationDate,
            'reservation_time' => fake()->time('H:i'),
            'guest_count' => $guestCount,
            'table_id' => Table::factory(),
            'selected_products' => [
                [
                    'id' => fake()->numberBetween(1, 10),
                    'name' => fake()->randomElement(['Nasi Goreng', 'Mie Goreng', 'Ayam Bakar', 'Ikan Bakar', 'Sate Ayam']),
                    'quantity' => fake()->numberBetween(1, 3),
                    'price' => fake()->numberBetween(25000, 100000),
                ],
            ],
            'payment_type' => $paymentType,
            'payment_method' => 'qris',
            'qris_transaction_id' => null,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'remaining_amount' => $totalAmount,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.now()->timestamp.'-'.fake()->numberBetween(1000, 9999),
            'calendar_event_id' => null,
            'notified_at' => null,
            'cancelled_reason' => null,
        ];
    }

    /**
     * Indicate that the reservation is confirmed.
     */
    public function confirmed(): static
    {
        $totalAmount = fake()->numberBetween(200000, 1000000);
        $paymentType = fake()->randomElement(['dp', 'full']);
        $dpPercentage = 50;

        $paidAmount = $paymentType === 'full' ? $totalAmount : ($totalAmount * $dpPercentage / 100);
        $remainingAmount = $totalAmount - $paidAmount;

        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CONFIRMED,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $remainingAmount,
            'calendar_event_id' => 'mock_event_'.fake()->uuid(),
            'notified_at' => now(),
        ]);
    }

    /**
     * Indicate that the reservation is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_CANCELLED,
            'cancelled_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the reservation is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Reservation::STATUS_COMPLETED,
            'paid_amount' => $attributes['total_amount'],
            'remaining_amount' => 0,
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
     * Indicate that the reservation has paid DP only.
     */
    public function withDp(): static
    {
        $totalAmount = fake()->numberBetween(200000, 1000000);
        $dpPercentage = 50;
        $paidAmount = $totalAmount * $dpPercentage / 100;

        return $this->state(fn (array $attributes) => [
            'payment_type' => 'dp',
            'total_amount' => $totalAmount,
            'paid_amount' => $paidAmount,
            'remaining_amount' => $totalAmount - $paidAmount,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
    }

    /**
     * Indicate that the reservation is fully paid.
     */
    public function fullyPaid(): static
    {
        $totalAmount = fake()->numberBetween(200000, 1000000);

        return $this->state(fn (array $attributes) => [
            'payment_type' => 'full',
            'total_amount' => $totalAmount,
            'paid_amount' => $totalAmount,
            'remaining_amount' => 0,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);
    }
}
