<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\FonnteService;
use App\Services\GoogleCalendarService;
use App\Services\InvoiceService;
use App\Services\ReservationFulfillmentService;
use App\Services\WhatsAppAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * ReservationFulfillmentService:
 *  - onPaymentConfirmed: sends WA confirmation + calendar link + invoice
 *  - onMerchantComplete: sends WA "merchant confirmed" + calendar link
 */
class ReservationFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeReservation(array $overrides = []): Reservation
    {
        $merchant = User::factory()->create();
        $store    = Store::factory()->create(['user_id' => $merchant->id]);

        return Reservation::factory()->create(array_merge([
            'user_id'          => $merchant->id,
            'store_id'         => $store->id,
            'customer_name'    => 'Budi Santoso',
            'phone'            => '081234567890',
            'email'            => 'budi@example.com',
            'reservation_date' => now()->addDays(3)->toDateString(),
            'reservation_time' => '19:00',
            'guest_count'      => 2,
            'status'           => Reservation::STATUS_CONFIRMED,
            'total_amount'     => 100000,
            'paid_amount'      => 100000,
            'remaining_amount' => 0,
        ], $overrides));
    }

    public function test_on_payment_confirmed_sends_text_via_fonnte_when_no_waba(): void
    {
        $reservation = $this->makeReservation();

        $fonnte   = Mockery::mock(FonnteService::class);
        $accounts = Mockery::mock(WhatsAppAccountService::class);
        $invoices = Mockery::mock(InvoiceService::class);
        $calendar = app(GoogleCalendarService::class);

        // No active WABA account → should fall back to Fonnte.
        $accounts->shouldReceive('getActiveAccount')
            ->with($reservation->user_id)
            ->andReturn(null);

        $captured = null;
        $fonnte->shouldReceive('send')
            ->once()
            ->withArgs(function (string $phone, string $message) use (&$captured) {
                $captured = ['phone' => $phone, 'message' => $message];
                return true;
            });

        $invoices->shouldReceive('generate')->andReturn(null);

        $service = new ReservationFulfillmentService($fonnte, $accounts, $invoices, $calendar);
        $service->onPaymentConfirmed($reservation->fresh(['store', 'table', 'user']));

        $this->assertStringContainsString('81234567890', $captured['phone'] ?? '');
        $this->assertStringContainsString('Pembayaran Berhasil', $captured['message'] ?? '');
        $this->assertStringContainsString('calendar.google.com', $captured['message'] ?? '');
    }

    public function test_on_payment_confirmed_skips_invoice_when_no_pos_order_linked(): void
    {
        // When no POS order is linked (pos_order_id = null), the invoice step
        // is skipped gracefully — no exception, and Fonnte still gets the text.
        $reservation = $this->makeReservation(['pos_order_id' => null]);

        $fonnte   = Mockery::mock(FonnteService::class);
        $accounts = Mockery::mock(WhatsAppAccountService::class);
        $invoices = Mockery::mock(InvoiceService::class);
        $calendar = app(GoogleCalendarService::class);

        $accounts->shouldReceive('getActiveAccount')->andReturn(null);
        // InvoiceService::generate must NOT be called when there's no linked order.
        $invoices->shouldNotReceive('generate');
        $fonnte->shouldReceive('send')->once();

        $service = new ReservationFulfillmentService($fonnte, $accounts, $invoices, $calendar);
        $service->onPaymentConfirmed($reservation->fresh(['store', 'table', 'user']));

        $this->addToAssertionCount(1);
    }

    public function test_on_merchant_complete_sends_confirmation_with_calendar_link(): void
    {
        $reservation = $this->makeReservation();

        $fonnte   = Mockery::mock(FonnteService::class);
        $accounts = Mockery::mock(WhatsAppAccountService::class);
        $invoices = Mockery::mock(InvoiceService::class);
        $calendar = app(GoogleCalendarService::class);

        $accounts->shouldReceive('getActiveAccount')->andReturn(null);

        $captured = null;
        $fonnte->shouldReceive('send')
            ->once()
            ->withArgs(function (string $phone, string $message) use (&$captured) {
                $captured = $message;
                return true;
            });

        $service = new ReservationFulfillmentService($fonnte, $accounts, $invoices, $calendar);
        $service->onMerchantComplete($reservation->fresh(['store', 'table', 'user']));

        $this->assertStringContainsString('Dikonfirmasi', $captured ?? '');
        $this->assertStringContainsString('calendar.google.com', $captured ?? '');
    }
}
