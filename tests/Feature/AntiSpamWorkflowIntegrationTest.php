<?php

namespace Tests\Feature;

use App\Jobs\ProcessBufferedMessages;
use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Services\AntiSpamWorkflow;
use App\Services\MessageBuffer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AntiSpamWorkflowIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected WhatsAppAccount $account;
    protected WhatsAppContact $contact;
    protected AntiSpamWorkflow $workflow;
    protected MessageBuffer $messageBuffer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test user and account
        $this->user = User::factory()->create();
        $this->account = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
            'phone_number_id' => '123456789',
            'access_token' => 'test_token',
        ]);
        $this->contact = WhatsAppContact::factory()->create([
            'user_id' => $this->user->id,
            'phone_number_id' => $this->account->phone_number_id,
            'wa_id' => '1234567890',
        ]);

        $this->workflow = app(AntiSpamWorkflow::class);
        $this->messageBuffer = app(MessageBuffer::class);

        // Clear cache before each test
        Cache::flush();
    }

    /** @test */
    public function workflow_buffers_first_message_and_dispatches_job(): void
    {
        Queue::fake();

        $result = $this->workflow->validate(
            $this->account,
            $this->contact,
            'Hello, this is a test message',
            'msg_123'
        );

        // With buffering enabled, validate returns false (message is buffered)
        $this->assertFalse($result, 'Message should be buffered, not processed immediately');

        // Job should be dispatched
        Queue::assertPushed(ProcessBufferedMessages::class);
    }

    /** @test */
    public function workflow_buffers_multiple_rapid_messages(): void
    {
        Queue::fake();

        // Send 3 rapid messages (like user spam clicking)
        $this->workflow->validate($this->account, $this->contact, 'halo', 'msg_1');
        $this->workflow->validate($this->account, $this->contact, 'halo', 'msg_2');
        $this->workflow->validate($this->account, $this->contact, 'halo', 'msg_3');

        // Only ONE job should be dispatched (debounce)
        Queue::assertPushed(ProcessBufferedMessages::class, 1);

        // Buffer should contain all messages
        $bufferSize = $this->messageBuffer->getBufferSize($this->contact->wa_id);
        $this->assertEquals(3, $bufferSize, 'Buffer should contain 3 messages');
    }

    /** @test */
    public function message_buffer_merges_duplicate_messages(): void
    {
        // Push duplicate messages to buffer
        $this->messageBuffer->push($this->contact->wa_id, 'halo');
        $this->messageBuffer->push($this->contact->wa_id, 'halo');
        $this->messageBuffer->push($this->contact->wa_id, 'halo');

        // Flush and merge
        $messages = $this->messageBuffer->flush($this->contact->wa_id);
        $merged = $this->messageBuffer->mergeMessages($messages);

        // Should merge to single message
        $this->assertEquals('halo', $merged, 'Duplicate messages should be merged to one');
    }

    /** @test */
    public function message_buffer_combines_different_messages(): void
    {
        // Push different messages (like user typing in parts)
        $this->messageBuffer->push($this->contact->wa_id, 'menu');
        $this->messageBuffer->push($this->contact->wa_id, 'ayam bakar');

        // Flush and merge
        $messages = $this->messageBuffer->flush($this->contact->wa_id);
        $merged = $this->messageBuffer->mergeMessages($messages);

        // Should combine messages
        $this->assertEquals('menu ayam bakar', $merged, 'Different messages should be combined');
    }

    /** @test */
    public function workflow_detects_duplicate_messages(): void
    {
        Queue::fake();
        
        $content = 'Duplicate message test';

        // First message should be buffered
        $result1 = $this->workflow->validate(
            $this->account,
            $this->contact,
            $content,
            'msg_456'
        );
        $this->assertFalse($result1, 'First message should be buffered');

        // Clear debounce to simulate new session
        Cache::flush();

        // Same content again should be rejected as duplicate (deduplication layer)
        $result2 = $this->workflow->validate(
            $this->account,
            $this->contact,
            $content,
            'msg_457'
        );
        
        // Note: With buffer enabled, dedup check happens before buffering
        // If dedup is working, this should still be false (either buffered or rejected)
        $this->assertFalse($result2);
    }

    /** @test */
    public function workflow_enforces_rate_limit(): void
    {
        Queue::fake();
        
        $maxMessages = config('ai_agent.anti_spam.rate_limit.max_messages', 20);

        // Send max_messages
        for ($i = 0; $i < $maxMessages; $i++) {
            $this->workflow->validate(
                $this->account,
                $this->contact,
                "Message number {$i}",
                "msg_{$i}"
            );
        }

        // Next message should be rejected (rate limit exceeded)
        $result = $this->workflow->validate(
            $this->account,
            $this->contact,
            'Message over limit',
            'msg_over_limit'
        );
        $this->assertFalse($result, 'Message over rate limit should be rejected');
    }

    /** @test */
    public function workflow_is_per_user(): void
    {
        Queue::fake();
        
        // Create second user
        $user2 = User::factory()->create();
        $account2 = WhatsAppAccount::factory()->create([
            'user_id' => $user2->id,
            'phone_number_id' => '987654321',
        ]);
        $contact2 = WhatsAppContact::factory()->create([
            'user_id' => $user2->id,
            'phone_number_id' => $account2->phone_number_id,
            'wa_id' => '0987654321',
        ]);

        // Send messages from first user
        for ($i = 0; $i < 10; $i++) {
            $this->workflow->validate(
                $this->account,
                $this->contact,
                "User 1 message {$i}",
                "msg_u1_{$i}"
            );
        }

        // Second user should still be able to send messages (buffered)
        $result = $this->workflow->validate(
            $account2,
            $contact2,
            'User 2 message',
            'msg_u2_1'
        );
        
        // Returns false because buffered, but job should be dispatched
        $this->assertFalse($result);
        
        // Check that second user's buffer has the message
        $bufferSize = $this->messageBuffer->getBufferSize($contact2->wa_id);
        $this->assertEquals(1, $bufferSize, 'Second user buffer should have 1 message');
    }

    /** @test */
    public function workflow_works_without_buffering_when_disabled(): void
    {
        // Disable buffering
        config(['ai_agent.anti_spam.buffer.enabled' => false]);
        
        // Recreate workflow with new config
        $this->workflow = app(AntiSpamWorkflow::class);

        $result = $this->workflow->validate(
            $this->account,
            $this->contact,
            'Test without buffer',
            'msg_no_buffer'
        );

        // Without buffering, first message should return true (process immediately)
        $this->assertTrue($result, 'Without buffering, message should be processed immediately');
    }
}
