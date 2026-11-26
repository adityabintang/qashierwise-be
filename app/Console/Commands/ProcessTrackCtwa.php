<?php

namespace App\Console\Commands;

use App\Jobs\SendCtwaPurchase;
use App\Models\CtwaDelivery;
use App\Models\CtwaOrder;
use App\Models\Order;
use App\Services\TrackCtwaService;
use Illuminate\Console\Command;

class ProcessTrackCtwa extends Command
{
    protected $signature = 'track-ctwa:process';

    protected $description = 'Reconcile WhatsApp recap payments and queue due Meta Purchase events';

    public function handle(TrackCtwaService $trackCtwa): int
    {
        CtwaOrder::whereNull('paid_at')->whereHas('setting', fn ($query) => $query->whereNotNull('enabled_at'))
            ->whereIn('order_number', Order::select('order_number'))
            ->chunkById(100, function ($orders) use ($trackCtwa) {
                foreach ($orders as $order) {
                    rescue(fn () => $trackCtwa->reconcilePos($order));
                }
            });

        CtwaDelivery::where('status', CtwaDelivery::PENDING)->where('next_attempt_at', '<=', now())
            ->chunkById(100, function ($deliveries) {
                foreach ($deliveries as $delivery) {
                    SendCtwaPurchase::dispatch($delivery->id);
                }
            });

        return self::SUCCESS;
    }
}
