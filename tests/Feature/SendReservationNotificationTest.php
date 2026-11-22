<?php

namespace Tests\Feature;

use App\Jobs\SendReservationNotification;
use App\Models\Reservation;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SendReservationNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_it_persists_outgoing_message_id_for_reservation_notification(): void
    {
        $reservation = Reservation::factory()->create([
            'phone' => '081234567890',
            'customer_name' => 'Budi',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $account = WhatsAppAccount::factory()->create([
            'user_id' => $reservation->user_id,
            'phone_number_id' => '1234567890',
            'is_active' => true,
        ]);

        $accountService = $this->createMock(WhatsAppAccountService::class);
        $accountService->expects($this->once())
            ->method('getActiveAccount')
            ->with($reservation->user_id)
            ->willReturn($account);

        $apiResponse = new class
        {
            public function decodedBody(): array
            {
                return [
                    'messages' => [
                        ['id' => 'wamid.test.outgoing.123'],
                    ],
                ];
            }
        };

        $whatsAppMock = Mockery::mock('overload:Netflie\\WhatsAppCloudApi\\WhatsAppCloudApi');
        $whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->andReturn($apiResponse);

        $job = new SendReservationNotification($reservation, 'success');
        $job->handle($accountService);

        $contact = WhatsAppContact::withoutGlobalScopes()
            ->where('user_id', $reservation->user_id)
            ->where('phone_number_id', '1234567890')
            ->where('wa_id', '6281234567890')
            ->first();

        $this->assertNotNull($contact);

        $message = WhatsAppMessage::withoutGlobalScopes()
            ->where('message_id', 'wamid.test.outgoing.123')
            ->first();

        $this->assertNotNull($message);
        $this->assertSame($reservation->user_id, $message->user_id);
        $this->assertSame('1234567890', $message->phone_number_id);
        $this->assertSame($contact->id, $message->contact_id);
        $this->assertSame('outgoing', $message->direction);
        $this->assertSame('sent', $message->status);
        $this->assertSame('reservation_notification', $message->metadata['source'] ?? null);
        $this->assertSame($reservation->id, $message->metadata['reservation_id'] ?? null);
    }

    public function test_success_message_includes_add_to_google_calendar_link(): void
    {
        $reservation = Reservation::factory()->create([
            'phone' => '081234567890',
            'customer_name' => 'Siti',
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $account = WhatsAppAccount::factory()->create([
            'user_id' => $reservation->user_id,
            'phone_number_id' => '1234567890',
            'is_active' => true,
        ]);

        $accountService = $this->createMock(WhatsAppAccountService::class);
        $accountService->method('getActiveAccount')->willReturn($account);

        $captured = null;
        $apiResponse = new class
        {
            public function decodedBody(): array
            {
                return ['messages' => [['id' => 'wamid.cal.link.1']]];
            }
        };

        $whatsAppMock = Mockery::mock('overload:Netflie\\WhatsAppCloudApi\\WhatsAppCloudApi');
        $whatsAppMock->shouldReceive('sendTextMessage')
            ->once()
            ->andReturnUsing(function ($to, $message, $preview) use (&$captured, $apiResponse) {
                $captured = $message;

                return $apiResponse;
            });

        (new SendReservationNotification($reservation, 'success'))->handle($accountService);

        $this->assertNotNull($captured);
        $this->assertStringContainsString('Tambahkan ke Google Calendar', $captured);
        $this->assertStringContainsString('https://calendar.google.com/calendar/render?', $captured);
    }
}
