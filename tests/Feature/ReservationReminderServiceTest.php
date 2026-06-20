<?php

namespace Tests\Feature;

use App\Services\ReservationReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReservationReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_reminders_uses_reservation_date_and_time_fields_not_null(): void
    {
        $reservation = \App\Models\Reservation::factory()->create([
            'reservation_date' => now()->addDay(),
            'reservation_time' => '19:30',
        ]);

        // Create config with reminder enabled and fully configured
        \App\Models\ReservationConfig::factory()
            ->withReminders()
            ->create([
                'user_id' => $reservation->user_id,
                'store_id' => $reservation->store_id,
            ]);

        // Log all INFO entries
        Log::shouldReceive('info')
            ->andReturn(null);

        Log::shouldReceive('warning')
            ->andReturn(null);

        Log::shouldReceive('error')
            ->andReturn(null);

        // Mock and capture the QstashSchedulerService call to verify correct datetime is passed
        $qstashCalled = false;
        $capturedDateTime = null;

        $qstash = $this->mock(\App\Services\QstashSchedulerService::class)
            ->shouldReceive('scheduleMultiple')
            ->andReturnUsing(function ($resId, $dateTime, $timings) use (&$qstashCalled, &$capturedDateTime) {
                $qstashCalled = true;
                $capturedDateTime = $dateTime;

                return [];
            })
            ->getMock();

        $service = app(ReservationReminderService::class);
        $service->scheduleReminders($reservation);

        // Verify that QstashSchedulerService was called (reminder was NOT skipped as past)
        $this->assertTrue($qstashCalled, 'Reminder should be scheduled (not skipped as past)');

        // Verify that the datetime passed is NOT empty/blank
        $this->assertNotEmpty($capturedDateTime, 'DateTime passed to scheduler should not be blank');

        // Verify that the datetime contains both date and time components
        $this->assertStringContainsString($reservation->reservation_date->toDateString(), $capturedDateTime);
        $this->assertStringContainsString('19:30', $capturedDateTime);
    }
}
