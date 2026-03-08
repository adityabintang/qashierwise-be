<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\ReservationConfig;
use App\Models\User;
use App\Services\WhatsAppFlowEncryptionService;
use App\Services\WhatsAppFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsAppFlowControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ==================== Authenticated Flow Management ====================

    public function test_authenticated_user_can_list_flows(): void
    {
        $user = User::factory()->create();
        $this->mock(WhatsAppFlowService::class, function ($mock) use ($user) {
            $mock->shouldReceive('listFlows')
                ->once()
                ->with($user->id)
                ->andReturn([
                    ['id' => 'flow_123', 'name' => 'Reservasi', 'status' => 'DRAFT'],
                ]);
        });

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/flows')
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonCount(1, 'data');
    }

    public function test_authenticated_user_can_create_flow(): void
    {
        $user = User::factory()->create();
        $this->mock(WhatsAppFlowService::class, function ($mock) use ($user) {
            $mock->shouldReceive('createReservationFlow')
                ->once()
                ->with($user->id)
                ->andReturn(['id' => 'flow_456', 'name' => 'Reservasi_aBcDeF']);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/flows')
            ->assertCreated()
            ->assertJsonFragment(['id' => 'flow_456']);
    }

    public function test_authenticated_user_can_publish_flow(): void
    {
        $user = User::factory()->create();
        $flowId = 'flow_789';

        $this->mock(WhatsAppFlowService::class, function ($mock) use ($user, $flowId) {
            $mock->shouldReceive('publishFlow')
                ->once()
                ->with($user->id, $flowId)
                ->andReturn(true);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/whatsapp/flows/{$flowId}/publish")
            ->assertOk()
            ->assertJson(['success' => true, 'flow_id' => $flowId]);
    }

    public function test_authenticated_user_can_delete_flow(): void
    {
        $user = User::factory()->create();
        $flowId = 'flow_del_01';

        $this->mock(WhatsAppFlowService::class, function ($mock) use ($user, $flowId) {
            $mock->shouldReceive('deleteFlow')
                ->once()
                ->with($user->id, $flowId)
                ->andReturn(true);
        });

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/whatsapp/flows/{$flowId}")
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_send_flow_requires_phone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/flows/send', ['flow_id' => 'flow_001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['phone']);
    }

    public function test_send_flow_requires_flow_id_when_no_config(): void
    {
        $user = User::factory()->create();

        $this->mock(WhatsAppFlowService::class, function ($mock) {
            $mock->shouldReceive('getFlowConfig')->once()->andReturn(null);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/flows/send', ['phone' => '6281234567890'])
            ->assertUnprocessable()
            ->assertJsonFragment(['message' => 'flow_id wajib diisi']);
    }

    public function test_authenticated_user_can_send_flow(): void
    {
        $user = User::factory()->create();

        $this->mock(WhatsAppFlowService::class, function ($mock) use ($user) {
            $mock->shouldReceive('getFlowConfig')->once()->andReturn(null);
            $mock->shouldReceive('sendReservationFlow')
                ->once()
                ->andReturn([
                    'success' => true,
                    'flow_token' => $user->id.'_uuid-test',
                    'message_id' => 'wamid.test123',
                ]);
        });

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/flows/send', [
                'phone' => '6281234567890',
                'flow_id' => 'flow_001',
            ])
            ->assertOk()
            ->assertJsonFragment(['success' => true]);
    }

    public function test_unauthenticated_request_cannot_access_flow_management(): void
    {
        $this->getJson('/api/whatsapp/flows')->assertUnauthorized();
        $this->postJson('/api/whatsapp/flows')->assertUnauthorized();
    }

    // ==================== Public Flow Endpoint ====================

    public function test_flow_endpoint_health_check_returns_ok(): void
    {
        $this->get('/api/whatsapp/flow/endpoint')
            ->assertOk()
            ->assertSee('OK');
    }

    public function test_flow_endpoint_rejects_missing_encryption_params(): void
    {
        $this->post('/api/whatsapp/flow/endpoint', [])
            ->assertStatus(400);
    }

    public function test_flow_endpoint_returns_health_check_payload_for_ping_action(): void
    {
        $user = User::factory()->create();
        $flowToken = $user->id.'_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';

        ReservationConfig::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'reminder_template_language' => 'id',
        ]);

        $this->mock(WhatsAppFlowEncryptionService::class, function ($mock) use ($flowToken) {
            $mock->shouldReceive('decryptRequest')
                ->once()
                ->andReturn([
                    'data' => ['action' => 'ping', 'flow_token' => $flowToken],
                    'aes_key' => 'mock_aes_key',
                    'iv' => str_repeat("\x00", 16),
                ]);
            $mock->shouldReceive('encryptResponse')
                ->once()
                ->with(Mockery::on(fn ($r) => ($r['data']['status'] ?? null) === 'active'), Mockery::any(), Mockery::any())
                ->andReturn(base64_encode('{"data":{"status":"active"}}'));
        });

        $this->postJson('/api/whatsapp/flow/endpoint', [
            'encrypted_aes_key' => base64_encode('test'),
            'encrypted_flow_data' => base64_encode('test'),
            'initial_vector' => base64_encode('test'),
        ])->assertOk();
    }

    public function test_flow_endpoint_creates_reservation_on_complete_action(): void
    {
        $user = User::factory()->create();
        $flowToken = $user->id.'_xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx';

        ReservationConfig::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'reminder_template_language' => 'id',
        ]);

        $completePayload = [
            'action' => 'complete',
            'flow_token' => $flowToken,
            'data' => [
                'customer_name' => 'Test Customer',
                'phone' => '6281234567890',
                'reservation_date' => now()->addDays(3)->format('Y-m-d'),
                'reservation_time' => '12:00',
                'guest_count' => '2',
                'payment_type' => 'full',
                'payment_method' => 'cash',
            ],
        ];

        $createdReservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'customer_name' => 'Test Customer',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->mock(WhatsAppFlowEncryptionService::class, function ($mock) use ($completePayload) {
            $mock->shouldReceive('decryptRequest')
                ->once()
                ->andReturn([
                    'data' => $completePayload,
                    'aes_key' => 'mock_aes_key',
                    'iv' => str_repeat("\x00", 16),
                ]);
            $mock->shouldReceive('encryptResponse')
                ->once()
                ->with(Mockery::on(fn ($r) => $r['screen'] === 'SUCCESS'), Mockery::any(), Mockery::any())
                ->andReturn(base64_encode('{"screen":"SUCCESS"}'));
        });

        $this->mock(WhatsAppFlowService::class, function ($mock) use ($createdReservation) {
            $mock->shouldReceive('processFlowResponse')
                ->once()
                ->andReturn($createdReservation);
        });

        $this->postJson('/api/whatsapp/flow/endpoint', [
            'encrypted_aes_key' => base64_encode('test'),
            'encrypted_flow_data' => base64_encode('test'),
            'initial_vector' => base64_encode('test'),
        ])->assertOk();
    }

    // ==================== processFlowResponse Correctness ====================

    public function test_process_flow_response_maps_lunas_to_full_payment_type(): void
    {
        $user = User::factory()->create();

        $responseData = [
            'customer_name' => 'Budi Santoso',
            'phone' => '6281234567890',
            'reservation_date' => now()->addDays(2)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'guest_count' => '3',
            'payment_type' => 'lunas',
            'payment_method' => 'cash',
            'total_amount' => 200000,
        ];

        $service = app(WhatsAppFlowService::class);
        $reservation = $service->processFlowResponse($user->id, $user->id.'_test-token', $responseData);

        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->id,
            'payment_type' => 'full',
            'customer_name' => 'Budi Santoso',
        ]);
    }

    public function test_process_flow_response_sets_pending_payment_for_qris_dp(): void
    {
        $user = User::factory()->create();

        $responseData = [
            'customer_name' => 'Ani Wijaya',
            'phone' => '6282345678901',
            'reservation_date' => now()->addDays(5)->format('Y-m-d'),
            'reservation_time' => '18:00',
            'guest_count' => '2',
            'payment_type' => 'dp',
            'payment_method' => 'qris',
            'total_amount' => 300000,
        ];

        $service = app(WhatsAppFlowService::class);
        $reservation = $service->processFlowResponse($user->id, $user->id.'_dp-test-token', $responseData);

        $this->assertSame(Reservation::STATUS_PENDING_PAYMENT, $reservation->status);
        $this->assertSame('dp', $reservation->payment_type);
    }

    public function test_process_flow_response_auto_confirms_cash_full_payment(): void
    {
        $user = User::factory()->create();

        $responseData = [
            'customer_name' => 'Citra Dewi',
            'phone' => '6283456789012',
            'reservation_date' => now()->addDays(7)->format('Y-m-d'),
            'reservation_time' => '20:00',
            'guest_count' => '4',
            'payment_type' => 'full',
            'payment_method' => 'cash',
        ];

        $service = app(WhatsAppFlowService::class);
        $reservation = $service->processFlowResponse($user->id, $user->id.'_cash-test', $responseData);

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->status);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertNotNull($reservation->order_id);
    }

    public function test_flow_endpoint_creates_reservation_on_payment_confirmed_trigger(): void
    {
        $user = User::factory()->create();
        $flowToken = $user->id.'_payment-confirm-test';

        ReservationConfig::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'reminder_template_language' => 'id',
        ]);

        // Pre-populate the cache the way the flow progression would
        \Illuminate\Support\Facades\Cache::put('flow_data:'.$flowToken, [
            'reservation_date' => now()->addDays(3)->format('Y-m-d'),
            'reservation_time' => '19:00',
            'customer_name' => 'Dedi Pratama',
            'phone' => '6285678901234',
            'guest_count' => '3',
            'table_id' => null,
            'selected_products' => [],
            'menu_total' => 0.0,
            'table_fee' => 0.0,
            'grand_total' => 0.0,
            'dp_amount' => 0,
        ], now()->addHours(2));

        $payload = [
            'action' => 'data_exchange',
            'flow_token' => $flowToken,
            'data' => [
                'trigger' => 'payment_confirmed',
                'payment_type' => 'full',
                'payment_method' => 'cash',
            ],
        ];

        $createdReservation = Reservation::factory()->create([
            'user_id' => $user->id,
            'customer_name' => 'Dedi Pratama',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $this->mock(WhatsAppFlowEncryptionService::class, function ($mock) use ($payload) {
            $mock->shouldReceive('decryptRequest')
                ->once()
                ->andReturn([
                    'data' => $payload,
                    'aes_key' => 'mock_aes_key',
                    'iv' => str_repeat("\x00", 16),
                ]);
            $mock->shouldReceive('encryptResponse')
                ->once()
                ->with(Mockery::on(fn ($r) => $r['screen'] === 'SUCCESS'), Mockery::any(), Mockery::any())
                ->andReturn(base64_encode('{"screen":"SUCCESS"}'));
        });

        $this->mock(WhatsAppFlowService::class, function ($mock) use ($createdReservation) {
            $mock->shouldReceive('processFlowResponse')
                ->once()
                ->andReturn($createdReservation);
        });

        $this->postJson('/api/whatsapp/flow/endpoint', [
            'encrypted_aes_key' => base64_encode('test'),
            'encrypted_flow_data' => base64_encode('test'),
            'initial_vector' => base64_encode('test'),
        ])->assertOk();

        // Cache should be cleared after successful reservation creation
        $this->assertNull(\Illuminate\Support\Facades\Cache::get('flow_data:'.$flowToken));
    }
}
