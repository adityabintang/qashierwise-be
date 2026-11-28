<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppBroadcast;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppBroadcastRecipient;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppBroadcastSender;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class WhatsAppBroadcastTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/whatsapp/broadcasts';

    private string $tier = 'TIER_50';

    private bool $quotaUnavailable = false;

    private bool $approved = true;

    private array $sendResults = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(function (Request $request) {
            $segments = explode('/', trim(parse_url($request->url(), PHP_URL_PATH), '/'));
            $id = $segments[1] ?? '';
            $edge = $segments[2] ?? null;
            if ($edge === 'messages') {
                return $this->sendResults === [] ? Http::response(['messages' => [['id' => 'wamid.broadcast']]]) : array_shift($this->sendResults);
            }
            if ($edge === 'owned_whatsapp_business_accounts') {
                return Http::response(['data' => [['id' => '100'], ['id' => '101']]]);
            }
            if ($edge === 'message_templates') {
                return Http::response(['data' => [$this->template($id === '100' ? '200' : '201')]]);
            }
            if (in_array($id, ['200', '201'], true)) {
                return Http::response($this->template($id));
            }

            return $this->quotaUnavailable ? Http::response([], 503) : Http::response([
                'owner_business_info' => ['id' => '300'],
                'whatsapp_business_manager_messaging_limit' => $this->tier,
            ]);
        });
    }

    public function test_quota_is_shared_and_reservations_reject_the_next_broadcast(): void
    {
        $sender = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $other = WhatsAppAccount::factory()->create(['waba_id' => '101']);
        $this->actingAs($sender->user, 'sanctum');
        $contacts = WhatsAppContact::factory()->count(50)->create([
            'user_id' => $other->user_id,
            'phone_number_id' => $other->phone_number_id,
        ]);
        foreach ($contacts as $index => $contact) {
            $this->logMessage($other, $contact, "template.$index", 'outgoing', 'template', now()->subHour());
        }
        $this->logMessage($other, $contacts[48], 'in-window', 'incoming', 'text', now()->subHours(2));
        $this->logMessage($other, $contacts[49], 'in-window-2', 'incoming', 'text', now()->subHours(2));
        $this->logMessage($other, $contacts[10], 'incoming-after-send', 'incoming', 'text', now()->subMinutes(10));
        $this->logMessage($other, $contacts[0], 'same-recipient', 'outgoing', 'template', now()->subHour());

        $first = $this->contact($sender, '62800000001');
        $second = $this->contact($sender, '62800000002');
        $third = $this->contact($other, '62800000003');

        $this->getJson(self::URL.'/options')->assertOk()->assertJsonPath('data.quota.remaining', 2);
        $this->postJson(self::URL, $this->draft([$first->id, $second->id, $this->contact($sender, '62800000004')->id]))
            ->assertConflict()
            ->assertJsonPath('error_code', 'BROADCAST_LIMIT_EXCEEDED')
            ->assertJsonPath('recipient_count', 3)
            ->assertJsonPath('remaining_quota', 2);
        $this->assertDatabaseCount('whatsapp_broadcasts', 0);

        $this->actingAs($other->user, 'sanctum')->getJson(self::URL.'/options')->assertJsonPath('data.quota.remaining', 2);
        $this->actingAs($sender->user, 'sanctum')->postJson(self::URL, $this->draft([$first->id, $second->id]))
            ->assertAccepted()->assertJsonPath('data.queued_count', 2);
        $this->actingAs($other->user, 'sanctum')->postJson(self::URL, $this->draft([$third->id], '201'))
            ->assertConflict()->assertJsonPath('remaining_quota', 0);
        $this->assertDatabaseCount('whatsapp_broadcasts', 1);
    }

    public function test_consent_ownership_opt_out_deduplication_and_idempotency(): void
    {
        $this->tier = 'TIER_UNLIMITED';
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $other = WhatsAppAccount::factory()->create(['waba_id' => '101']);
        $this->actingAs($account->user, 'sanctum');
        $first = $this->contact($account, '62800000001');
        $same = $this->contact($account, '+62800000001');
        $optedOut = $this->contact($account, '62800000002');
        $this->optOut($optedOut);
        $foreign = $this->contact($other, '62800000003');

        $this->getJson(self::URL.'/options')->assertOk()->assertJsonPath('data.quota.remaining', null)
            ->assertJsonPath('data.templates.0.id', '200');
        $this->postJson(self::URL, $this->draft([$foreign->id]))->assertUnprocessable()->assertJsonValidationErrors('contact_ids');
        $this->postJson(self::URL, $this->draft([$first->id], '201'))->assertUnprocessable()->assertJsonValidationErrors('template_id');
        $draft = $this->draft([$first->id, $same->id, $optedOut->id]);
        $this->postJson(self::URL, array_replace($draft, ['consent_confirmed' => false]))
            ->assertUnprocessable()->assertJsonValidationErrors('consent_confirmed');
        $this->postJson(self::URL, array_replace($draft, ['variables' => ['header' => ['1' => 'Hi']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('variables');
        $id = $this->postJson(self::URL, $draft)->assertAccepted()
            ->assertJsonPath('data.queued_count', 1)->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.preview.body', 'Halo Rina.')->json('data.id');

        $this->quotaUnavailable = true;
        $this->postJson(self::URL, $draft)->assertAccepted()->assertJsonPath('data.id', $id);
        $this->postJson(self::URL, array_replace($draft, ['contact_ids' => [$first->id]]))
            ->assertConflict()->assertJsonPath('error_code', 'BROADCAST_IDEMPOTENCY_CONFLICT');
        $this->assertDatabaseCount('whatsapp_broadcast_recipients', 2);
        $this->actingAs($other->user, 'sanctum')->getJson(self::URL.'/'.$id)->assertNotFound();
    }

    public function test_unverified_quota_rejects_options_and_submission(): void
    {
        $this->quotaUnavailable = true;
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $this->actingAs($account->user, 'sanctum');
        $contact = $this->contact($account, '62800000001');
        $this->getJson(self::URL.'/options')->assertServiceUnavailable()->assertJsonPath('error_code', 'BROADCAST_QUOTA_UNAVAILABLE');
        $this->postJson(self::URL, $this->draft([$contact->id]))->assertServiceUnavailable()
            ->assertJsonPath('error_code', 'BROADCAST_QUOTA_UNAVAILABLE');
        $this->assertDatabaseCount('whatsapp_broadcasts', 0);
    }

    public function test_worker_rechecks_opt_out_and_records_meta_payload_and_signed_receipts(): void
    {
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $this->actingAs($account->user, 'sanctum');
        $first = $this->contact($account, '62800000001');
        $second = $this->contact($account, '62800000002');
        $id = $this->postJson(self::URL, $this->draft([$first->id, $second->id]))->assertAccepted()->json('data.id');
        $this->optOut($second);

        $this->runJob($id);

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages')
            && $request['to'] === $first->wa_id
            && $request['template']['components'][0] === ['type' => 'header', 'parameters' => [['type' => 'text', 'text' => 'Hi']]]
            && $request['template']['components'][1] === ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => 'Rina', 'parameter_name' => 'name']]]);

        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'wamid.broadcast', 'type' => 'template', 'contact_id' => $first->id]);
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('data.accepted_count', 1)->assertJsonPath('data.skipped_count', 1);
        $this->receipt($account, $first, 'wamid.broadcast', 'read');
        $this->receipt($account, $first, 'wamid.broadcast', 'delivered');

        $this->assertDatabaseHas('whatsapp_messages', ['message_id' => 'wamid.broadcast', 'status' => 'read']);
        $this->getJson(self::URL.'/'.$id)->assertOk()->assertJsonPath('data.delivered_count', 1)
            ->assertJsonPath('recipients.data.0.status', 'read');
    }

    public function test_quota_drop_and_paused_templates_stop_unstarted_recipients(): void
    {
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $this->actingAs($account->user, 'sanctum');
        $contact = $this->contact($account, '62800000001');
        $id = $this->postJson(self::URL, $this->draft([$contact->id]))->assertAccepted()->json('data.id');
        $this->tier = 'UNTIERED';
        $this->runJob($id);
        $this->getJson(self::URL.'/'.$id)->assertJsonPath('data.stop_reason', 'quota_reduced')->assertJsonPath('data.skipped_count', 1);

        $this->tier = 'TIER_50';
        $id = $this->postJson(self::URL, $this->draft([$contact->id]))->assertAccepted()->json('data.id');
        $this->approved = false;
        $this->runJob($id);
        $this->getJson(self::URL.'/'.$id)->assertJsonPath('data.stop_reason', 'template_changed')->assertJsonPath('data.skipped_count', 1);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/messages'));
    }

    public function test_provider_restriction_stops_the_queue_and_uncertain_results_are_never_resent(): void
    {
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $this->actingAs($account->user, 'sanctum');
        $first = $this->contact($account, '62800000001');
        $second = $this->contact($account, '62800000002');
        $third = $this->contact($account, '62800000003');
        $id = $this->postJson(self::URL, $this->draft([$first->id, $second->id, $third->id]))->assertAccepted()->json('data.id');
        $this->sendResults = [Http::response([], 502), Http::response(['error' => ['code' => 131031]], 400)];
        $this->runJob($id);
        $this->runJob($id);

        $this->getJson(self::URL.'/'.$id)->assertOk()
            ->assertJsonPath('data.review_count', 1)
            ->assertJsonPath('data.failed_count', 1)
            ->assertJsonPath('data.skipped_count', 1)
            ->assertJsonPath('data.stop_reason', 'account_restricted');

        $pending = WhatsAppBroadcastRecipient::where('whatsapp_broadcast_id', $id)->where('status', 'review')->sole();
        $this->receipt($account, $first, 'wamid.reconciled', 'delivered', $pending->callback_id);
        $this->getJson(self::URL.'/'.$id)->assertJsonPath('data.review_count', 0)->assertJsonPath('data.delivered_count', 1);
        $this->assertCount(2, Http::recorded(fn (Request $request) => str_ends_with($request->url(), '/messages')));
    }

    public function test_scheduler_dispatches_to_the_database_worker_and_recovers_uncertain_attempts(): void
    {
        config(['queue.default' => 'database']);
        $account = WhatsAppAccount::factory()->create(['waba_id' => '100']);
        $this->actingAs($account->user, 'sanctum');
        $contact = $this->contact($account, '62800000001');
        $other = $this->contact($account, '62800000002');
        $id = $this->postJson(self::URL, $this->draft([$contact->id, $other->id]))->assertAccepted()->json('data.id');
        WhatsAppBroadcastRecipient::where('contact_id', $other->id)->update([
            'status' => 'sending',
            'attempted_at' => now()->subMinutes(6),
        ]);

        $event = collect(app(Schedule::class)->events())->firstWhere('description', 'whatsapp-broadcast-dispatch');
        $this->assertNotNull($event);
        $event->run(app());
        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertSuccessful();

        $this->getJson(self::URL.'/'.$id)->assertOk()
            ->assertJsonPath('data.review_count', 1)->assertJsonPath('data.accepted_count', 1);
        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/messages') && $request['to'] === $contact->wa_id);
        Http::assertNotSent(fn (Request $request) => str_ends_with($request->url(), '/messages') && $request['to'] === $other->wa_id);
    }

    private function runJob(int $id): void
    {
        (new SendWhatsAppBroadcast($id))->handle(app(WhatsAppBroadcastSender::class));
    }

    private function template(string $id): array
    {
        return [
            'id' => $id,
            'name' => 'broadcast_test',
            'language' => 'id',
            'status' => $this->approved ? 'APPROVED' : 'PAUSED',
            'category' => 'MARKETING',
            'components' => [
                ['type' => 'HEADER', 'format' => 'TEXT', 'text' => '{{1}}'],
                ['type' => 'BODY', 'text' => 'Halo {{name}}.'],
            ],
        ];
    }

    private function draft(array $contacts, string $template = '200'): array
    {
        return [
            'contact_ids' => $contacts,
            'template_id' => $template,
            'variables' => ['header' => ['1' => 'Hi'], 'body' => ['name' => 'Rina']],
            'idempotency_key' => (string) Str::uuid(),
            'consent_confirmed' => true,
        ];
    }

    private function contact(WhatsAppAccount $account, string $number): WhatsAppContact
    {
        return WhatsAppContact::factory()->create([
            'user_id' => $account->user_id,
            'phone_number_id' => $account->phone_number_id,
            'wa_id' => $number,
        ]);
    }

    private function optOut(WhatsAppContact $contact): void
    {
        DB::table('whatsapp_contacts')->where('id', $contact->id)->update(['drips_unsubscribed_at' => now()]);
    }

    private function logMessage(WhatsAppAccount $account, WhatsAppContact $contact, string $id, string $direction, string $type, $time): void
    {
        WhatsAppMessage::withoutGlobalScopes()->create([
            'user_id' => $account->user_id, 'phone_number_id' => $account->phone_number_id, 'contact_id' => $contact->id,
            'message_id' => $id, 'direction' => $direction, 'type' => $type, 'sent_at' => $time,
        ]);
    }

    private function receipt(WhatsAppAccount $account, WhatsAppContact $contact, string $id, string $status, ?string $callback = null): void
    {
        config(['whatsapp.app_secret' => 'broadcast-webhook-test']);
        $json = json_encode([
            'object' => 'whatsapp_business_account',
            'entry' => [['id' => $account->waba_id, 'changes' => [['field' => 'messages', 'value' => [
                'metadata' => ['phone_number_id' => $account->phone_number_id],
                'statuses' => [array_filter([
                    'id' => $id, 'status' => $status, 'recipient_id' => $contact->wa_id,
                    'timestamp' => (string) now()->timestamp, 'biz_opaque_callback_data' => $callback,
                ])],
            ]]]]],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/whatsapp/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, config('whatsapp.app_secret')),
        ], $json)->assertOk();
    }
}
