<?php

namespace Tests\Feature;

use App\Jobs\ProcessAiAgentMessage;
use App\Jobs\ProcessWhatsAppCoexistenceBatch;
use App\Jobs\StoreWhatsAppHistoryAttachment;
use App\Models\AiAgent;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\DeliveryFulfillmentService;
use App\Services\WhatsAppCoexistenceImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class WhatsAppCoexistenceImportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.app_secret' => 'test-signing-secret',
            'whatsapp.embedded_signup.app_id' => 'test-app',
            'whatsapp.embedded_signup.app_secret' => 'test-signing-secret',
            'whatsapp.embedded_signup.config_id' => 'test-config',
        ]);
        $this->travelTo(now()->startOfSecond());
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');

        Http::preventStrayRequests();
    }

    public function test_signup_requests_contacts_then_history_once_and_keeps_cloud_api_signup_available(): void
    {
        $this->fakeSignup();
        $this->signup()->assertOk();
        $this->signup()->assertOk();

        $requests = Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/smb_app_data'))
            ->map(fn ($pair) => $pair[0]['sync_type'])->values()->all();
        $this->assertSame(['smb_app_state_sync', 'history'], $requests);
        $this->assertDatabaseCount('whatsapp_coexistence_imports', 1);
        $this->assertDatabaseHas('whatsapp_coexistence_imports', [
            'contacts_request_id' => 'contacts-request',
            'history_request_id' => 'history-request',
        ]);

        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.history_status', 'waiting');
        $this->travel(25)->hours();
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.history_status', 'unconfirmed');
        $this->webhook('history', $this->history(100, [$this->message('late', 26 * 3600)]))->assertOk();
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.history_status', 'imported');

        $this->signup('FINISH', 'cloud-code')->assertOk()->assertJsonPath('data.coexistence_enabled', false);
        $this->getJson('/api/whatsapp/account')->assertOk()->assertJsonPath('data.coexistence_import', null);
        $this->assertDatabaseCount('whatsapp_coexistence_imports', 1);
        Http::assertSentCount(9);
    }

    public function test_unverified_assets_and_unsubscribed_signup_cannot_request_an_import(): void
    {
        $this->fakeSignup(subscriptionSucceeds: false);
        $this->signup(phone: '999')->assertBadRequest();
        $this->assertDatabaseCount('whatsapp_coexistence_imports', 0);
        $this->signup()->assertOk();
        $this->assertDatabaseHas('whatsapp_coexistence_imports', ['subscribed_at' => null]);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/smb_app_data'));
    }

    public function test_an_uncertain_sync_request_is_not_sent_again_but_late_batches_are_received(): void
    {
        $attempts = [];
        $this->fakeSignup(sync: function (Request $request) use (&$attempts) {
            $type = $request['sync_type'];
            $attempts[] = $type;

            return $type === 'smb_app_state_sync' ? Http::failedConnection()($request) : Http::response([
                'request_id' => 'history-request',
            ]);
        });
        $this->signup()->assertOk();
        $this->signup()->assertOk();
        $this->assertSame(['smb_app_state_sync', 'history'], $attempts);
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.contacts_status', 'waiting');
        $this->travel(25)->hours();
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.contacts_status', 'unconfirmed');

        $this->webhook('smb_app_state_sync', ['state_sync' => [
            $this->contact('628123456789', 'Kontak terlambat', 26 * 3600),
        ]])->assertOk();
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.contacts_status', 'imported');
    }

    public function test_unsigned_and_misrouted_batches_are_ignored_and_declined_history_keeps_contacts_and_live_messages(): void
    {
        $this->fakeSignup();
        $this->signup()->assertOk();
        WhatsAppAccount::factory()->for(User::factory())->create(['phone_number_id' => '999', 'waba_id' => '998']);
        $contacts = ['state_sync' => [$this->contact('628123456789', 'Rina', 20)]];
        $this->webhook('smb_app_state_sync', $contacts, signature: 'invalid')->assertForbidden();
        $this->webhook('smb_app_state_sync', $contacts, signature: null)->assertForbidden();
        $this->webhook('smb_app_state_sync', $contacts, waba: '998')->assertOk();
        $this->webhook('smb_app_state_sync', $contacts, phone: '999')->assertOk();
        $this->assertDatabaseCount('whatsapp_coexistence_batches', 0);

        $this->webhook('smb_app_state_sync', $contacts)->assertOk();
        $this->webhook('history', ['history' => [['errors' => [['code' => 2593109]]]]])->assertOk();
        $this->getJson('/api/whatsapp/account')->assertOk()
            ->assertJsonPath('data.coexistence_import.contacts_status', 'imported')
            ->assertJsonPath('data.coexistence_import.history_status', 'not_shared');
        $this->getJson('/api/whatsapp/contacts')->assertOk()->assertJsonPath('data.0.name', 'Rina');
        $this->assertDatabaseCount('whatsapp_messages', 0);

        $this->live('live-after-decline', 'Pesan baru')->assertOk();
        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'live-after-decline', 'is_history_import' => false]);
    }

    public function test_batches_are_encrypted_before_the_worker_and_contacts_keep_existing_names(): void
    {
        $this->fakeSignup();
        $this->signup()->assertOk();
        Queue::fake([ProcessWhatsAppCoexistenceBatch::class]);
        $contact = WhatsAppContact::factory()->for($this->user)->create([
            'phone_number_id' => '200',
            'wa_id' => '628123456789',
            'name' => 'Nama tersimpan',
        ]);
        $value = ['state_sync' => [
            $this->contact($contact->wa_id, 'Nama di ponsel', 200),
            $this->contact('628999999999', 'Rina terbaru', 10),
            $this->contact(null, 'BSUID only', 10),
            $this->contact('628777777777', 'Perubahan live', -10),
        ]];
        $this->webhook('smb_app_state_sync', $value)->assertOk();
        $this->webhook('smb_app_state_sync', $value)->assertOk();
        $this->assertDatabaseCount('whatsapp_coexistence_batches', 1);
        $batch = DB::table('whatsapp_coexistence_batches')->first();
        $this->assertStringNotContainsString('Rina', $batch->payload);
        $this->assertStringContainsString('Rina', Crypt::decryptString($batch->payload));
        Queue::assertPushed(ProcessWhatsAppCoexistenceBatch::class);

        $service = app(WhatsAppCoexistenceImportService::class);
        $service->processBatch($batch->id);
        $service->processBatch($batch->id);
        $this->webhook('smb_app_state_sync', ['state_sync' => [
            $this->contact('628999999999', 'Rina lama', 20),
            $this->contact(null, 'BSUID repeated', 20),
        ]])->assertOk();
        $service->processBatch(DB::table('whatsapp_coexistence_batches')->max('id'));

        $this->assertSame('Nama tersimpan', $contact->fresh()->name);
        $this->assertDatabaseHas('whatsapp_contacts', ['wa_id' => '628999999999', 'name' => 'Rina terbaru']);
        $this->assertDatabaseMissing('whatsapp_contacts', ['wa_id' => '628777777777']);
        $this->assertDatabaseHas('whatsapp_coexistence_imports', ['contacts_without_phone' => 1]);
        $this->assertDatabaseHas('whatsapp_coexistence_batches', ['id' => $batch->id, 'payload' => null]);
    }

    public function test_database_worker_processes_a_batch_after_the_webhook_acknowledges_it(): void
    {
        $this->fakeSignup();
        config(['queue.default' => 'database']);
        $this->signup()->assertOk();
        $this->assertDatabaseCount('jobs', 1);
        $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();
        $this->assertDatabaseCount('jobs', 0);

        $this->webhook('history', $this->history(100, [$this->message('worker-message', 10)]))->assertOk();
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertNotNull(DB::table('whatsapp_coexistence_batches')->value('payload'));

        $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();
        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'worker-message', 'is_history_import' => true]);
        $this->assertDatabaseHas('whatsapp_coexistence_batches', ['payload' => null]);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_signed_history_preserves_text_and_unsigned_mixed_batches_make_no_changes(): void
    {
        $this->fakeSignup();
        $this->signup()->assertOk();
        $text = "  Awal pesan\nAkhir pesan  ";
        $message = $this->message('original-text', 60);
        $message['text']['body'] = $text;
        $metadata = ['phone_number_id' => '200', 'display_phone_number' => '628111111111'];
        $history = $this->history(100, [$message]);

        $this->deliverWebhook([
            ['field' => 'messages', 'value' => ['metadata' => $metadata, 'messages' => [$this->message('live-unsigned', 0)]]],
            ['field' => 'history', 'value' => ['metadata' => $metadata] + $history],
        ], signature: null)->assertForbidden();
        $this->assertDatabaseCount('whatsapp_messages', 0);
        $this->assertDatabaseCount('whatsapp_coexistence_batches', 0);

        $this->webhook('history', $history)->assertOk();
        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'original-text', 'content' => $text]);
    }

    public function test_dashboard_routes_render_the_import_status_and_private_attachment_controls(): void
    {
        $this->withoutVite();
        $this->get('/dashboard/whatsapp-account')->assertOk()
            ->assertSee('Impor WhatsApp Business')
            ->assertSee('Riwayat Inbox')
            ->assertSee('Tetap buka WhatsApp Business saat impor berjalan.');
        $this->get('/dashboard/messages')->assertOk()
            ->assertSee('Unduh lampiran')
            ->assertSee('Lampiran tidak tersedia');
    }

    public function test_history_keeps_original_order_and_does_not_open_the_service_window_or_increment_unread(): void
    {
        $this->fakeSignup();
        $this->signup()->assertOk();
        Queue::fake([ProcessAiAgentMessage::class]);
        $account = WhatsAppAccount::withoutGlobalScopes()->sole();
        AiAgent::create([
            'whatsapp_account_id' => $account->id,
            'bot_name' => 'Test agent',
            'system_prompt' => 'Test prompt',
            'is_active' => true,
        ]);
        WhatsAppContact::factory()->for($this->user)->create([
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => '628123456789',
            'ai_active' => true,
            'last_message_at' => null,
            'unread_count' => 0,
        ]);
        $this->webhook('history', $this->history(100, [
            $this->message('history-newer', 20),
            $this->message('collision', 100),
        ]))->assertOk();
        $contact = WhatsAppContact::withoutGlobalScopes()->sole();
        $window = new \ReflectionMethod(DeliveryFulfillmentService::class, 'customerWithin24h');
        $this->assertFalse($window->invoke(app(DeliveryFulfillmentService::class), $contact));
        $this->assertSame(0, $contact->unread_count);
        Queue::assertNotPushed(ProcessAiAgentMessage::class);

        $this->live('collision', 'Live menang')->assertOk();
        $this->live('collision', 'Duplikat live')->assertOk();
        Queue::assertPushed(ProcessAiAgentMessage::class, 1);
        $late = $this->history(30, [
            $this->message('history-older', 500, from: '628111111111'),
            $this->message('collision', 100),
            $this->message('history-tied', 20),
            $this->message('older-than-180-days', 181 * 86400),
        ]);
        $late['history'][0]['threads'][] = [
            'id' => '123456789@g.us',
            'messages' => [$this->message('group-message', 10)],
        ];
        $this->webhook('history', $late)->assertOk();
        $this->webhook('history', $late)->assertOk();

        $contact->refresh();
        $this->assertSame(1, $contact->unread_count);
        $this->assertSame('Live menang', $contact->last_message_text);
        $this->assertTrue($window->invoke(app(DeliveryFulfillmentService::class), $contact));
        $this->assertDatabaseHas('whatsapp_coexistence_imports', ['history_progress' => 100]);
        $this->assertDatabaseCount('whatsapp_messages', 4);
        $this->assertDatabaseCount('whatsapp_contacts', 1);

        $url = "/api/whatsapp/contacts/{$contact->id}/messages";
        $recent = $this->getJson($url.'?per_page=2')->assertOk()
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0.message_id', 'history-tied')
            ->assertJsonPath('data.1.message_id', 'collision')
            ->assertJsonPath('data.1.content', 'Live menang')
            ->assertJsonPath('data.1.is_history_import', false);
        $this->getJson($url.'?per_page=2&before_id='.$recent->json('oldest_id'))->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonPath('data.0.message_id', 'history-older')
            ->assertJsonPath('data.0.direction', 'outgoing')
            ->assertJsonPath('data.0.is_read', true)
            ->assertJsonPath('data.1.message_id', 'history-newer');
    }

    public function test_attachment_before_history_is_matched_and_only_its_account_can_download_it(): void
    {
        $this->fakeSignup();
        Storage::fake('whatsapp_history');
        Storage::fake('public');
        config(['filesystems.default' => 'public']);
        $this->signup()->assertOk();
        $asset = $this->message('with-attachment', 3600);
        $asset['type'] = 'image';
        $caption = "  Caption dari Meta\nbaris kedua  ";
        $asset['image'] = ['id' => '300', 'caption' => $caption];
        $this->webhook('history', ['messages' => [$asset]])->assertOk();
        $this->assertDatabaseCount('whatsapp_messages', 0);

        $placeholder = $this->message('with-attachment', 3600);
        $placeholder['type'] = 'media_placeholder';
        $this->webhook('history', $this->history(100, [$placeholder]))->assertOk();

        $message = WhatsAppMessage::withoutGlobalScopes()->sole();
        $account = WhatsAppAccount::withoutGlobalScopes()->sole();
        $this->assertTrue($message->metadata['history_attachment']);
        $this->assertSame($caption, $message->caption);
        $path = StoreWhatsAppHistoryAttachment::path($account->id, $message->id);
        Storage::disk('whatsapp_history')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $url = "/api/whatsapp/messages/{$message->id}/history-attachment";
        $this->get($url)->assertOk()->assertDownload();

        $other = User::factory()->create();
        WhatsAppAccount::factory()->for($other)->create();
        $this->actingAs($other, 'sanctum')->get($url)->assertNotFound();
    }

    public function test_oversized_and_old_attachments_keep_their_placeholders(): void
    {
        $this->fakeSignup();
        config(['whatsapp.media_limits.image' => 3]);
        Storage::fake('whatsapp_history');
        $this->signup()->assertOk();
        $images = [];
        foreach (['large' => 3600, 'old' => 15 * 86400] as $id => $age) {
            $image = $this->message($id, $age);
            $image['type'] = 'image';
            $image['image'] = ['id' => $id === 'old' ? '301' : '300', 'caption' => 'Caption '.$id];
            $images[] = $image;
        }
        $oldPlaceholder = $this->message('old', 15 * 86400);
        $oldPlaceholder['type'] = 'media_placeholder';
        $this->webhook('history', $this->history(100, [$images[0], $oldPlaceholder]))->assertOk();
        $this->webhook('history', ['messages' => [$images[1]]])->assertOk();

        foreach (WhatsAppMessage::withoutGlobalScopes()->get() as $message) {
            $this->assertTrue($message->metadata['attachment_unavailable']);
            $this->assertSame('Caption '.$message->message_id, $message->caption);
            $this->get("/api/whatsapp/messages/{$message->id}/history-attachment")->assertNotFound();
        }
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'lookaside.fbsbx.com')
            || str_contains($request->url(), '/301?'));
        $this->assertSame([], Storage::disk('whatsapp_history')->allFiles());
    }

    private function fakeSignup(bool $subscriptionSucceeds = true, ?\Closure $sync = null): void
    {
        Http::fake([
            'graph.facebook.com/*/oauth/access_token*' => Http::response(['access_token' => 'business-token']),
            'graph.facebook.com/*/100/phone_numbers*' => Http::response(['data' => [[
                'id' => '200', 'display_phone_number' => '+62 8111111111',
            ]]]),
            'graph.facebook.com/*/200?*' => Http::response(['is_on_biz_app' => true, 'platform_type' => 'CLOUD_API']),
            'graph.facebook.com/*/100/subscribed_apps' => $subscriptionSucceeds
                ? Http::response(['success' => true]) : Http::response(['error' => ['message' => 'unavailable']], 503),
            'graph.facebook.com/*/200/smb_app_data' => $sync ?? fn (Request $request) => Http::response([
                'request_id' => $request['sync_type'] === 'history' ? 'history-request' : 'contacts-request',
            ]),
            'graph.facebook.com/*/300?*' => Http::response(['url' => 'https://lookaside.fbsbx.com/test-file', 'file_size' => 4]),
            'lookaside.fbsbx.com/test-file' => Http::response('file', 200, ['Content-Type' => 'image/png']),
        ]);
    }

    private function signup(string $event = WhatsAppCoexistenceImportService::SIGNUP_EVENT, string $code = 'code', string $phone = '200'): TestResponse
    {
        return $this->postJson('/api/whatsapp/embedded-signup/callback', [
            'event' => $event,
            'code' => $code,
            'waba_id' => '100',
            'phone_number_id' => $phone,
        ]);
    }

    private function webhook(string $field, array $value, string $waba = '100', string $phone = '200', ?string $signature = 'valid'): TestResponse
    {
        return $this->deliverWebhook([[
            'field' => $field,
            'value' => ['metadata' => ['phone_number_id' => $phone, 'display_phone_number' => '628111111111']] + $value,
        ]], $waba, $signature);
    }

    private function deliverWebhook(array $changes, string $waba = '100', ?string $signature = 'valid'): TestResponse
    {
        $body = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => $waba, 'changes' => $changes]],
        ], JSON_THROW_ON_ERROR);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($signature !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = $signature === 'valid'
                ? 'sha256='.hash_hmac('sha256', $body, 'test-signing-secret')
                : 'sha256=invalid';
        }

        return $this->call('POST', '/api/whatsapp/webhook', [], [], [], $headers, $body);
    }

    private function contact(?string $phone, string $name, int $secondsAgo): array
    {
        return [
            'type' => 'contact',
            'action' => 'add',
            'contact' => ['phone_number' => $phone, 'full_name' => $name, 'user_id' => 'ID.100'],
            'metadata' => ['timestamp' => (string) now()->subSeconds($secondsAgo)->timestamp],
        ];
    }

    private function message(string $id, int $secondsAgo, string $from = '628123456789'): array
    {
        return [
            'id' => $id,
            'from' => $from,
            'timestamp' => (string) now()->subSeconds($secondsAgo)->timestamp,
            'type' => 'text',
            'text' => ['body' => 'Riwayat '.$id],
        ];
    }

    private function history(int $progress, array $messages): array
    {
        return ['history' => [[
            'metadata' => ['phase' => 0, 'chunk_order' => $progress, 'progress' => $progress],
            'threads' => [['id' => '628123456789', 'messages' => $messages]],
        ]]];
    }

    private function live(string $id, string $text): TestResponse
    {
        $message = $this->message($id, 0);
        $message['text']['body'] = $text;

        return $this->webhook('messages', ['messages' => [$message]]);
    }
}
