<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected WhatsAppAccount $whatsappAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->whatsappAccount = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($this->user);
    }

    // ==================== Index Tests ====================

    public function test_can_list_reservations(): void
    {
        Reservation::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/reservations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'customer_name',
                            'phone',
                            'reservation_date',
                            'reservation_time',
                            'guest_count',
                            'status',
                        ],
                    ],
                ],
            ]);
    }

    public function test_can_filter_reservations_by_status(): void
    {
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/reservations?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_filter_today_reservations(): void
    {
        Reservation::factory()->today()->create([
            'user_id' => $this->user->id,
        ]);
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'reservation_date' => now()->addDays(5),
        ]);

        $response = $this->getJson('/api/reservations?today=1');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.data'));
    }

    // ==================== Store Tests ====================

    public function test_can_create_reservation(): void
    {
        $data = [
            'customer_name' => 'John Doe',
            'phone' => '+6281234567890',
            'reservation_date' => now()->addDays(3)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 4,
            'email' => 'john@example.com',
            'event_type' => 'birthday',
            'special_notes' => 'Window seat preferred',
        ];

        $response = $this->postJson('/api/reservations', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil dibuat',
            ]);

        $this->assertDatabaseHas('reservations', [
            'customer_name' => 'John Doe',
            'phone' => '+6281234567890',
            'status' => 'pending',
        ]);
    }

    public function test_cannot_create_reservation_without_required_fields(): void
    {
        $response = $this->postJson('/api/reservations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_name',
                'phone',
                'reservation_date',
                'reservation_time',
                'guest_count',
            ]);
    }

    public function test_cannot_create_reservation_for_past_date(): void
    {
        $data = [
            'customer_name' => 'John Doe',
            'phone' => '+6281234567890',
            'reservation_date' => now()->subDays(1)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => 4,
        ];

        $response = $this->postJson('/api/reservations', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reservation_date']);
    }

    // ==================== Show Tests ====================

    public function test_can_view_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $reservation->id,
                    'customer_name' => $reservation->customer_name,
                ],
            ]);
    }

    public function test_cannot_view_other_users_reservation(): void
    {
        $otherUser = User::factory()->create();
        $reservation = Reservation::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(403);
    }

    // ==================== Update Tests ====================

    public function test_can_update_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->putJson("/api/reservations/{$reservation->id}", [
            'guest_count' => 6,
            'special_notes' => 'Updated notes',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil diperbarui',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'guest_count' => 6,
            'special_notes' => 'Updated notes',
        ]);
    }

    // ==================== Delete Tests ====================

    public function test_can_delete_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/reservations/{$reservation->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil dihapus',
            ]);

        $this->assertDatabaseMissing('reservations', [
            'id' => $reservation->id,
        ]);
    }

    // ==================== Status Action Tests ====================

    public function test_can_confirm_pending_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/confirm");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil dikonfirmasi',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'confirmed',
        ]);
    }

    public function test_cannot_confirm_non_pending_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/confirm");

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_can_cancel_reservation(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/cancel", [
            'reason' => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil dibatalkan',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'cancelled',
            'cancellation_reason' => 'Customer requested cancellation',
        ]);
    }

    public function test_can_complete_confirmed_reservation(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/complete");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi berhasil diselesaikan',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'completed',
        ]);
    }

    public function test_can_mark_no_show(): void
    {
        $reservation = Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->postJson("/api/reservations/{$reservation->id}/no-show");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Reservasi ditandai sebagai no-show',
            ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'status' => 'no_show',
        ]);
    }

    // ==================== Statistics Tests ====================

    public function test_can_get_statistics(): void
    {
        Reservation::factory()->today()->create([
            'user_id' => $this->user->id,
        ]);
        Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
            'reservation_date' => now()->addDays(3),
        ]);

        $response = $this->getJson('/api/reservations/statistics');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'statistics' => [
                        'today',
                        'upcoming',
                        'pending',
                        'confirmed',
                        'total_this_month',
                        'completed_this_month',
                    ],
                    'today_reservations',
                ],
            ]);
    }

    // ==================== Model Tests ====================

    public function test_reservation_has_correct_casts(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'preferences' => ['window', 'quiet'],
        ]);

        $this->assertIsArray($reservation->preferences);
        $this->assertContains('window', $reservation->preferences);
    }

    public function test_reservation_scopes(): void
    {
        Reservation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        Reservation::factory()->confirmed()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertEquals(1, Reservation::pending()->count());
        $this->assertEquals(1, Reservation::confirmed()->count());
    }

    public function test_reservation_helper_methods(): void
    {
        $reservation = Reservation::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->assertTrue($reservation->isPending());
        $this->assertFalse($reservation->isConfirmed());

        $reservation->confirm();

        $this->assertFalse($reservation->isPending());
        $this->assertTrue($reservation->isConfirmed());
    }
}
