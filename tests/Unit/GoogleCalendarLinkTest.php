<?php

namespace Tests\Unit;

use App\Models\Reservation;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCalendarLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_a_no_oauth_add_to_calendar_url(): void
    {
        $reservation = Reservation::factory()->create([
            'customer_name' => 'Budi Santoso',
            'reservation_date' => '2026-07-01',
            'reservation_time' => '19:30',
        ]);

        $url = app(GoogleCalendarService::class)->buildAddToCalendarUrl($reservation->fresh(['store']));

        $this->assertStringStartsWith('https://calendar.google.com/calendar/render?', $url);

        parse_str(parse_url($url, PHP_URL_QUERY), $params);

        $this->assertSame('TEMPLATE', $params['action']);
        $this->assertStringContainsString('Budi Santoso', $params['text']);
        // dates = START/END, both compact ISO (Ymd\THis), 2h apart.
        $this->assertMatchesRegularExpression('/^\d{8}T\d{6}\/\d{8}T\d{6}$/', $params['dates']);
        [$start, $end] = explode('/', $params['dates']);
        $this->assertSame('20260701T193000', $start);
        $this->assertSame('20260701T213000', $end);
        $this->assertArrayHasKey('ctz', $params);
        $this->assertArrayHasKey('details', $params);
    }
}
