<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationConfigBulkSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_bulk_slots_returns_expected_count(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'store_id' => $store->id,
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-21',
            'opening_time' => '10:00',
            'closing_time' => '12:00',
            'slot_duration' => 60,
            'capacity_per_slot' => 12,
            'exclude_dates' => ['2026-02-21'],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/reservation-config/generate-slots', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 2);

        $slots = $response->json('data.slots');
        $this->assertSame([
            '2026-02-20T10:00',
            '2026-02-20T11:00',
        ], $slots);
    }

    public function test_generate_bulk_slots_validates_time_range(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create([
            'user_id' => $user->id,
        ]);

        $payload = [
            'store_id' => $store->id,
            'start_date' => '2026-02-20',
            'end_date' => '2026-02-20',
            'opening_time' => '18:00',
            'closing_time' => '17:00',
            'slot_duration' => 30,
            'capacity_per_slot' => 12,
        ];

        $this->actingAs($user)
            ->postJson('/api/reservation-config/generate-slots', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['closing_time']);
    }
}
