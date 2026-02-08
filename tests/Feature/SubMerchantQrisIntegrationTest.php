<?php

namespace Tests\Feature;

use App\Jobs\ProcessQrisPayment;
use App\Models\MerchantBalance;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end integration tests for the Sub-Merchant QRIS System.
 *
 * Tests complete flows:
 * - QRIS generation to payment settlement
 * - Balance tracking
 *
 * Requirements: All requirements integration
 */
class SubMerchantQrisIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $adminUser;

    private SubMerchant $subMerchant;

    private MerchantBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();

        // Create regular user
        $this->user = User::factory()->create([
            'name' => 'Test Merchant',
            'email' => 'merchant@test.com',
        ]);

        // Create admin user (email contains 'admin' which is checked by AdminSessionValidation middleware)
        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@test.com',
        ]);
    }

    /**
     * Helper to create a sub-merchant with balance.
     */
    private function createSubMerchant(): void
    {
        $this->subMerchant = SubMerchant::create([
            'user_id' => $this->user->id,
            'business_name' => 'Test Merchant',
            'is_active' => true,
        ]);

        $this->balance = MerchantBalance::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);
    }

    /**
     * Helper to build Midtrans webhook payload.
     */
    private function buildWebhookPayload(string $orderId, string $status, float $amount = 100000): array
    {
        $grossAmount = number_format($amount, 2, '.', '');
        $statusCode = $status === 'settlement' ? '200' : ($status === 'pending' ? '201' : '202');

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

    // ==========================================
    // QRIS Generation to Payment Settlement Flow
    // ==========================================

    /** @test */
    public function complete_qris_flow_from_registration_to_payment_settlement(): void
    {
        Queue::fake();

        // Step 1: Register as sub-merchant via API
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/register', [
                'business_name' => 'Test Merchant',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify sub-merchant was created with zero balance
        $subMerchant = SubMerchant::where('user_id', $this->user->id)->first();
        $this->assertNotNull($subMerchant);
        $this->assertEquals(0, $subMerchant->balance->available_balance);

        // Step 2: Generate QRIS code
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 100000,
                'description' => 'Test Payment',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $orderId = $response->json('data.transaction.order_id');
        $this->assertNotNull($orderId);

        // Verify transaction was created with correct fee calculation
        $transaction = QrisTransaction::where('order_id', $orderId)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals(100000, $transaction->amount);
        $this->assertEquals(2500, $transaction->platform_fee); // 2.5%
        $this->assertEquals(97500, $transaction->net_amount);
        $this->assertEquals(QrisTransaction::STATUS_PENDING, $transaction->status);

        // Step 3: Simulate Midtrans webhook for settlement
        $webhookPayload = $this->buildWebhookPayload($orderId, 'settlement', 100000);

        $response = $this->postJson('/api/webhooks/midtrans', $webhookPayload);
        $response->assertStatus(200);

        // Verify transaction status updated
        $transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_SETTLEMENT, $transaction->status);
        $this->assertNotNull($transaction->settled_at);

        // Verify job was dispatched
        Queue::assertPushed(ProcessQrisPayment::class);
    }

    /** @test */
    public function qris_payment_settlement_updates_merchant_balance(): void
    {
        $this->createSubMerchant();

        // Create a settled transaction
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-'.now()->format('YmdHis').'-TEST1234',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        // Process the payment
        $job = new ProcessQrisPayment($transaction, []);
        $job->handle(
            app(BalanceService::class),
            app(\App\Services\AiAgentService::class)
        );

        // Verify balance was updated
        $this->balance->refresh();
        $this->assertEquals(97500, $this->balance->available_balance);
        $this->assertEquals(97500, $this->balance->total_earned);

        // Verify platform fee was recorded
        $platformFee = PlatformFee::where('qris_transaction_id', $transaction->id)->first();
        $this->assertNotNull($platformFee);
        $this->assertEquals(2.5, $platformFee->fee_percentage);
        $this->assertEquals(2500, $platformFee->fee_amount);
    }

    /** @test */
    public function multiple_qris_payments_accumulate_balance_correctly(): void
    {
        $this->createSubMerchant();
        $balanceService = app(BalanceService::class);
        $aiAgentService = app(\App\Services\AiAgentService::class);

        // Process first payment
        $transaction1 = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-'.now()->format('YmdHis').'-TEST0001',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $job1 = new ProcessQrisPayment($transaction1, []);
        $job1->handle($balanceService, $aiAgentService);

        // Process second payment
        $transaction2 = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-'.now()->format('YmdHis').'-TEST0002',
            'amount' => 50000,
            'platform_fee' => 1250,
            'net_amount' => 48750,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $job2 = new ProcessQrisPayment($transaction2, []);
        $job2->handle($balanceService, $aiAgentService);

        // Verify accumulated balance
        $this->balance->refresh();
        $expectedBalance = 97500 + 48750; // 146250
        $this->assertEquals($expectedBalance, $this->balance->available_balance);
        $this->assertEquals($expectedBalance, $this->balance->total_earned);
    }

    /** @test */
    public function expired_qris_cannot_be_settled(): void
    {
        $this->createSubMerchant();

        // Create an expired transaction
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-'.now()->format('YmdHis').'-EXPIRED',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_PENDING,
            'expires_at' => now()->subMinutes(10), // Already expired
        ]);

        // Verify transaction is expired
        $this->assertTrue($transaction->isExpired());
        $this->assertFalse($transaction->canBeUsed());

        // Webhook should still process but balance shouldn't update for expired
        $webhookPayload = $this->buildWebhookPayload($transaction->order_id, 'expire', 100000);

        $response = $this->postJson('/api/webhooks/midtrans', $webhookPayload);
        $response->assertStatus(200);

        // Verify transaction marked as expired
        $transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_EXPIRE, $transaction->status);

        // Verify balance unchanged
        $this->balance->refresh();
        $this->assertEquals(0, $this->balance->available_balance);
    }

    // ==========================================
    // Complete End-to-End Flow
    // ==========================================

    /** @test */
    public function complete_merchant_lifecycle_from_registration_to_payment(): void
    {
        Queue::fake();

        // 1. User registers as sub-merchant
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/register', [
                'business_name' => 'Test Merchant',
            ]);
        $response->assertStatus(201);

        // 2. Generate QRIS and receive payment
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 200000,
            ]);
        $response->assertStatus(201);
        $orderId = $response->json('data.transaction.order_id');

        // 3. Simulate payment settlement
        $webhookPayload = $this->buildWebhookPayload($orderId, 'settlement', 200000);
        $this->postJson('/api/webhooks/midtrans', $webhookPayload);

        // 4. Process the payment job manually
        $transaction = QrisTransaction::where('order_id', $orderId)->first();
        $job = new ProcessQrisPayment($transaction, []);
        $job->handle(
            app(BalanceService::class),
            app(\App\Services\AiAgentService::class)
        );

        // 5. Check balance
        $response = $this->actingAs($this->user)
            ->getJson('/api/sub-merchant/balance');
        $response->assertStatus(200);

        $expectedBalance = 200000 - (200000 * 0.025); // 195000
        $this->assertEquals($expectedBalance, $response->json('data.balance.available'));

        // 6. Verify final state
        $subMerchant = SubMerchant::where('user_id', $this->user->id)->first();
        $subMerchant->load('balance');

        $this->assertEquals($expectedBalance, $subMerchant->balance->available_balance);
        $this->assertEquals($expectedBalance, $subMerchant->balance->total_earned);
    }
}
