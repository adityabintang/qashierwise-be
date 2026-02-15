<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_events_include_table_capacity_notes_and_time_display(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $table = Table::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'number' => 'A1',
            'capacity' => 4,
        ]);

        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'table_id' => $table->id,
            'guest_count' => 2,
            'notes' => 'Tolong dekat jendela',
            'status' => Reservation::STATUS_CONFIRMED,
            'reservation_date' => today()->toDateString(),
            'reservation_time' => null,
            'order_id' => 'RSV-TEST-001',
        ]);

        $this->assertSame(1, Reservation::where('user_id', $user->id)
            ->whereDate('reservation_date', today()->toDateString())
            ->whereIn('status', [Reservation::STATUS_CONFIRMED, Reservation::STATUS_COMPLETED])
            ->count());

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/reservations/calendar?start='.today()->toDateString().'&end='.today()->toDateString());

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id)
            ->assertJsonPath('data.0.title', $reservation->customer_name.' (4 tamu)')
            ->assertJsonPath('data.0.extendedProps.order_id', 'RSV-TEST-001')
            ->assertJsonPath('data.0.extendedProps.table_name', 'Meja A1')
            ->assertJsonPath('data.0.extendedProps.table_capacity', 4)
            ->assertJsonPath('data.0.extendedProps.guest_count', 4)
            ->assertJsonPath('data.0.extendedProps.notes', 'Tolong dekat jendela')
            ->assertJsonPath('data.0.extendedProps.time_display', 'Waktu belum ditentukan');
    }
}
