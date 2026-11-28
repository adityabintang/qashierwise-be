<?php

use App\Jobs\DispatchDueDrips;
use App\Jobs\SendWhatsAppBroadcast;
use App\Models\WhatsAppBroadcast;
use App\Models\WhatsAppBroadcastRecipient;
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

Schedule::command('track-ctwa:process')->everyMinute()->withoutOverlapping(5);

Schedule::call(function () {
    WhatsAppBroadcastRecipient::where('status', 'sending')->where('attempted_at', '<', now()->subMinutes(5))
        ->update(['status' => 'review', 'reason' => 'send_result_unknown']);

    WhatsAppBroadcast::whereHas('recipients', fn ($rows) => $rows->where('status', 'queued'))
        ->select('id')->chunkById(100, function ($broadcasts) {
            foreach ($broadcasts as $broadcast) {
                SendWhatsAppBroadcast::dispatch($broadcast->id);
            }
        });
})->name('whatsapp-broadcast-dispatch')->everyMinute()->withoutOverlapping(5);

// Drip dispatcher — scans ai_agent_drip_schedules for due rows and queues
// SendDripJob for each. Cheap query (indexed on status + fire_at).
Schedule::job(new DispatchDueDrips)
    ->everyMinute()
    ->name('ai-agent-drip-dispatch')
    ->withoutOverlapping();
