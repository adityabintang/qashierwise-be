<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\WhatsAppFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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
}
