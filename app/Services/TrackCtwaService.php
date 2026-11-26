<?php

namespace App\Services;

use App\Models\CtwaClick;
use App\Models\CtwaDelivery;
use App\Models\CtwaOrder;
use App\Models\CtwaRecap;
use App\Models\CtwaSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\QrisTransaction;
use App\Models\WhatsAppAccount;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TrackCtwaService
{
    public const ORDER_NUMBER = '/\A[A-Z0-9][A-Z0-9._\/-]{0,79}\z/i';

    public function captureWebhook(WhatsAppAccount $account, string $wabaId, string $field, array $value): void
    {
        if ($wabaId !== (string) $account->waba_id || ! in_array($field, ['messages', 'smb_message_echoes'], true)) {
            return;
        }

        $setting = $this->enabledSetting($account);
        if (! $setting) {
            return;
        }

        if ($field === 'messages') {
            foreach ($value['messages'] ?? [] as $message) {
                $referral = $message['referral'] ?? [];
                $clickId = trim((string) ($referral['ctwa_clid'] ?? ''));
                $sourceId = (string) ($referral['source_id'] ?? '');
                $contact = self::contact($message['from'] ?? $message['from_user_id'] ?? '');
                $timestamp = $this->webhookTime($message['timestamp'] ?? null);
                if (! $contact || empty($message['id']) || ! $timestamp || $timestamp->lt($setting->enabled_at)
                    || (! $clickId && ($referral['source_type'] ?? ($sourceId !== '' ? 'ad' : null)) !== 'ad')) {
                    continue;
                }

                $click = new CtwaClick([
                    'ctwa_setting_id' => $setting->id,
                    'contact' => $contact,
                    'message_id' => $message['id'],
                    'source_id' => preg_match('/\A[0-9]{1,32}\z/', $sourceId) ? $sourceId : null,
                    'click_id' => $clickId ?: null,
                    'click_hash' => $clickId ? hash('sha256', $clickId) : null,
                    'clicked_at' => $timestamp,
                ]);
                CtwaClick::query()->insertOrIgnore($click->getAttributes());
            }

            return;
        }

        if ($account->coexistence_enabled) {
            foreach ($value['message_echoes'] ?? [] as $message) {
                $timestamp = $this->webhookTime($message['timestamp'] ?? null);
                if (($message['type'] ?? null) === 'text' && is_string($message['text']['body'] ?? null) && $timestamp) {
                    $this->captureRecap($setting, $message['to'] ?? $message['to_user_id'] ?? '', $message['id'] ?? '', $message['text']['body'], $timestamp, 'business_app');
                }
            }
        }
    }

    public function captureInbox(WhatsAppAccount $account, string $contact, string $messageId, string $body, CarbonInterface $sentAt, bool $marked): void
    {
        $setting = $this->enabledSetting($account);
        if ($setting) {
            $this->captureRecap($setting, $contact, $messageId, $body, $sentAt, 'inbox', $marked);
        }
    }

    public function enabledSetting(WhatsAppAccount $account): ?CtwaSetting
    {
        if (! $account->is_active || ! $account->waba_id) {
            return null;
        }

        return CtwaSetting::where('whatsapp_account_id', $account->id)
            ->where('waba_id', $account->waba_id)->whereNotNull('enabled_at')->first();
    }

    private function captureRecap(CtwaSetting $setting, string $recipient, string $messageId, string $body, CarbonInterface $sentAt, string $channel, bool $marked = false): void
    {
        $contact = self::contact($recipient);
        $text = str_replace(["\r\n", "\r"], "\n", $body);
        if (! $contact || $messageId === '' || $sentAt->lt($setting->enabled_at)
            || (! $marked && ! preg_match('/^[\h*]*Rekap\h+Order[\h*:]*$/mi', $text))) {
            return;
        }

        preg_match_all('/^\h*No\.?\h+Order\h*:\h*(.*?)\h*$/mi', $text, $numbers);
        preg_match_all('/^\h*Total\h*:\h*(.*?)\h*$/mi', $text, $totals);
        $number = count($numbers[1]) === 1 && preg_match(self::ORDER_NUMBER, $numbers[1][0]) ? strtoupper($numbers[1][0]) : null;
        $total = count($totals[1]) === 1 ? $this->parseTotal($totals[1][0]) : null;

        DB::transaction(function () use ($setting, $contact, $messageId, $body, $sentAt, $channel, $number, $total) {
            $recap = CtwaRecap::firstOrCreate(
                ['ctwa_setting_id' => $setting->id, 'message_id' => $messageId],
                [
                    'contact' => $contact,
                    'channel' => $channel,
                    'body' => $body,
                    'sent_at' => $sentAt,
                    'order_number' => $number,
                    'total' => $total,
                    'review_reason' => $number && $total ? null : 'invalid_format',
                ],
            );
            if ($recap->wasRecentlyCreated && $number && $total) {
                $this->applyRecap($recap);
            }
        }, 3);
    }

    private function parseTotal(string $input): ?string
    {
        if (! preg_match('/\A(?:Rp\h*)?([0-9]+|[0-9]{1,3}(?:\.[0-9]{3})+)(?:,([0-9]{2}))?\z/i', $input, $parts)) {
            return null;
        }

        $whole = ltrim(str_replace('.', '', $parts[1]), '0') ?: '0';
        $decimal = $parts[2] ?? '00';

        return strlen($whole) <= 10 && ($whole !== '0' || $decimal !== '00') ? "$whole.$decimal" : null;
    }

    public function review(CtwaRecap $recap, int $actor, ?string $number, ?string $total): void
    {
        DB::transaction(function () use ($recap, $actor, $number, $total) {
            $recap = CtwaRecap::lockForUpdate()->findOrFail($recap->id);
            abort_unless($recap->review_reason && ! $recap->reviewed_at, 409, 'Rekap sudah ditinjau.');
            if ($number !== null) {
                $recap->fill(['order_number' => strtoupper($number), 'total' => $total]);
                $this->applyRecap($recap, true);
                abort_if($recap->review_reason, 409, 'Nomor order sudah dipakai kontak lain atau jumlah order lunas berbeda.');
            } else {
                $recap->review_reason = 'dismissed';
            }
            $recap->reviewed_at = now();
            $recap->reviewed_by = $actor;
            $recap->save();
        }, 3);
    }

    private function applyRecap(CtwaRecap $recap, bool $reviewed = false): void
    {
        $order = CtwaOrder::firstOrCreate(
            ['ctwa_setting_id' => $recap->ctwa_setting_id, 'order_number' => $recap->order_number],
            ['contact' => $recap->contact, 'recap_total' => $recap->total, 'first_recap_at' => $recap->sent_at],
        );
        $order = CtwaOrder::lockForUpdate()->findOrFail($order->id);
        $recap->ctwa_order_id = $order->id;

        if ($order->contact !== $recap->contact) {
            $recap->review_reason = 'contact_conflict';
        } elseif ($order->recap_total !== $recap->total && (! $reviewed || $order->paid_at)) {
            $recap->review_reason = 'total_conflict';
        } else {
            $recap->review_reason = null;
            if (! $order->paid_at) {
                $order->recap_total = $recap->total;
                $order->first_recap_at = $order->first_recap_at->min($recap->sent_at);
                $order->save();
            }
        }
        $recap->save();
    }

    public function confirmExternal(CtwaSetting $setting, CtwaOrder $tracked, string $total, CarbonInterface $paidAt, int $actor): void
    {
        DB::transaction(function () use ($setting, $tracked, $total, $paidAt, $actor) {
            $tracked = CtwaOrder::lockForUpdate()->findOrFail($tracked->id);
            if ($tracked->paid_at) {
                return;
            }
            abort_if($this->awaitingReview($tracked), 409, 'Tinjau rekap yang bermasalah sebelum mengonfirmasi pembayaran.');
            abort_if($tracked->pos_order_id || $this->posOrder($setting, $tracked), 409, 'Nomor order ada di POS. Periksa pelanggan dan bukti pembayaran POS.');
            abort_if(bccomp($total, $tracked->recap_total, 2) !== 0, 422, 'Nominal yang terverifikasi harus sama dengan total rekap.');
            abort_if($paidAt->lt($setting->first_enabled_at), 422, 'Waktu pelunasan mendahului aktivasi TrackCTWA.');
            $this->settle($setting, $tracked, $tracked->recap_total, $paidAt, $actor);
        }, 3);
    }

    public function reconcilePos(CtwaOrder $tracked): void
    {
        DB::transaction(function () use ($tracked) {
            $tracked = CtwaOrder::lockForUpdate()->findOrFail($tracked->id);
            $setting = $tracked->setting;
            if ($tracked->paid_at || ! $setting?->enabled_at || ! $setting->account?->is_active
                || $setting->waba_id !== $setting->account->waba_id || $this->awaitingReview($tracked)) {
                return;
            }

            $posOrder = $this->posOrder($setting, $tracked);
            if (! $posOrder || ! self::contact($posOrder->customer_phone ?? '') || self::contact($posOrder->customer_phone) !== $tracked->contact) {
                return;
            }

            if (CtwaOrder::where('pos_order_id', $posOrder->id)->whereKeyNot($tracked->id)->exists()) {
                return;
            }
            $tracked->pos_order_id = $posOrder->id;
            $tracked->save();

            $paidAt = $this->posSettlement($posOrder);
            if ($paidAt && $paidAt->gte($setting->first_enabled_at)) {
                $this->settle($setting, $tracked, $posOrder->total, $paidAt);
            }
        }, 3);
    }

    private function posOrder(CtwaSetting $setting, CtwaOrder $tracked): ?Order
    {
        return Order::where('order_number', $tracked->order_number)
            ->whereHas('store', fn ($query) => $query->where('user_id', $setting->account->user_id))
            ->lockForUpdate()->first();
    }

    private function posSettlement(Order $order): ?CarbonInterface
    {
        if (! in_array($order->status, [Order::STATUS_PAID, 'completed'], true) || bccomp($order->total, '0', 2) <= 0) {
            return null;
        }

        $payments = $order->payments()->where('status', Payment::STATUS_PAID)->whereNotNull('paid_at')->with('qrisTransaction')->get();
        $entries = $payments->map(fn (Payment $payment) => [
            'amount' => $payment->amount,
            'paid_at' => $payment->qrisTransaction?->paid_at ?? $payment->paid_at,
        ]);
        $standaloneQris = QrisTransaction::where('linked_order_id', $order->id)
            ->where('status', QrisTransaction::STATUS_SETTLEMENT)->whereNotNull('paid_at')->whereDoesntHave('payment')->get();
        foreach ($standaloneQris as $transaction) {
            $entries->push(['amount' => $transaction->amount, 'paid_at' => $transaction->paid_at]);
        }

        $paid = '0';
        foreach ($entries->sortBy('paid_at') as $entry) {
            if (bccomp($entry['amount'], '0', 2) <= 0) {
                continue;
            }
            $paid = bcadd($paid, $entry['amount'], 2);
            if (bccomp($paid, $order->total, 2) >= 0) {
                return $entry['paid_at'];
            }
        }

        return null;
    }

    private function awaitingReview(CtwaOrder $order): bool
    {
        return CtwaRecap::where('ctwa_setting_id', $order->ctwa_setting_id)->where('order_number', $order->order_number)
            ->whereNotNull('review_reason')->whereNull('reviewed_at')->exists();
    }

    private function settle(CtwaSetting $setting, CtwaOrder $order, string $amount, CarbonInterface $paidAt, ?int $actor = null): void
    {
        $click = CtwaClick::where('ctwa_setting_id', $setting->id)->where('contact', $order->contact)
            ->whereBetween('clicked_at', [$order->first_recap_at->subDays(7), $order->first_recap_at])
            ->latest('clicked_at')->latest('id')->first();
        $attributed = $click?->click_id && $click->clicked_at->lte($paidAt);

        $order->update([
            'ctwa_click_id' => $click?->id,
            'paid_total' => $amount,
            'paid_at' => $paidAt,
            'confirmed_by' => $actor,
        ]);
        $order->delivery()->create([
            'event_id' => (string) Str::uuid(),
            'dataset_id' => $setting->dataset_id,
            'waba_id' => $setting->waba_id,
            'status' => $attributed ? CtwaDelivery::PENDING : CtwaDelivery::UNATTRIBUTED,
            'next_attempt_at' => $attributed ? now() : null,
        ]);
    }

    private function webhookTime(mixed $timestamp): ?CarbonImmutable
    {
        return ctype_digit((string) $timestamp) && (int) $timestamp <= now()->addMinutes(5)->timestamp
            ? CarbonImmutable::createFromTimestamp((int) $timestamp, 'UTC') : null;
    }

    private static function contact(string $value): string
    {
        $value = trim($value);
        if (preg_match('/\A[+0-9() .-]+\z/', $value)) {
            $value = preg_replace('/[^0-9]/', '', $value);
            if (strlen($value) > 20) {
                return '';
            }

            return str_starts_with($value, '0') ? '62'.substr($value, 1) : $value;
        }

        return preg_match('/\A[A-Za-z]{2}\.[A-Za-z0-9._-]{1,76}\z/', $value) ? $value : '';
    }
}
