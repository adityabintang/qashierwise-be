<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the cleanup command to run daily at midnight (WIB timezone)
Schedule::command('reservations:cleanup-expired-slots')
    ->dailyAt('00:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/reservation-cleanup.log'));

Schedule::command('blog:publish-scheduled')
    ->everyMinute()
    ->withoutOverlapping();

// Drip dispatcher — scans ai_agent_drip_schedules for due rows and queues
// SendDripJob for each. Cheap query (indexed on status + fire_at).
Schedule::job(new \App\Jobs\DispatchDueDrips)
    ->everyMinute()
    ->name('ai-agent-drip-dispatch')
    ->withoutOverlapping();
