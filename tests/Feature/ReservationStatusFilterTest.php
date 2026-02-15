<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_filter_reservations_by_dp_confirmed_status(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $dpConfirmedReservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'payment_type' => Reservation::PAYMENT_TYPE_DP,
        ]);

        Reservation::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
        ]);

        Reservation::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'payment_type' => Reservation::PAYMENT_TYPE_DP,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/reservations?status=dp_confirmed');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dpConfirmedReservation->id)
            ->assertJsonPath('data.0.status', Reservation::STATUS_CONFIRMED)
            ->assertJsonPath('data.0.payment_type', Reservation::PAYMENT_TYPE_DP)
            ->assertJsonPath('data.0.status_label', 'DP Confirmed');
    }
}
