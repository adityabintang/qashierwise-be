<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationConfigValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_fee_is_required(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'store_id' => $store->id,
            'guest_options' => [2, 4, 6, 8],
            'dp_percentage' => 50,
            'allow_full_payment' => true,
            'allow_dp_payment' => true,
        ];

        $this->actingAs($user)
            ->postJson('/api/reservation-config', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_fee']);
    }

    public function test_at_least_one_payment_type_must_be_selected(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'store_id' => $store->id,
            'guest_options' => [2, 4, 6, 8],
            'dp_percentage' => 50,
            'reservation_fee' => 400000,
            'allow_full_payment' => false,
            'allow_dp_payment' => false,
        ];

        $this->actingAs($user)
            ->postJson('/api/reservation-config', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Minimal satu jenis pembayaran harus dipilih.');
    }
}
