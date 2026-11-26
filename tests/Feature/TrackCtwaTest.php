<?php

namespace Tests\Feature;

use App\Events\NewWhatsAppMessage;
use App\Models\CtwaAdAccount;
use App\Models\CtwaDelivery;
use App\Models\CtwaOrder;
use App\Models\CtwaRecap;
use App\Models\CtwaSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosUser;
use App\Models\Store;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\PaymentService;
use App\Services\TrackCtwaService;
use App\Services\WebhookForwardingService;
use App\Services\WhatsAppAccountService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Netflie\WhatsAppCloudApi\Response;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TrackCtwaTest extends TestCase
{
    private User $owner;

    private WhatsAppAccount $account;

    private CtwaSetting $setting;

    public function test_ads_login_uses_existing_embedded_signup_config(): void
    {
        config([
            'whatsapp.embedded_signup.app_id' => 'test-app',
            'whatsapp.embedded_signup.config_id' => 'existing-config',
            'track_ctwa.ads_login_config_id' => null,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson('/api/track-ctwa/ads-login/config')
            ->assertOk()
            ->assertJsonPath('config_id', 'existing-config');
    }

    public function test_ads_login_connects_available_ad_account_without_manual_token(): void
    {
        config([
            'whatsapp.embedded_signup.app_id' => 'test-app',
            'whatsapp.embedded_signup.app_secret' => 'test-secret',
            'track_ctwa.ads_login_config_id' => 'test-ads-config',
        ]);
        Http::fake([
            'https://graph.facebook.com/v27.0/oauth/access_token*' => Http::response(['access_token' => 'test-token']),
            'https://graph.facebook.com/v27.0/me/permissions*' => Http::response([
                'data' => [['permission' => 'ads_read', 'status' => 'granted']],
            ]),
            'https://graph.facebook.com/v27.0/me/adaccounts*' => Http::response([
                'data' => [['account_id' => '123', 'name' => 'Demo Ads', 'timezone_name' => 'Asia/Jakarta', 'currency' => 'IDR']],
            ]),
            'https://graph.facebook.com/v27.0/act_123/insights*' => Http::response(['data' => []]),
            'https://graph.facebook.com/v27.0/act_123*' => Http::response([
                'account_id' => '123', 'name' => 'Demo Ads', 'timezone_name' => 'Asia/Jakarta', 'currency' => 'IDR',
            ]),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/track-ctwa/ads-login/callback', ['code' => 'test-code'])
            ->assertOk()->assertJsonPath('accounts.0.account_id', '123');

        $this->assertDatabaseHas('ctwa_ad_accounts', [
            'user_id' => $this->owner->id,
            'account_id' => '123',
        ]);
    }

    public function test_ads_login_rejects_missing_ads_read_permission(): void
    {
        config([
            'whatsapp.embedded_signup.app_id' => 'test-app',
            'whatsapp.embedded_signup.app_secret' => 'test-secret',
            'track_ctwa.ads_login_config_id' => 'test-ads-config',
        ]);
        Http::fake([
            'https://graph.facebook.com/v27.0/oauth/access_token*' => Http::response(['access_token' => 'test-token']),
            'https://graph.facebook.com/v27.0/me/permissions*' => Http::response([
                'data' => [['permission' => 'ads_read', 'status' => 'declined']],
            ]),
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->postJson('/api/track-ctwa/ads-login/callback', ['code' => 'test-code'])
            ->assertStatus(422);
        $this->assertDatabaseCount('ctwa_ad_accounts', 0);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => [
            'database/migrations/0001_01_01_000000_create_users_table.php',
            'database/migrations/2024_01_28_052900_create_permission_tables.php',
            'database/migrations/2024_11_15_042414_create_whatsapp_accounts_table.php',
            'database/migrations/2024_11_15_042421_create_whatsapp_contacts_table.php',
            'database/migrations/2024_11_15_042421_create_whatsapp_messages_table.php',
            'database/migrations/2024_12_04_000002_create_stores_table.php',
            'database/migrations/2024_12_04_000003_create_pos_users_table.php',
            'database/migrations/2024_12_04_000004_create_tables_table.php',
            'database/migrations/2024_12_04_000006_create_products_table.php',
            'database/migrations/2024_12_04_000007_create_orders_table.php',
            'database/migrations/2024_12_04_000008_create_order_items_table.php',
            'database/migrations/2024_12_04_000009_create_payments_table.php',
            'database/migrations/2024_12_20_045216_create_ai_agents_table.php',
            'database/migrations/2024_12_20_045234_create_ai_agent_conversations_table.php',
            'database/migrations/2024_12_22_000001_create_qris_transactions_table.php',
            'database/migrations/2025_01_07_074337_add_phone_number_id_to_whatsapp_tables.php',
            'database/migrations/2025_01_27_140612_add_is_master_admin_to_users_table.php',
            'database/migrations/2025_01_29_000001_add_user_id_and_unique_constraint_to_stores.php',
            'database/migrations/2025_11_23_093114_add_is_read_to_whatsapp_messages_table.php',
            'database/migrations/2025_12_15_000001_add_embedded_signup_fields_to_whatsapp_accounts_table.php',
            'database/migrations/2025_12_25_113317_add_order_id_to_qris_transactions_table.php',
            'database/migrations/2025_12_25_113407_add_source_and_customer_fields_to_orders_table.php',
            'database/migrations/2025_12_25_113939_add_qris_fields_to_payments_table.php',
            'database/migrations/2025_12_30_011728_add_reference_id_and_paid_at_to_qris_transactions_table.php',
            'database/migrations/2026_06_06_000000_create_track_ctwa_tables.php',
        ]])->assertSuccessful();

        config(['whatsapp.app_secret' => 'test-signing-secret']);
        Event::fake([NewWhatsAppMessage::class]);
        Http::preventStrayRequests();
        $this->travelTo(now()->setDate(2026, 6, 9)->setTime(9, 0));
        $this->owner = User::factory()->create(['is_master_admin' => true]);
        $this->account = WhatsAppAccount::factory()->for($this->owner)->create([
            'waba_id' => '321',
            'coexistence_enabled' => true,
        ]);
        $this->setting = CtwaSetting::create([
            'whatsapp_account_id' => $this->account->id,
            'waba_id' => '321',
            'dataset_id' => '456',
            'events_token' => 'test-events-token',
            'enabled_at' => now()->subDays(10),
            'first_enabled_at' => now()->subDays(10),
        ]);
    }

    public function test_signed_echoes_and_inbox_send_share_one_order_and_purchase(): void
    {
        $this->click('wamid.ad', 'first-click', '111', now()->subHours(4)->timestamp);
        $this->mock(WebhookForwardingService::class)->expects('dispatch')->once();
        $incoming = [
            'id' => 'wamid.latest', 'from' => '628123456789', 'type' => 'text', 'timestamp' => now()->subHours(2)->timestamp,
            'text' => ['body' => 'Saya mau pesan'],
            'referral' => ['source_id' => '222', 'source_type' => 'ad', 'ctwa_clid' => 'last-click'],
        ];
        $this->postWebhook('messages', 'messages', $incoming, false)->assertForbidden();
        $this->assertDatabaseCount('ctwa_clicks', 1);
        $this->postWebhook('messages', 'messages', $incoming)->assertOk();
        $this->postWebhook('messages', 'messages', $incoming)->assertOk();
        $this->assertDatabaseCount('ctwa_clicks', 2);
        $body = "Rekap Order\r\nNo. Order: INV-123\r\nTotal: Rp150.000";
        $echo = [
            'id' => 'wamid.echo',
            'timestamp' => now()->subHour()->timestamp,
            'type' => 'text',
            'to' => '628123456789',
            'text' => ['body' => $body],
        ];
        $this->postWebhook('smb_message_echoes', 'message_echoes', $echo, false)->assertForbidden();
        $this->assertDatabaseCount('ctwa_recaps', 0);
        $this->postWebhook('smb_message_echoes', 'message_echoes', $echo)->assertOk();
        $this->postWebhook('smb_message_echoes', 'message_echoes', $echo)->assertOk();

        $inboxBody = "No. Order: INV-123\r\nTotal: Rp150.000";
        $response = $this->mock(Response::class);
        $response->expects('decodedBody')->andReturn(['messages' => [['id' => 'wamid.inbox']]])->atLeast()->once();
        $client = $this->mock(WhatsAppCloudApi::class);
        $client->expects('sendTextMessage')->with('628123456789', $inboxBody)->andReturn($response);
        $this->partialMock(WhatsAppAccountService::class)->expects('getClientForUser')->with($this->owner->id)->andReturn($client);
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/whatsapp/send/text', [
            'to' => '628123456789', 'message' => $inboxBody, 'track_ctwa_recap' => true,
        ])->assertOk()->assertJsonPath('track_ctwa_warning', null);
        $this->assertDatabaseCount('ctwa_orders', 1);
        $this->assertDatabaseCount('ctwa_recaps', 2);
        $this->assertDatabaseCount('ctwa_deliveries', 0);

        $order = CtwaOrder::sole();
        $path = "/api/track-ctwa/phones/{$this->account->id}/orders/{$order->id}/confirm";
        $paidAt = now()->subMinutes(30);
        $this->postJson($path, ['total' => 75000, 'paid_at' => $paidAt->toIso8601String()])->assertUnprocessable();
        $this->postJson($path, ['total' => 150000, 'paid_at' => $paidAt->toIso8601String()])->assertOk();
        $this->postJson($path, ['total' => 150000, 'paid_at' => $paidAt->toIso8601String()])->assertOk();
        $this->assertDatabaseCount('ctwa_deliveries', 1);
        $eventId = CtwaDelivery::sole()->event_id;

        Http::fake(['https://graph.facebook.com/*/456/events' => Http::sequence()->pushStatus(503)->push(['events_received' => 1])]);
        $this->artisan('track-ctwa:process')->assertSuccessful();
        $this->assertSame(CtwaDelivery::PENDING, CtwaDelivery::sole()->status);
        $this->artisan('track-ctwa:process')->assertSuccessful();
        Http::assertSentCount(1);
        $this->travel(1)->minutes();
        $this->artisan('track-ctwa:process')->assertSuccessful();
        $this->artisan('track-ctwa:process')->assertSuccessful();
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertSame([
                'event_name' => 'Purchase',
                'event_id' => $eventId,
                'event_time' => $paidAt->timestamp,
                'action_source' => 'business_messaging',
                'messaging_channel' => 'whatsapp',
                'user_data' => ['whatsapp_business_account_id' => '321', 'ctwa_clid' => 'last-click'],
                'custom_data' => ['value' => 150000.0, 'currency' => 'IDR'],
            ], $request['data'][0]);
        }
        $this->assertSame(CtwaDelivery::ACCEPTED, CtwaDelivery::sole()->status);
    }

    public function test_unsigned_message_webhooks_are_saved_without_ctwa_attribution(): void
    {
        $this->mock(WebhookForwardingService::class)->expects('dispatch')->once();

        $this->postWebhook('messages', 'messages', [
            'id' => 'wamid.unsigned-in', 'from' => '628123456789', 'type' => 'text', 'timestamp' => now()->timestamp,
            'text' => ['body' => 'Saya mau pesan'],
            'referral' => ['source_id' => '222', 'source_type' => 'ad', 'ctwa_clid' => 'unsigned-click'],
        ], null)->assertOk();
        $this->postWebhook('smb_message_echoes', 'message_echoes', [
            'id' => 'wamid.unsigned-out', 'to' => '628123456789', 'type' => 'text', 'timestamp' => now()->timestamp,
            'text' => ['body' => "Rekap Order\nNo. Order: INV-123\nTotal: Rp150.000"],
        ], null)->assertOk();

        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'wamid.unsigned-in', 'direction' => 'incoming']);
        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'wamid.unsigned-out', 'direction' => 'outgoing']);
        $this->assertDatabaseCount('ctwa_clicks', 0);
        $this->assertDatabaseCount('ctwa_recaps', 0);
    }

    public function test_message_history_and_status_survive_unavailable_ctwa_storage(): void
    {
        Schema::drop('ctwa_settings');
        $this->mock(WebhookForwardingService::class)->expects('dispatch')->once();

        $this->postWebhook('messages', 'messages', [
            'id' => 'wamid.incoming', 'from' => '628123456789', 'type' => 'text', 'timestamp' => now()->timestamp,
            'text' => ['body' => 'Saya mau pesan'],
            'referral' => ['source_id' => '222', 'source_type' => 'ad', 'ctwa_clid' => 'latest-click'],
        ])->assertOk();
        $recap = "Rekap Order\nNo. Order: INV-123\nTotal: Rp150.000";
        $this->postWebhook('smb_message_echoes', 'message_echoes', [
            'id' => 'wamid.echo', 'to' => '628123456789', 'type' => 'text', 'timestamp' => now()->timestamp,
            'text' => ['body' => $recap],
        ])->assertOk();
        $this->postWebhook('messages', 'statuses', [
            'id' => 'wamid.echo', 'status' => 'delivered', 'timestamp' => now()->timestamp,
        ])->assertOk();

        $response = $this->mock(Response::class);
        $response->expects('decodedBody')->andReturn(['messages' => [['id' => 'wamid.inbox']]])->atLeast()->once();
        $client = $this->mock(WhatsAppCloudApi::class);
        $client->expects('sendTextMessage')->with('628123456789', $recap)->andReturn($response);
        $this->partialMock(WhatsAppAccountService::class)->expects('getClientForUser')->with($this->owner->id)->andReturn($client);
        $this->actingAs($this->owner, 'sanctum')->postJson('/api/whatsapp/send/text', [
            'to' => '628123456789', 'message' => $recap, 'track_ctwa_recap' => true,
        ])->assertOk()->assertJsonPath('saved_message.direction', 'outgoing')
            ->assertJsonPath('track_ctwa_warning', 'Pesan terkirim. Rekap gagal dicatat di TrackCTWA.');

        $contact = WhatsAppContact::sole();
        $this->getJson("/api/whatsapp/contacts/{$contact->id}/messages")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.message_id', 'wamid.incoming')
            ->assertJsonPath('data.0.direction', 'incoming')
            ->assertJsonPath('data.1.message_id', 'wamid.echo')
            ->assertJsonPath('data.1.status', 'delivered')
            ->assertJsonPath('data.2.message_id', 'wamid.inbox');
    }

    public function test_review_and_last_click_without_click_id_do_not_send_a_purchase(): void
    {
        $this->click('wamid.older', 'old-click', '111', now()->subDays(2)->timestamp);
        $this->click('wamid.no-id', null, '222', now()->subDay()->timestamp);
        $this->click('wamid.no-id', null, '222', now()->subDay()->timestamp);
        $this->assertDatabaseCount('ctwa_clicks', 2);
        $service = app(TrackCtwaService::class);
        $service->captureInbox($this->account, '628123456789', 'wamid.ambiguous', "No. Order: EXT-1\nTotal: Rp75.000\nTotal: Rp100.000", now(), true);
        $service->captureInbox($this->account, '628999999999', 'wamid.organic', "Rekap Order\nNo. Order: EXT-2\nTotal: Rp50.000", now(), false);
        $this->assertDatabaseCount('ctwa_orders', 1);
        $recap = CtwaRecap::whereNotNull('review_reason')->sole();
        $path = "/api/track-ctwa/phones/{$this->account->id}";
        $this->actingAs($this->owner, 'sanctum')->patchJson("$path/recaps/{$recap->id}", [
            'action' => 'resolve', 'order_number' => 'EXT-1', 'total' => 75000,
        ])->assertOk();

        foreach (CtwaOrder::all() as $order) {
            $this->postJson("$path/orders/{$order->id}/confirm", [
                'total' => $order->recap_total, 'paid_at' => now()->toIso8601String(),
            ])->assertOk();
        }
        $this->artisan('track-ctwa:process')->assertSuccessful();
        Http::assertNothingSent();
        $this->assertSame(2, CtwaDelivery::where('status', CtwaDelivery::UNATTRIBUTED)->count());
    }

    public function test_pos_requires_settled_amount_and_uses_final_total_and_payment_time(): void
    {
        $posOrder = $this->posOrder('POS-1');
        $this->click('wamid.ad', 'pos-click', '111', now()->subDay()->timestamp);
        app(TrackCtwaService::class)->captureInbox($this->account, '628123456789', 'wamid.recap', "Rekap Order\nNo. Order: POS-1\nTotal: Rp125.000", now(), false);
        $first = app(PaymentService::class)->processPayment($posOrder, ['amount' => 60000], true);
        $this->assertSame(Payment::STATUS_PAID, $first->status);
        $this->assertSame(Order::STATUS_PENDING, $posOrder->refresh()->status);

        $posOrder->update(['status' => Order::STATUS_PAID]);
        $this->artisan('track-ctwa:process')->assertSuccessful();
        $this->assertDatabaseCount('ctwa_deliveries', 0);
        $tracked = CtwaOrder::sole();
        $this->assertSame($posOrder->id, $tracked->pos_order_id);
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/track-ctwa/phones/{$this->account->id}/orders/{$tracked->id}/confirm", [
                'total' => 125000, 'paid_at' => now()->toIso8601String(),
            ])->assertConflict();
        $this->travel(3)->minutes();
        $settledAt = now();
        $posOrder->update(['status' => Order::STATUS_PENDING]);
        app(PaymentService::class)->processPayment($posOrder, ['amount' => 40000], true);
        Http::fake(['https://graph.facebook.com/*/456/events' => Http::response(['events_received' => 1])]);
        $this->travel(5)->minutes();
        $this->artisan('track-ctwa:process')->assertSuccessful();
        $this->assertSame('100000.00', $tracked->refresh()->paid_total);
        $this->assertSame($settledAt->timestamp, $tracked->paid_at->timestamp);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => (float) $request['data'][0]['custom_data']['value'] === 100000.0);
    }

    public function test_expired_payment_fails_without_changing_payment_timestamp(): void
    {
        $paidAt = now()->subDays(8);
        $this->click('wamid.ad', 'old-click', '111', now()->subDays(9)->timestamp);
        app(TrackCtwaService::class)->captureInbox($this->account, '628123456789', 'wamid.recap', "Rekap Order\nNo. Order: OLD\nTotal: Rp15.000", $paidAt->copy()->subHour(), false);
        app(TrackCtwaService::class)->confirmExternal($this->setting, CtwaOrder::sole(), '15000', $paidAt, $this->owner->id);
        $this->artisan('track-ctwa:process')->assertSuccessful();
        $this->assertSame(CtwaDelivery::FAILED, CtwaDelivery::sole()->status);
        $this->assertSame($paidAt->timestamp, CtwaOrder::sole()->paid_at->timestamp);
        Http::assertNothingSent();
    }

    public function test_reporting_uses_ad_timezone_click_cohort_and_distinct_meta_roas(): void
    {
        $this->click('wamid.ad', 'cohort-click', '111', now()->subHours(13)->timestamp); // 03:00 WIB, same date as the report.
        app(TrackCtwaService::class)->captureInbox($this->account, '628123456789', 'wamid.recap', "Rekap Order\nNo. Order: AD-1\nTotal: Rp150.000", now(), false);
        $this->travel(1)->days();
        app(TrackCtwaService::class)->confirmExternal($this->setting, CtwaOrder::sole(), '150000', now(), $this->owner->id);
        Http::fake([
            'https://graph.facebook.com/*/act_909?*' => Http::response(['account_id' => '909', 'name' => 'Pilot', 'timezone_name' => 'Asia/Jakarta', 'currency' => 'IDR']),
            'https://graph.facebook.com/*/act_909/insights?*' => Http::sequence()
                ->push(['data' => []])
                ->push(['data' => [[
                    'ad_id' => '111', 'ad_name' => 'Pilot ad', 'campaign_id' => '333', 'adset_id' => '444', 'spend' => '50000',
                    'purchase_roas' => [['action_type' => 'omni_purchase', 'value' => '2.25'], ['action_type' => 'offsite_conversion.fb_pixel_purchase', 'value' => '1.25']],
                    'account_currency' => 'IDR',
                ]]])
                ->push(['data' => [[
                    'account_id' => '909', 'spend' => '75000', 'purchase_roas' => [['action_type' => 'omni_purchase', 'value' => '1.75']],
                ]]]),
        ]);
        $account = $this->actingAs($this->owner, 'sanctum')->postJson('/api/track-ctwa/ad-accounts', [
            'account_id' => 'act_909', 'access_token' => 'ads-secret',
        ])->assertOk()->assertJsonMissing(['access_token' => 'ads-secret'])->json('id');
        $url = "/api/track-ctwa/ad-accounts/$account/report?from=2026-06-09&to=2026-06-09&level=ad&campaign_id=333";
        $this->getJson($url)->assertOk()->assertJsonPath('timezone', 'Asia/Jakarta')
            ->assertJsonPath('rows.0.chats', 1)->assertJsonPath('rows.0.paid_orders', 1)
            ->assertJsonPath('rows.0.revenue', '150000.00')
            ->assertJsonPath('rows.0.qashierwise_roas', 3)->assertJsonPath('rows.0.meta_roas', 2.25);
        $this->getJson($url)->assertOk();
        Http::assertSentCount(3);
        $this->getJson("/api/track-ctwa/ad-accounts/$account/report?from=2026-06-09&to=2026-06-09&level=account")
            ->assertOk()->assertJsonPath('rows.0.account_id', '909')->assertJsonPath('rows.0.qashierwise_roas', 2)
            ->assertJsonPath('rows.0.meta_roas', 1.75);
        $this->actingAs(User::factory()->create(['is_master_admin' => true]), 'sanctum')->getJson($url)->assertNotFound();
    }

    public function test_owner_can_validate_dataset_and_staff_need_their_assigned_permissions(): void
    {
        Http::fake(['https://graph.facebook.com/*/321/dataset' => Http::response(['id' => '456'])]);
        $path = "/api/track-ctwa/phones/{$this->account->id}";
        $this->actingAs($this->owner, 'sanctum')->putJson($path, [
            'enabled' => true, 'dataset_id' => '999', 'events_token' => 'wrong-dataset-token',
        ])->assertUnprocessable();
        $this->putJson($path, ['enabled' => true, 'dataset_id' => '456', 'events_token' => 'rotated-events-token'])->assertOk();
        $this->assertSame('rotated-events-token', $this->setting->refresh()->events_token);
        $this->assertNotSame('rotated-events-token', DB::table('ctwa_settings')->value('events_token'));
        $this->getJson('/api/track-ctwa')->assertOk()->assertJsonMissingPath('phones.0.tracking.events_token');

        $reviewer = User::factory()->create(['is_master_admin' => false]);
        $role = Role::create(['name' => 'Review Staff', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('manage_track_ctwa');
        $reviewer->assignRole($role);
        $posUser = PosUser::create([
            'user_id' => $reviewer->id, 'store_id' => Store::factory()->for($this->owner)->create()->id,
            'role_id' => $role->id, 'is_active' => true,
        ]);
        $this->actingAs($reviewer, 'sanctum')->getJson("$path/recaps")->assertOk();
        $this->putJson($path, ['enabled' => false])->assertForbidden();
        $this->postJson("$path/orders/1/confirm", [])->assertForbidden();
        $posUser->update(['is_active' => false]);
        $this->getJson("$path/recaps")->assertForbidden();
    }

    public function test_report_omits_qashierwise_roas_for_a_non_idr_ad_account(): void
    {
        $ads = CtwaAdAccount::create([
            'user_id' => $this->owner->id, 'account_id' => '909', 'name' => 'USD Account',
            'currency' => 'USD', 'timezone' => 'Asia/Jakarta', 'access_token' => 'test-ads-token',
        ]);
        Http::fake(['https://graph.facebook.com/*/act_909/insights?*' => Http::response(['data' => [[
            'ad_id' => '111', 'spend' => '20.00', 'purchase_roas' => [['action_type' => 'omni_purchase', 'value' => '2.25']],
        ]]])]);
        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/track-ctwa/ad-accounts/{$ads->id}/report?from=2026-06-09&to=2026-06-09&level=ad")
            ->assertOk()->assertJsonPath('rows.0.currency', 'USD')
            ->assertJsonPath('rows.0.qashierwise_roas', null)->assertJsonPath('rows.0.meta_roas', 2.25);
    }

    public function test_dashboard_exposes_staff_review_order_and_ad_report_sections(): void
    {
        $this->withoutVite();
        $this->get('/dashboard/track-ctwa')->assertOk()
            ->assertSee('Perlu ditinjau')
            ->assertSee('Order dan pengiriman Purchase')
            ->assertSee('ROAS Meta · Semua sumber');
    }

    private function click(string $id, ?string $clickId, string $sourceId, int $timestamp): void
    {
        app(TrackCtwaService::class)->captureWebhook($this->account, '321', 'messages', ['messages' => [[
            'id' => $id, 'timestamp' => $timestamp, 'from' => '628123456789',
            'referral' => ['source_id' => $sourceId, 'source_type' => 'ad', 'ctwa_clid' => $clickId],
        ]]]);
    }

    private function postWebhook(string $field, string $key, array $message, ?bool $valid = true)
    {
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '321',
                'changes' => [[
                    'field' => $field,
                    'value' => ['metadata' => ['phone_number_id' => $this->account->phone_number_id], $key => [$message]],
                ]],
            ]],
        ]);

        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($valid !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $body, $valid ? 'test-signing-secret' : 'invalid');
        }

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [], $headers, $body);
    }

    private function posOrder(string $number): Order
    {
        $store = Store::factory()->for($this->owner)->create();
        $role = Role::create(['name' => 'Cashier', 'guard_name' => 'sanctum']);
        $staff = PosUser::create(['user_id' => $this->owner->id, 'store_id' => $store->id, 'role_id' => $role->id, 'is_active' => true]);

        return Order::create([
            'store_id' => $store->id,
            'pos_user_id' => $staff->id,
            'order_number' => $number,
            'customer_phone' => '0812-3456-789',
            'total' => 100000,
        ]);
    }
}
