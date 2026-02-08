<?php

namespace Tests\Feature;

use App\Jobs\ProcessQrisPayment;
use App\Models\MerchantBalance;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MidtransWebhookControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private SubMerchant $subMerchant;

    private QrisTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and sub-merchant
        $this->user = User::factory()->create();

        $this->subMerchant = SubMerchant::create([
            'user_id' => $this->user->id,
            'business_name' => 'Test User',
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
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    /** @test */
    public function webhook_rejects_missing_required_fields(): void
    {
        $response = $this->postJson('/api/webhooks/midtrans', []);

        $response->assertStatus(400)
            ->assertJson(['status' => 'error', 'message' => 'Missing required fields']);
    }

    /** @test */
    public function webhook_returns_ok_for_unknown_transaction(): void
    {
        $payload = $this->buildWebhookPayload('UNKNOWN-ORDER-ID', 'settlement');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok', 'message' => 'Transaction not found']);
    }

    /** @test */
    public function webhook_processes_settlement_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'settlement');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status updated
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_SETTLEMENT, $this->transaction->status);
        $this->assertNotNull($this->transaction->settled_at);

        // Verify job was dispatched
        Queue::assertPushed(ProcessQrisPayment::class, function ($job) {
            return $job->queue === 'payments';
        });
    }

    /** @test */
    public function webhook_processes_cancellation(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'cancel');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status updated
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_CANCEL, $this->transaction->status);
    }

    /** @test */
    public function webhook_processes_expiration(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'expire');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Verify transaction status updated
        $this->transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_EXPIRE, $this->transaction->status);
    }

    /** @test */
    public function webhook_skips_already_settled_transaction(): void
    {
        Queue::fake();

        // Mark transaction as already settled
        $this->transaction->markAsSettled('existing-midtrans-id');
        $this->transaction->save();

        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'settlement');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

        $response->assertStatus(200);

        // Verify no job was dispatched (already processed)
        Queue::assertNotPushed(ProcessQrisPayment::class);
    }

    /** @test */
    public function webhook_handles_pending_status(): void
    {
        $payload = $this->buildWebhookPayload($this->transaction->order_id, 'pending');

        $response = $this->postJson('/api/webhooks/midtrans', $payload);

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
        $grossAmount = '100000.00';
        $statusCode = $status === 'settlement' ? '200' : ($status === 'pending' ? '201' : '202');

        // Build signature (without server key in test mode)
        $serverKey = config('services.midtrans.server_key') ?? '';
        $signatureString = $orderId.$statusCode.$grossAmount.$serverKey;
        $signatureKey = hash('sha512', $signatureString);

        return [
            'order_id' => $orderId,
            'transaction_status' => $status,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_id' => 'midtrans-'.uniqid(),
            'payment_type' => 'qris',
            'fraud_status' => 'accept',
            'transaction_time' => now()->toDateTimeString(),
        ];
    }
}
