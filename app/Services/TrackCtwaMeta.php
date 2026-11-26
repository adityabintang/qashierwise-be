<?php

namespace App\Services;

use App\Models\CtwaAdAccount;
use App\Models\CtwaDelivery;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TrackCtwaMeta
{
    public function datasetMatches(string $token, string $wabaId, string $datasetId): bool
    {
        $result = $this->graph($token)->get("$wabaId/dataset");
        $actual = $result->json('id') ?? $result->json('dataset_id') ?? $result->json('data.0.id') ?? $result->json('data.0.dataset_id');

        return $result->successful() && (string) $actual === $datasetId;
    }

    public function adAccountInfo(string $token, string $accountId): ?array
    {
        $response = $this->graph($token)->get("act_$accountId", ['fields' => 'account_id,name,timezone_name,currency']);
        $info = $response->json();
        if (! $response->successful() || (string) ($info['account_id'] ?? '') !== $accountId
            || ! in_array($info['timezone_name'] ?? '', timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true)
            || ! preg_match('/\A[A-Z]{3}\z/', $info['currency'] ?? '')) {
            return null;
        }

        $probe = $this->graph($token)->get("act_$accountId/insights", [
            'fields' => 'spend,purchase_roas',
            'date_preset' => 'yesterday',
            'limit' => 1,
        ]);

        return $probe->successful() ? $info : null;
    }

    public function sendPurchase(string $token, CtwaDelivery $delivery): Response
    {
        $order = $delivery->order;

        return $this->graph($token)->post("$delivery->dataset_id/events", [
            'data' => [[
                'event_name' => 'Purchase',
                'event_id' => $delivery->event_id,
                'event_time' => $order->paid_at->timestamp,
                'action_source' => 'business_messaging',
                'messaging_channel' => 'whatsapp',
                'user_data' => [
                    'whatsapp_business_account_id' => $delivery->waba_id,
                    'ctwa_clid' => $order->click->click_id,
                ],
                'custom_data' => ['value' => (float) $order->paid_total, 'currency' => 'IDR'],
            ]],
        ]);
    }

    public function insights(CtwaAdAccount $account, string $level, string $from, string $to): array
    {
        $key = "ctwa.insights.{$account->id}.{$account->updated_at->timestamp}.$level.$from.$to";

        return Cache::remember($key, now()->addMinutes(15), function () use ($account, $level, $from, $to) {
            $fields = match ($level) {
                'ad' => 'ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name',
                'adset' => 'adset_id,adset_name,campaign_id,campaign_name',
                'campaign' => 'campaign_id,campaign_name',
                'account' => 'account_id,account_name',
            };
            $params = [
                'level' => $level,
                'time_range' => json_encode(['since' => $from, 'until' => $to]),
                'use_unified_attribution_setting' => 'true',
                'fields' => "$fields,spend,purchase_roas,account_currency",
                'limit' => 500,
            ];
            $path = "act_{$account->account_id}/insights";
            $rows = [];
            do {
                $response = $this->graph($account->access_token)->get($path, $params);
                $data = $response->json('data');
                if (! $response->successful() || ! is_array($data)) {
                    throw new RuntimeException('Ads Insights tidak tersedia.');
                }
                $rows = array_merge($rows, $data);
                $next = $response->json('paging.next') ? $response->json('paging.cursors.after') : null;
                $params['after'] = $next;
            } while ($next);

            return $rows;
        });
    }

    public function adMetadata(CtwaAdAccount $account, array $ids): array
    {
        $key = "ctwa.ad_metadata.{$account->id}.{$account->updated_at->timestamp}.";
        $metadata = Cache::many(array_map(fn ($id) => $key.$id, $ids));
        $missing = array_filter($ids, fn ($id) => $metadata[$key.$id] === null);
        foreach (array_chunk($missing, 20) as $chunk) {
            $data = $this->graph($account->access_token)->get('', [
                'ids' => implode(',', $chunk),
                'fields' => 'id,name,account_id,adset_id,campaign_id,adset{name},campaign{name}',
            ])->json();
            foreach ($chunk as $id) {
                $ad = $data[$id] ?? null;
                if (! $ad || (string) ($ad['account_id'] ?? '') !== $account->account_id) {
                    continue;
                }
                $row = [
                    'ad_id' => (string) $id,
                    'ad_name' => $ad['name'] ?? null,
                    'adset_id' => $ad['adset_id'] ?? null,
                    'adset_name' => $ad['adset']['name'] ?? null,
                    'campaign_id' => $ad['campaign_id'] ?? null,
                    'campaign_name' => $ad['campaign']['name'] ?? null,
                ];
                $metadata[$key.$id] = $row;
                Cache::put($key.$id, $row, now()->addMinutes(15));
            }
        }

        return array_values(array_filter($metadata));
    }

    private function graph(string $token): PendingRequest
    {
        return Http::baseUrl('https://graph.facebook.com/'.config('track_ctwa.graph_version'))
            ->withToken($token)->acceptJson()->connectTimeout(4)->timeout(12)->withoutRedirecting();
    }
}
