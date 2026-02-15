<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAmountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_calculate_amounts_uses_reservation_fee_and_products(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $config = ReservationConfig::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'is_active' => true,
            'available_slots' => [],
            'guest_options' => [2, 4, 6, 8],
            'dp_percentage' => 50.0,
            'allow_full_payment' => true,
            'allow_dp_payment' => true,
            'reservation_fee' => 400000,
        ]);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'price' => 12200,
        ]);

        $service = app(ReservationService::class);
        $amounts = $service->calculateAmounts([
            'selected_products' => [
                ['id' => $product->id, 'quantity' => 3],
            ],
        ], $config);

        $this->assertEquals(436600, $amounts['total_amount']);
        $this->assertEquals(218300, $amounts['dp_amount']);
        $this->assertEquals(218300, $amounts['remaining_amount']);
    }

    public function test_calculate_amounts_uses_reservation_fee_when_no_products(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $config = ReservationConfig::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'is_active' => true,
            'available_slots' => [],
            'guest_options' => [2, 4, 6, 8],
            'dp_percentage' => 50.0,
            'allow_full_payment' => true,
            'allow_dp_payment' => true,
            'reservation_fee' => 400000,
        ]);

        $service = app(ReservationService::class);
        $amounts = $service->calculateAmounts([], $config);

        $this->assertEquals(400000, $amounts['total_amount']);
        $this->assertEquals(200000, $amounts['dp_amount']);
        $this->assertEquals(200000, $amounts['remaining_amount']);
    }
}
