<?php

namespace App\Jobs;

use App\Models\CtwaDelivery;
use App\Services\TrackCtwaMeta;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;

class SendCtwaPurchase implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public int $timeout = 30;

    private const RETRY_DELAYS = [60, 300, 900, 3600, 14400, 43200];

    public function __construct(public int $deliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->deliveryId;
    }

    public function handle(TrackCtwaMeta $meta): void
    {
        $delivery = CtwaDelivery::with(['order.click', 'order.setting.account'])->find($this->deliveryId);
        if (! $delivery || $delivery->status !== CtwaDelivery::PENDING || $delivery->next_attempt_at?->isFuture()) {
            return;
        }

        $claimed = CtwaDelivery::whereKey($this->deliveryId)->where('status', CtwaDelivery::PENDING)
            ->where('attempts', $delivery->attempts)->where('next_attempt_at', '<=', now())
            ->update(['next_attempt_at' => now()->addMinutes(2), 'attempts' => $delivery->attempts + 1]);
        if (! $claimed) {
            return;
        }

        $order = $delivery->order;
        $setting = $order->setting;
        $account = $setting->account;
        if ($order->paid_at->lt(now()->subDays(7))) {
            $this->failDelivery($delivery, 'Waktu pelunasan melampaui batas pengiriman Meta.');

            return;
        }
        if (! $account?->is_active || $account->waba_id !== $delivery->waba_id || ! $order->click?->click_id) {
            $this->failDelivery($delivery, 'Koneksi WhatsApp atau atribusi order sudah tidak tersedia.');

            return;
        }
        if ($delivery->attempts > count(self::RETRY_DELAYS)) {
            $this->failDelivery($delivery, 'Batas percobaan pengiriman tercapai.');

            return;
        }

        $token = $setting->events_token ?: $account->access_token;
        if (! $token) {
            $this->failDelivery($delivery, 'Token pengiriman Meta belum tersedia.');

            return;
        }

        try {
            $response = $meta->sendPurchase($token, $delivery);
        } catch (ConnectionException) {
            $this->retryDelivery($delivery, 'Koneksi ke Meta terganggu.');

            return;
        }

        if ($response->successful() && (int) $response->json('events_received') === 1) {
            $delivery->update([
                'status' => CtwaDelivery::ACCEPTED,
                'next_attempt_at' => null,
                'accepted_at' => now(),
                'error' => null,
            ]);

            return;
        }

        $code = (int) $response->json('error.code');
        $error = 'Meta tidak menerima event (HTTP '.$response->status().($code ? ", kode $code" : '').').';
        if ($response->serverError() || in_array($response->status(), [408, 429], true) || $response->json('error.is_transient') === true
            || in_array($code, [1, 2, 4, 17, 32, 341, 613, 80000, 80004], true)) {
            $this->retryDelivery($delivery, $error);
        } else {
            $this->failDelivery($delivery, $error);
        }
    }

    private function retryDelivery(CtwaDelivery $delivery, string $error): void
    {
        $delay = self::RETRY_DELAYS[$delivery->attempts] ?? null;
        if ($delay === null) {
            $this->failDelivery($delivery, $error);

            return;
        }

        $expires = $delivery->order->paid_at->addDays(7)->addSecond();
        $delivery->update(['next_attempt_at' => now()->addSeconds($delay)->min($expires), 'error' => $error]);
    }

    private function failDelivery(CtwaDelivery $delivery, string $error): void
    {
        $delivery->update(['status' => CtwaDelivery::FAILED, 'next_attempt_at' => null, 'error' => $error]);
    }
}
