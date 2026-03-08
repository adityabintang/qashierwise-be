<?php

namespace Tests\Unit;

use App\Models\ReservationFlowConfig;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppFlowService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsAppFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_reservation_flow_preserves_empty_object_payload_in_flow_json(): void
    {
        $user = User::factory()->create();
        WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'access_token' => 'test-token',
            'waba_id' => '1234567890',
        ]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'id' => 'flow_123',
            ], 200),
        ]);

        $service = app(WhatsAppFlowService::class);

        $result = $service->createReservationFlow($user->id);

        $this->assertSame('flow_123', $result['id']);

        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            if (! isset($payload['flow_json'])) {
                return false;
            }

            $decodedFlow = json_decode($payload['flow_json']);
            if (! is_object($decodedFlow)) {
                return false;
            }

            $completePayload = $decodedFlow->screens[4]->layout->children[5]->{'on-click-action'}->payload ?? null;

            return is_object($completePayload) && get_object_vars($completePayload) === [];
        });
    }

    public function test_create_reservation_flow_uses_dynamic_config_when_available(): void
    {
        Schema::create('reservation_flow_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('flow_id')->nullable();
            $table->string('flow_name')->default('Reservasi');
            $table->string('flow_status')->default('draft');
            $table->boolean('enable_table_selection')->default(true);
            $table->boolean('enable_menu_selection')->default(true);
            $table->boolean('require_menu_selection')->default(false);
            $table->boolean('enable_payment')->default(true);
            $table->decimal('table_fee', 12, 2)->default(100000);
            $table->decimal('dp_percentage', 5, 2)->default(50.00);
            $table->boolean('allow_full_payment')->default(true);
            $table->boolean('allow_dp_payment')->default(true);
            $table->boolean('enable_qris')->default(true);
            $table->boolean('enable_cash')->default(true);
            $table->boolean('require_email')->default(false);
            $table->boolean('require_event_type')->default(false);
            $table->string('header_text')->default('Buat Reservasi');
            $table->text('body_text')->nullable();
            $table->string('footer_text')->default('Powered by QashierWise');
            $table->string('cta_text')->default('Buat Reservasi');
            $table->json('available_table_ids')->nullable();
            $table->json('available_product_ids')->nullable();
            $table->json('enabled_event_types')->nullable();
            $table->json('blocked_times')->nullable();
            $table->json('operating_days')->nullable();
            $table->integer('time_interval')->default(60);
            $table->integer('max_advance_days')->default(30);
            $table->integer('min_advance_hours')->default(2);
            $table->integer('max_guests')->default(20);
            $table->integer('min_guests')->default(1);
            $table->time('opening_time')->default('10:00');
            $table->time('closing_time')->default('21:00');
            $table->timestamps();
        });

        $user = User::factory()->create();
        WhatsAppAccount::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'access_token' => 'test-token',
            'waba_id' => '1234567890',
        ]);

        $config = ReservationFlowConfig::create([
            'user_id' => $user->id,
            'flow_name' => 'Flow Khusus',
            'enable_table_selection' => false,
            'enable_menu_selection' => false,
            'enable_payment' => false,
            'allow_full_payment' => true,
            'allow_dp_payment' => false,
            'enable_qris' => false,
            'enable_cash' => true,
            'cta_text' => 'Pesan Sekarang',
        ]);

        Http::fake([
            'https://graph.facebook.com/v21.0/1234567890/flows' => Http::response([
                'id' => 'flow_dynamic_123',
            ], 200),
            'https://graph.facebook.com/v21.0/flow_dynamic_123/assets' => Http::response([
                'success' => true,
            ], 200),
        ]);

        $service = app(WhatsAppFlowService::class);

        $result = $service->createReservationFlow($user->id);

        $this->assertSame('flow_dynamic_123', $result['id']);
        $this->assertDatabaseHas('reservation_flow_configs', [
            'id' => $config->id,
            'flow_id' => 'flow_dynamic_123',
            'flow_status' => 'draft',
        ]);

        Http::assertSentCount(2);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://graph.facebook.com/v21.0/1234567890/flows') {
                return false;
            }

            $payload = $request->data();

            return str_starts_with($payload['name'] ?? '', 'Flow Khusus_')
                && isset($payload['endpoint_uri'], $payload['data_api_version'])
                && ! isset($payload['flow_json']);
        });

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.facebook.com/v21.0/flow_dynamic_123/assets'
                && str_contains($request->body(), 'flow.json')
                && str_contains($request->body(), 'WELCOME_SCREEN')
                && str_contains($request->body(), 'SUMMARY');
        });
    }
}
