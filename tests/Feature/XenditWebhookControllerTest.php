<?php

namespace Tests\Feature;

use App\Models\MerchantBalance;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class XenditWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SubMerchant $subMerchant;

    private QrisTransaction $transaction;

    private string $webhookToken = 'test_webhook_token_456';

    protected function setUp(): void
    {
        parent::setUp();

        // Set webhook token in config
        config(['xendit.webhook_token' => $this->webhookToken]);

        // Create user and sub-merchant
        $this->user = User::factory()->create();

        $this->subMerchant = SubMerchant::create([
            'user_id' => $this->user->id,
            'business_name' => 'Test Business',
            'xendit_account_id' => 'xen_acc_test_123',
            'xendit_account_status' => 'active',
            'is_active' => true,
        ]);

        MerchantBalance::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // Create a pending transaction
        $this->transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-'.now()->format('YmdHis').'-TEST1234',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_PENDING,
            'provider' => 'xendit',
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /** @test */
    public function webhook_rejects_missing_reference_id(): void
    {
        $response = $this->postJson('/api/webhooks/xendit', [
            'status' => 'COMPLETED',
        ], [
            'x-callback-token' => $this->webhookToken,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'Missing required field: reference_id or external_id',
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    /** @test */
    public function webhook_rejects_invalid_signature(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'COMPLETED');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => 'invalid_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'error' => 'Invalid webhook signature',
                'code' => 'UNAUTHORIZED',
            ]);
    }

    /** @test */
    public function webhook_processes_completed_payment(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'COMPLETED');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => $this->webhookToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status updated
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_SETTLEMENT, $this->transaction->status);
        $this->assertNotNull($this->transaction->paid_at);
        $this->assertNotNull($this->transaction->reference_id);
    }

    /** @test */
    public function webhook_processes_expired_payment(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'INACTIVE');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => $this->webhookToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status updated
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_EXPIRE, $this->transaction->status);
    }

    /** @test */
    public function webhook_returns_ok_for_unknown_transaction(): void
    {
        $payload = $this->buildWebhookPayload('UNKNOWN-ORDER-ID', 'COMPLETED');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => $this->webhookToken,
        ]);

        // Should return 200 to prevent retries
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    /** @test */
    public function webhook_skips_already_settled_transaction(): void
    {
        // Mark transaction as already settled
        $this->transaction->update([
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'paid_at' => now(),
            'reference_id' => 'existing-xendit-id',
        ]);

        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'COMPLETED');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => $this->webhookToken,
        ]);

        $response->assertStatus(200);

        // Verify transaction status unchanged
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_SETTLEMENT, $this->transaction->status);
        $this->assertEquals('existing-xendit-id', $this->transaction->reference_id);
    }

    /** @test */
    public function webhook_handles_active_status(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'ACTIVE');

        $response = $this->postJson('/api/webhooks/xendit', $payload, [
            'x-callback-token' => $this->webhookToken,
        ]);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status remains pending
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_PENDING, $this->transaction->status);
    }

    /**
     * Build a webhook payload for testing.
     */
    private function buildWebhookPayload(string $orderId, string $status): array
    {
        $payload = [
            'id' => 'qr_'.uniqid(),
            'reference_id' => $orderId,
            'amount' => 100000,
            'status' => $status,
            'type' => 'DYNAMIC',
            'currency' => 'IDR',
            'created' => now()->toIso8601String(),
            'updated' => now()->toIso8601String(),
        ];

        return $payload;
    }
}
