<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CtwaAdAccount;
use App\Models\CtwaOrder;
use App\Models\CtwaRecap;
use App\Models\CtwaSetting;
use App\Models\Order;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Services\TrackCtwaMeta;
use App\Services\TrackCtwaReport;
use App\Services\TrackCtwaService;
use App\Services\WhatsAppAccountService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class TrackCtwaController extends Controller
{
    public function __construct(private TrackCtwaService $tracking, private TrackCtwaMeta $meta) {}

    public function inbox(Request $request, WhatsAppAccountService $accounts): array
    {
        $account = $accounts->getActiveAccount($request->user()->getEffectiveUserId());

        return ['enabled' => $account && $this->tracking->enabledSetting($account) !== null];
    }

    public function index(Request $request): array
    {
        [$merchant, $access] = $this->access($request->user());
        $phones = WhatsAppAccount::withoutGlobalScope('userAccounts')->where('user_id', $merchant)
            ->get(['id', 'waba_id', 'display_phone_number', 'phone_number_id', 'name', 'is_active', 'coexistence_enabled']);
        $settings = CtwaSetting::whereIn('whatsapp_account_id', $phones->modelKeys())
            ->get(['id', 'whatsapp_account_id', 'dataset_id', 'enabled_at'])->keyBy('whatsapp_account_id');

        return [
            'access' => $access,
            'phones' => $phones->map(fn ($phone) => $phone->toArray() + ['tracking' => $settings->get($phone->id)]),
            'ad_accounts' => CtwaAdAccount::where('user_id', $merchant)->orderBy('name')
                ->get(['id', 'account_id', 'name', 'currency', 'timezone']),
        ];
    }

    public function configurePhone(Request $request, int $phone): array
    {
        $this->access($request->user(), 'configure');
        $account = WhatsAppAccount::where('user_id', $request->user()->id)->findOrFail($phone);
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'dataset_id' => 'required_if:enabled,true|nullable|regex:/\A[0-9]{1,32}\z/',
            'events_token' => 'nullable|string|max:4096',
        ]);
        $setting = CtwaSetting::where('whatsapp_account_id', $account->id)->first();
        if (! $data['enabled']) {
            $setting?->update(['enabled_at' => null]);

            return ['enabled' => false];
        }

        abort_unless($account->is_active && $account->waba_id, 422, 'Hubungkan nomor WhatsApp dan WABA terlebih dahulu.');
        abort_unless(config('whatsapp.app_secret'), 422, 'Server memerlukan WhatsApp App Secret untuk memverifikasi webhook.');
        $storedToken = $data['events_token'] ?? $setting?->events_token;
        $token = $storedToken ?: $account->access_token;
        abort_unless($token, 422, 'Token untuk Conversions API belum tersedia.');
        try {
            $matches = $this->meta->datasetMatches($token, (string) $account->waba_id, $data['dataset_id']);
        } catch (ConnectionException) {
            abort(502, 'Meta tidak dapat dihubungi. Coba lagi.');
        }
        abort_unless($matches, 422, 'Dataset tidak cocok dengan WABA atau token tidak memiliki akses.');

        $now = now();
        CtwaSetting::updateOrCreate(['whatsapp_account_id' => $account->id], [
            'waba_id' => $account->waba_id,
            'dataset_id' => $data['dataset_id'],
            'events_token' => $storedToken,
            'enabled_at' => $setting?->enabled_at ?? $now,
            'first_enabled_at' => $setting?->first_enabled_at ?? $now,
        ]);

        return ['enabled' => true];
    }

    public function connectAdAccount(Request $request): array
    {
        [$merchant] = $this->access($request->user(), 'configure');
        $data = $request->validate([
            'account_id' => 'required|string|regex:/\A(?:act_)?[0-9]{1,32}\z/',
            'access_token' => 'required|string|max:4096',
        ]);
        $id = str_replace('act_', '', $data['account_id']);
        try {
            $info = $this->meta->adAccountInfo($data['access_token'], $id);
        } catch (ConnectionException) {
            abort(502, 'Meta tidak dapat dihubungi. Coba lagi.');
        }
        abort_unless($info, 422, 'Akun iklan atau akses Ads Insights tidak dapat divalidasi.');

        $account = CtwaAdAccount::updateOrCreate(['user_id' => $merchant, 'account_id' => $id], [
            'name' => $info['name'] ?? "act_$id",
            'timezone' => $info['timezone_name'],
            'currency' => $info['currency'],
            'access_token' => $data['access_token'],
        ]);

        return $account->only(['id', 'account_id', 'name', 'timezone', 'currency']);
    }

    public function adsLoginConfig(Request $request): array
    {
        $this->access($request->user(), 'configure');

        return [
            'app_id' => config('whatsapp.embedded_signup.app_id'),
            'config_id' => config('track_ctwa.ads_login_config_id') ?: config('whatsapp.embedded_signup.config_id'),
            'api_version' => config('track_ctwa.graph_version'),
        ];
    }

    public function connectAdsFromLogin(Request $request): array
    {
        [$merchant] = $this->access($request->user(), 'configure');
        $data = $request->validate(['code' => 'required|string|max:4096']);
        $appId = config('whatsapp.embedded_signup.app_id');
        $appSecret = config('whatsapp.embedded_signup.app_secret');
        $configId = config('track_ctwa.ads_login_config_id') ?: config('whatsapp.embedded_signup.config_id');
        abort_unless($appId && $appSecret && $configId, 503, 'Koneksi Meta belum dikonfigurasi.');

        $graph = 'https://graph.facebook.com/'.config('track_ctwa.graph_version');
        $exchange = Http::timeout(15)->get($graph.'/oauth/access_token', [
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'code' => $data['code'],
        ]);
        $token = $exchange->json('access_token');
        abort_unless($exchange->successful() && is_string($token) && $token !== '', 422, 'Izin Meta tidak dapat diverifikasi. Coba hubungkan lagi.');

        $permissions = Http::withToken($token)->timeout(15)->get($graph.'/me/permissions');
        $hasAdsRead = collect($permissions->json('data', []))->contains(
            fn ($permission) => ($permission['permission'] ?? null) === 'ads_read' && ($permission['status'] ?? null) === 'granted'
        );
        abort_unless($permissions->successful() && $hasAdsRead, 422, 'Konfigurasi Meta belum memberikan izin ads_read. Aktifkan produk CTWA dan izin Ads Insights di konfigurasi Embedded Signup, lalu hubungkan kembali.');

        $longLived = Http::timeout(15)->get($graph.'/oauth/access_token', [
            'grant_type' => 'fb_exchange_token',
            'client_id' => $appId,
            'client_secret' => $appSecret,
            'fb_exchange_token' => $token,
        ]);
        if ($longLived->successful() && is_string($longLived->json('access_token'))) {
            $token = $longLived->json('access_token');
        }

        $validated = [];
        $after = null;
        do {
            $accounts = Http::withToken($token)->timeout(15)->get($graph.'/me/adaccounts', array_filter([
                'fields' => 'account_id,name,timezone_name,currency',
                'limit' => 100,
                'after' => $after,
            ]));
            abort_unless($accounts->successful() && is_array($accounts->json('data')), 422, 'Daftar akun iklan tidak dapat dibaca. Periksa izin akun Meta.');
            foreach ($accounts->json('data') as $account) {
                $id = (string) ($account['account_id'] ?? '');
                if (! preg_match('/\A[0-9]{1,32}\z/', $id)) {
                    continue;
                }
                $info = $this->meta->adAccountInfo($token, $id);
                if (! $info) {
                    continue;
                }
                $validated[$id] = $info;
            }
            $after = $accounts->json('paging.next') ? $accounts->json('paging.cursors.after') : null;
        } while ($after);
        abort_if($validated === [], 422, 'Tidak ada akun iklan dengan akses Ads Insights. Periksa izin akun Meta.');

        $connected = DB::transaction(function () use ($validated, $merchant, $token) {
            $accounts = [];
            foreach ($validated as $id => $info) {
                $accounts[] = CtwaAdAccount::updateOrCreate(
                    ['user_id' => $merchant, 'account_id' => (string) $id],
                    [
                        'name' => $info['name'],
                        'timezone' => $info['timezone_name'],
                        'currency' => $info['currency'],
                        'access_token' => $token,
                    ],
                )->only(['id', 'account_id', 'name', 'currency', 'timezone']);
            }

            return $accounts;
        });

        return ['accounts' => $connected];
    }

    public function recaps(Request $request, int $phone): array
    {
        [$merchant] = $this->access($request->user(), 'review');
        $setting = $this->setting($merchant, $phone);

        return CtwaRecap::where('ctwa_setting_id', $setting->id)->whereNotNull('review_reason')->whereNull('reviewed_at')
            ->latest('sent_at')->latest('id')->paginate(25, ['id', 'contact', 'channel', 'body', 'order_number', 'total', 'review_reason', 'sent_at'])->toArray();
    }

    public function review(Request $request, int $phone, int $recap): array
    {
        [$merchant] = $this->access($request->user(), 'review');
        $setting = $this->setting($merchant, $phone);
        $data = $request->validate([
            'action' => 'required|in:resolve,dismiss',
            'order_number' => ['required_if:action,resolve', 'nullable', 'regex:'.TrackCtwaService::ORDER_NUMBER],
            'total' => 'required_if:action,resolve|nullable|numeric|decimal:0,2|between:0.01,9999999999.99',
        ]);
        $record = CtwaRecap::where('ctwa_setting_id', $setting->id)->findOrFail($recap);
        $resolve = $data['action'] === 'resolve';
        $this->tracking->review($record, $request->user()->id, $resolve ? $data['order_number'] : null, $resolve ? (string) $data['total'] : null);

        return ['success' => true];
    }

    public function orders(Request $request, int $phone): array
    {
        [$merchant, $access] = $this->access($request->user());
        abort_unless($access['review'] || $access['confirm'] || $access['report'], 403);
        $setting = $this->setting($merchant, $phone);
        $orders = CtwaOrder::where('ctwa_setting_id', $setting->id)
            ->with(['delivery:id,ctwa_order_id,status,attempts,error,accepted_at', 'click:id,source_id'])
            ->latest('first_recap_at')->latest('id')
            ->paginate(25, ['id', 'ctwa_click_id', 'pos_order_id', 'order_number', 'contact', 'recap_total', 'paid_total', 'first_recap_at', 'paid_at']);

        $posNumbers = Order::whereIn('order_number', $orders->pluck('order_number'))
            ->whereHas('store', fn ($query) => $query->where('user_id', $merchant))->pluck('order_number')->flip();

        return $orders->through(fn (CtwaOrder $order) => $order->toArray() + [
            'has_pos_reference' => $posNumbers->has($order->order_number),
        ])->toArray();
    }

    public function confirm(Request $request, int $phone, int $order): array
    {
        [$merchant] = $this->access($request->user(), 'confirm');
        $setting = $this->setting($merchant, $phone);
        abort_unless($setting->enabled_at && $setting->account->is_active, 409, 'Aktifkan TrackCTWA untuk nomor ini.');
        $data = $request->validate([
            'total' => 'required|numeric|decimal:0,2|between:0.01,9999999999.99',
            'paid_at' => 'required|date_format:Y-m-d\TH:i:sP|before_or_equal:now',
        ]);
        $tracked = CtwaOrder::where('ctwa_setting_id', $setting->id)->findOrFail($order);
        $this->tracking->confirmExternal(
            $setting, $tracked, (string) $data['total'], CarbonImmutable::parse($data['paid_at'])->utc(), $request->user()->id,
        );

        return ['success' => true];
    }

    public function report(Request $request, int $adAccount, TrackCtwaReport $report): array
    {
        [$merchant] = $this->access($request->user(), 'report');
        $account = CtwaAdAccount::where('user_id', $merchant)->findOrFail($adAccount);
        $filters = $request->validate([
            'from' => 'required|date_format:Y-m-d',
            'to' => 'required|date_format:Y-m-d|after_or_equal:from',
            'level' => 'required|in:account,campaign,adset,ad',
            'campaign_id' => 'nullable|regex:/\A[0-9]{1,32}\z/',
            'adset_id' => 'nullable|regex:/\A[0-9]{1,32}\z/',
            'ad_id' => 'nullable|regex:/\A[0-9]{1,32}\z/',
        ]);
        if (! empty($filters['ad_id'])) {
            $filters['level'] = 'ad';
        } elseif (! empty($filters['adset_id']) && in_array($filters['level'], ['account', 'campaign'], true)) {
            $filters['level'] = 'adset';
        } elseif (! empty($filters['campaign_id']) && $filters['level'] === 'account') {
            $filters['level'] = 'campaign';
        }

        return $report->build($account, $filters);
    }

    private function setting(int $merchant, int $phone): CtwaSetting
    {
        return CtwaSetting::where('whatsapp_account_id', $phone)
            ->whereHas('account', fn ($query) => $query->where('user_id', $merchant))->firstOrFail();
    }

    private function access(User $user, ?string $ability = null): array
    {
        $owner = $user->isMasterAdmin() || $user->isSuperAdmin();
        $merchant = $user->getEffectiveUserId();
        $permissions = $owner ? [] : $user->getPermissionsViaDirectQuery();
        $activeStaff = ! $owner && $user->posUsers()->where('is_active', true)
            ->whereHas('store', fn ($query) => $query->where('user_id', $merchant))->exists();
        $access = [
            'configure' => $owner,
            'review' => $owner || ($activeStaff && in_array('manage_track_ctwa', $permissions, true)),
            'confirm' => $owner || ($activeStaff && in_array('process_track_ctwa_payment', $permissions, true)),
            'report' => $owner || ($activeStaff && in_array('view_track_ctwa', $permissions, true)),
        ];
        abort_unless($ability ? Arr::get($access, $ability) : in_array(true, $access, true), 403);

        return [$merchant, $access];
    }
}
