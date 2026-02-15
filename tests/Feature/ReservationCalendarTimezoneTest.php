<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationCalendarTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_at_17_00_shows_correct_time_in_calendar(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $table = Table::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'number' => 'A1',
            'capacity' => 4,
        ]);

        // Create reservation at 17:00 WIB
        $reservationDate = today()->toDateString();
        $reservationTime = '17:00';

        $reservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'table_id' => $table->id,
            'guest_count' => 2,
            'status' => Reservation::STATUS_CONFIRMED,
            'reservation_date' => $reservationDate,
            'reservation_time' => $reservationTime,
            'order_id' => 'RSV-TEST-002',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/reservations/calendar?start='.$reservationDate.'&end='.$reservationDate);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reservation->id)
            ->assertJsonPath('data.0.extendedProps.reservation_time', '17:00')
            ->assertJsonPath('data.0.extendedProps.time_display', '17:00 WIB');

        // Verify the start datetime is correct in ISO format with WIB timezone
        $responseData = $response->json('data.0');
        $this->assertNotNull($responseData['start']);

        // Parse the returned start time
        $startDateTime = Carbon::parse($responseData['start']);

        // Should be 17:00 in Asia/Jakarta timezone
        $expectedDateTime = Carbon::parse($reservationDate.' '.$reservationTime, 'Asia/Jakarta');

        $this->assertTrue(
            $startDateTime->equalTo($expectedDateTime),
            "Expected: {$expectedDateTime->toIso8601String()}, Got: {$startDateTime->toIso8601String()}"
        );
    }

    public function test_multiple_reservations_at_different_times_show_correctly(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);

        $reservationDate = today()->toDateString();

        // Create reservations at different times
        $times = ['07:00', '12:00', '17:00', '20:00'];
        $reservations = [];

        foreach ($times as $time) {
            $reservations[] = Reservation::factory()->create([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'status' => Reservation::STATUS_CONFIRMED,
                'reservation_date' => $reservationDate,
                'reservation_time' => $time,
            ]);
        }

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/reservations/calendar?start='.$reservationDate.'&end='.$reservationDate);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data');

        $responseData = $response->json('data');

        // Verify each reservation has the correct time
        foreach ($responseData as $index => $event) {
            $expectedTime = $times[$index];
            $this->assertEquals(
                $expectedTime,
                $event['extendedProps']['reservation_time'],
                "Reservation at index {$index} should have time {$expectedTime}"
            );

            $this->assertEquals(
                $expectedTime.' WIB',
                $event['extendedProps']['time_display'],
                "Reservation at index {$index} should display as {$expectedTime} WIB"
            );

            // Verify ISO datetime
            $startDateTime = Carbon::parse($event['start']);
            $expectedDateTime = Carbon::parse($reservationDate.' '.$expectedTime, 'Asia/Jakarta');

            $this->assertTrue(
                $startDateTime->equalTo($expectedDateTime),
                "Reservation at {$expectedTime}: Expected {$expectedDateTime->toIso8601String()}, Got {$startDateTime->toIso8601String()}"
            );
        }
    }
}
