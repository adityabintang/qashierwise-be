<?php

namespace App\Services;

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcastRecipient;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class WhatsAppBroadcastQuota
{
    public function __construct(private WhatsAppBroadcastMeta $meta) {}

    public function register(WhatsAppAccount $account, string $portfolio): void
    {
        $wabas = array_unique([$this->meta->wabaId($account), ...$this->meta->ownedWabas($account, $portfolio)]);
        $rows = array_map(fn ($id) => ['id' => $id, 'portfolio_id' => $portfolio], $wabas);
        DB::table('whatsapp_broadcast_wabas')->upsert($rows, ['id'], ['portfolio_id']);
    }

    public function lock(string $portfolio, Closure $callback): mixed
    {
        return DB::transaction(function () use ($portfolio, $callback) {
            DB::table('whatsapp_broadcast_portfolios')->insertOrIgnore(['id' => $portfolio]);
            DB::table('whatsapp_broadcast_portfolios')->where('id', $portfolio)->lockForUpdate()->first();

            return $callback();
        }, 5);
    }

    public function usage(string $portfolio): int
    {
        $cutoff = now()->subHours(24);
        $wabas = DB::table('whatsapp_broadcast_wabas')->where('portfolio_id', $portfolio)->select('id');
        $senders = DB::table('whatsapp_accounts')
            ->whereIn(DB::raw("COALESCE(NULLIF(waba_id, ''), business_account_id)"), $wabas)
            ->select('phone_number_id');

        $windowStart = match (DB::connection()->getDriverName()) {
            'sqlite' => "datetime(outgoing.sent_at, '-24 hours')",
            'pgsql' => "outgoing.sent_at - interval '24 hours'",
            'sqlsrv' => 'DATEADD(hour, -24, outgoing.sent_at)',
            default => 'DATE_SUB(outgoing.sent_at, INTERVAL 24 HOUR)',
        };

        $sent = DB::table('whatsapp_messages as outgoing')
            ->join('whatsapp_contacts as contact', 'contact.id', '=', 'outgoing.contact_id')
            ->whereIn('outgoing.phone_number_id', $senders)
            ->where('outgoing.direction', 'outgoing')
            ->where('outgoing.type', 'template')
            ->whereIn('outgoing.status', ['sent', 'delivered', 'read'])
            ->where('outgoing.sent_at', '>=', $cutoff)
            ->whereNotExists(fn (Builder $incoming) => $incoming->selectRaw('1')
                ->from('whatsapp_messages as incoming')
                ->whereColumn('incoming.contact_id', 'outgoing.contact_id')
                ->whereColumn('incoming.phone_number_id', 'outgoing.phone_number_id')
                ->where('incoming.direction', 'incoming')
                ->whereColumn('incoming.sent_at', '<=', 'outgoing.sent_at')
                ->where('incoming.sent_at', '>', DB::raw($windowStart)))
            ->selectRaw("REPLACE(contact.wa_id, '+', '') as wa_id");

        $reserved = WhatsAppBroadcastRecipient::query()
            ->whereHas('broadcast', fn ($broadcast) => $broadcast->where('portfolio_id', $portfolio))
            ->where(function ($rows) use ($cutoff) {
                $rows->whereIn('status', ['queued', 'sending'])
                    ->orWhere(fn ($review) => $review->where('status', 'review')->where('attempted_at', '>=', $cutoff))
                    ->orWhere(fn ($missingHistory) => $missingHistory
                        ->whereIn('status', ['accepted', 'delivered', 'read'])->where('accepted_at', '>=', $cutoff)
                        ->whereNotExists(fn ($messages) => $messages->selectRaw('1')->from('whatsapp_messages')
                            ->whereColumn('whatsapp_messages.message_id', 'whatsapp_broadcast_recipients.message_id')));
            })
            ->select('wa_id')->toBase();

        return DB::query()->fromSub($sent->union($reserved), 'usage')->count();
    }
}
