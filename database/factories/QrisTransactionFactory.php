<?php

namespace Database\Factories;

use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class QrisTransactionFactory extends Factory
{
    protected $model = QrisTransaction::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 10000, 1000000);
        $platformFee = QrisTransaction::calculatePlatformFee($amount);
        $netAmount = QrisTransaction::calculateNetAmount($amount);

        return [
            'sub_merchant_id' => SubMerchant::factory(),
            'order_id' => QrisTransaction::generateOrderId(),
            'amount' => $amount,
            'platform_fee' => $platformFee,
            'net_amount' => $netAmount,
            'status' => QrisTransaction::STATUS_PENDING,
            'provider' => 'midtrans',
            'provider_transaction_id' => 'test-' . fake()->uuid(),
            'qr_code_url' => fake()->url(),
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function settled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QrisTransaction::STATUS_EXPIRE,
            'expires_at' => now()->subMinutes(30),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => QrisTransaction::STATUS_CANCEL,
        ]);
    }

    public function xendit(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'xendit',
        ]);
    }

    public function midtrans(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'midtrans',
            'midtrans_transaction_id' => 'midtrans-' . fake()->uuid(),
        ]);
    }
}
