<?php

namespace App\Services;

use App\Models\CtwaAdAccount;
use App\Models\CtwaClick;
use App\Models\CtwaDelivery;
use App\Models\CtwaSetting;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TrackCtwaReport
{
    public function __construct(private TrackCtwaMeta $meta) {}

    public function build(CtwaAdAccount $account, array $filters): array
    {
        ['level' => $level, 'from' => $from, 'to' => $to] = $filters;
        $start = CarbonImmutable::parse($from, $account->timezone)->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, $account->timezone)->addDay()->startOfDay()->utc();
        $settings = CtwaSetting::whereHas('account', fn ($query) => $query->where('user_id', $account->user_id));
        $activatedAt = $settings->min('first_enabled_at');
        $hasCoverage = $activatedAt && $start->gte(CarbonImmutable::parse($activatedAt));
        $clicks = CtwaClick::whereIn('ctwa_setting_id', $settings->select('id'))
            ->where('clicked_at', '>=', $start)->where('clicked_at', '<', $end)->whereNotNull('source_id');
        $chats = (clone $clicks)->selectRaw('source_id, count(*) as chats')->groupBy('source_id')->pluck('chats', 'source_id');
        $sales = DB::query()->fromSub($clicks->select(['id', 'source_id']), 'clicks')
            ->join('ctwa_orders as orders', 'orders.ctwa_click_id', '=', 'clicks.id')
            ->join('ctwa_deliveries as deliveries', 'deliveries.ctwa_order_id', '=', 'orders.id')
            ->where('deliveries.status', '!=', CtwaDelivery::UNATTRIBUTED)
            ->selectRaw('clicks.source_id, deliveries.status, count(*) as paid_orders, sum(orders.paid_total) as revenue')
            ->groupBy('clicks.source_id', 'deliveries.status')->get()->groupBy('source_id');

        $available = true;
        try {
            $ads = collect($this->meta->insights($account, 'ad', $from, $to))->keyBy('ad_id');
        } catch (ConnectionException|RuntimeException) {
            $available = false;
            $ads = collect();
        }

        $missing = $chats->keys()->diff($ads->keys())->values()->all();
        if ($missing) {
            try {
                foreach ($this->meta->adMetadata($account, $missing) as $row) {
                    $ads->put($row['ad_id'], $row);
                }
            } catch (ConnectionException) {
                $available = false;
            }
        }

        $insights = $ads;
        if ($level !== 'ad') {
            try {
                $insights = collect($this->meta->insights($account, $level, $from, $to))->keyBy($level.'_id');
            } catch (ConnectionException|RuntimeException) {
                $available = false;
                $insights = collect();
            }
        }

        $groups = $this->filter($ads, $filters)
            ->map(fn ($ad) => $ad + ['account_id' => $account->account_id, 'account_name' => $account->name])
            ->filter(fn ($ad) => ! empty($ad[$level.'_id']))->groupBy($level.'_id');
        $insights = $this->filter($insights, $filters);
        $rows = $groups->keys()->merge($insights->keys())->unique()->map(function ($id) use ($groups, $insights, $chats, $sales, $level, $account, $available, $hasCoverage) {
            $items = $groups->get($id, collect());
            $insight = $insights->get($id, []);
            $row = $insight + ($items->first() ?? []);
            $count = 0;
            $revenue = '0.00';
            $statuses = array_fill_keys([CtwaDelivery::PENDING, CtwaDelivery::ACCEPTED, CtwaDelivery::FAILED], 0);
            foreach ($items as $ad) {
                $count += $chats->get($ad['ad_id'], 0);
                foreach ($sales->get($ad['ad_id'], []) as $sale) {
                    $statuses[$sale->status] += $sale->paid_orders;
                    $revenue = bcadd($revenue, (string) $sale->revenue, 2);
                }
            }
            $spend = isset($insight['spend']) ? (float) $insight['spend'] : null;
            $currency = $insight['account_currency'] ?? $account->currency;
            $metaRoas = collect($insight['purchase_roas'] ?? [])->keyBy('action_type');
            $metaValue = $metaRoas->get('omni_purchase')['value'] ?? $metaRoas->get('purchase')['value'] ?? null;

            return [
                'account_id' => $account->account_id,
                'account_name' => $account->name,
                'campaign_id' => $level !== 'account' ? ($row['campaign_id'] ?? null) : null,
                'campaign_name' => $level !== 'account' ? ($row['campaign_name'] ?? null) : null,
                'adset_id' => in_array($level, ['adset', 'ad'], true) ? ($row['adset_id'] ?? null) : null,
                'adset_name' => in_array($level, ['adset', 'ad'], true) ? ($row['adset_name'] ?? null) : null,
                'ad_id' => $level === 'ad' ? ($row['ad_id'] ?? null) : null,
                'ad_name' => $level === 'ad' ? ($row['ad_name'] ?? null) : null,
                'chats' => $count,
                'paid_orders' => array_sum($statuses),
                'revenue' => $revenue,
                'spend' => $spend,
                'currency' => $currency,
                'qashierwise_roas' => $available && $hasCoverage && $currency === 'IDR' && $spend > 0 ? (float) $revenue / $spend : null,
                'meta_roas' => is_numeric($metaValue) ? (float) $metaValue : null,
                'delivery' => $statuses,
            ];
        })->sortByDesc(fn ($row) => $row['spend'] ?? -1)->values();

        return [
            'rows' => $rows,
            'insights_available' => $available,
            'has_tracking_coverage' => (bool) $hasCoverage,
            'tracking_started_at' => $activatedAt,
            'timezone' => $account->timezone,
        ];
    }

    private function filter(Collection $rows, array $filters): Collection
    {
        return $rows->filter(function ($row) use ($filters) {
            foreach (['campaign_id', 'adset_id', 'ad_id'] as $field) {
                if (! empty($filters[$field]) && (string) ($row[$field] ?? '') !== $filters[$field]) {
                    return false;
                }
            }

            return true;
        });
    }
}
