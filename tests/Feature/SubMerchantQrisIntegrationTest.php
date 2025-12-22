<?php

namespace Tests\Feature;

use App\Jobs\ProcessQrisPayment;
use App\Models\MerchantBalance;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BalanceService;
use App\Services\QrisService;
use App\Services\SubMerchantService;
use App\Services\WithdrawalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * End-to-end integration tests for the Sub-Merchant QRIS System.
 * 
 * Tests complete flows:
 * - QRIS generation to payment settlement
 * - Withdrawal request to approval workflow
 * - Admin panel functionality
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
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Test Merchant',
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
        $signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
        $signatureKey = hash('sha512', $signatureString);

        return [
            'order_id' => $orderId,
            'transaction_status' => $status,
            'status_code' => $statusCode,
            'gross_amount' => $grossAmount,
            'signature_key' => $signatureKey,
            'transaction_id' => 'midtrans-' . uniqid(),
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
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Test Merchant',
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
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-TEST1234',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        // Process the payment
        $job = new ProcessQrisPayment($transaction, []);
        $job->handle(app(BalanceService::class));

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

        // Process first payment
        $transaction1 = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-TEST0001',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $job1 = new ProcessQrisPayment($transaction1, []);
        $job1->handle($balanceService);

        // Process second payment
        $transaction2 = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-TEST0002',
            'amount' => 50000,
            'platform_fee' => 1250,
            'net_amount' => 48750,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $job2 = new ProcessQrisPayment($transaction2, []);
        $job2->handle($balanceService);

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
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-EXPIRED',
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
    // Withdrawal Request to Approval Workflow
    // ==========================================

    /** @test */
    public function complete_withdrawal_flow_from_request_to_approval(): void
    {
        $this->createSubMerchant();

        // Give merchant some balance
        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        // Step 1: Confirm password for withdrawal
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals/confirm-password', [
                'password' => 'password', // Default factory password
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['confirmed' => true]]);

        // Step 2: Submit withdrawal request
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals', [
                'amount' => 50000,
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $withdrawalId = $response->json('data.withdrawal.id');

        // Verify withdrawal was created and balance deducted
        $withdrawal = WithdrawalRequest::find($withdrawalId);
        $this->assertNotNull($withdrawal);
        $this->assertEquals(50000, $withdrawal->amount);
        $this->assertEquals(WithdrawalRequest::STATUS_PENDING, $withdrawal->status);

        $this->balance->refresh();
        $this->assertEquals(50000, $this->balance->available_balance); // 100000 - 50000

        // Step 3: Admin approves withdrawal
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/withdrawals/{$withdrawalId}/approve", [
                'notes' => 'Approved for processing',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify withdrawal status updated
        $withdrawal->refresh();
        $this->assertEquals(WithdrawalRequest::STATUS_APPROVED, $withdrawal->status);
        $this->assertNotNull($withdrawal->processed_at);
        $this->assertEquals($this->adminUser->id, $withdrawal->processed_by);

        // Step 4: Admin marks as processed
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/withdrawals/{$withdrawalId}/mark-processed");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify final status
        $withdrawal->refresh();
        $this->assertEquals(WithdrawalRequest::STATUS_PROCESSED, $withdrawal->status);
    }

    /** @test */
    public function withdrawal_rejection_returns_balance_to_merchant(): void
    {
        $this->createSubMerchant();

        // Give merchant some balance
        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        // Create withdrawal request using service
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);

        // Verify balance was deducted
        $this->balance->refresh();
        $this->assertEquals(50000, $this->balance->available_balance);

        // Admin rejects withdrawal
        $response = $this->actingAs($this->adminUser)
            ->postJson("/api/admin/withdrawals/{$withdrawal->id}/reject", [
                'reason' => 'Invalid bank account details provided',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify balance was returned
        $this->balance->refresh();
        $this->assertEquals(100000, $this->balance->available_balance);

        // Verify withdrawal status
        $withdrawal->refresh();
        $this->assertEquals(WithdrawalRequest::STATUS_REJECTED, $withdrawal->status);
        $this->assertStringContainsString('Invalid bank account', $withdrawal->admin_notes);
    }

    /** @test */
    public function withdrawal_validation_enforces_minimum_amount(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        // Confirm password first
        $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals/confirm-password', [
                'password' => 'password',
            ]);

        // Try to withdraw less than minimum (Rp 10,000)
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals', [
                'amount' => 5000,
            ]);

        $response->assertStatus(422);

        // Verify balance unchanged
        $this->balance->refresh();
        $this->assertEquals(100000, $this->balance->available_balance);
    }

    /** @test */
    public function withdrawal_validation_enforces_sufficient_balance(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 20000,
            'total_earned' => 20000,
        ]);

        // Confirm password first
        $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals/confirm-password', [
                'password' => 'password',
            ]);

        // Try to withdraw more than available
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals', [
                'amount' => 50000,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'WITHDRAWAL_VALIDATION_FAILED');

        // Verify balance unchanged
        $this->balance->refresh();
        $this->assertEquals(20000, $this->balance->available_balance);
    }

    /** @test */
    public function merchant_can_cancel_pending_withdrawal(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        // Create withdrawal request
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);

        // Verify balance was deducted
        $this->balance->refresh();
        $this->assertEquals(50000, $this->balance->available_balance);

        // Confirm password
        $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals/confirm-password', [
                'password' => 'password',
            ]);

        // Cancel withdrawal
        $response = $this->actingAs($this->user)
            ->postJson("/api/sub-merchant/withdrawals/{$withdrawal->id}/cancel");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify balance was returned
        $this->balance->refresh();
        $this->assertEquals(100000, $this->balance->available_balance);
    }

    // ==========================================
    // Admin Panel Functionality
    // ==========================================

    /** @test */
    public function admin_can_list_pending_withdrawals(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 200000,
            'total_earned' => 200000,
        ]);

        // Create multiple withdrawal requests
        $withdrawalService = app(WithdrawalService::class);
        $withdrawal1 = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);
        $withdrawal2 = $withdrawalService->createWithdrawalRequest($this->subMerchant, 30000, false);

        // Admin lists pending withdrawals
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/withdrawals/pending');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(2, 'data.withdrawals');
    }

    /** @test */
    public function admin_can_view_withdrawal_details(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);

        // Admin views withdrawal details
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/withdrawals/{$withdrawal->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.withdrawal.amount', 50000)
            ->assertJsonPath('data.withdrawal.status', 'pending')
            ->assertJsonStructure([
                'data' => [
                    'withdrawal' => [
                        'id',
                        'amount',
                        'status',
                        'bank_details' => ['bank_name', 'account_number', 'account_holder_name'],
                        'sub_merchant' => ['id', 'user' => ['name', 'email']],
                    ],
                ],
            ]);
    }

    /** @test */
    public function admin_can_view_withdrawal_statistics(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 500000,
            'total_earned' => 500000,
        ]);

        // Create withdrawals with different statuses
        $withdrawalService = app(WithdrawalService::class);
        
        $pending = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);
        $toApprove = $withdrawalService->createWithdrawalRequest($this->subMerchant, 30000, false);
        
        // Approve one
        $withdrawalService->approveWithdrawal($toApprove, $this->adminUser, null, false);

        // Admin views statistics
        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/withdrawals/stats');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'stats' => [
                        'pending' => ['count', 'amount'],
                        'approved' => ['count', 'amount'],
                        'processed' => ['count', 'amount'],
                        'rejected' => ['count', 'amount'],
                        'total' => ['count', 'amount'],
                    ],
                ],
            ]);

        $this->assertEquals(1, $response->json('data.stats.pending.count'));
        $this->assertEquals(1, $response->json('data.stats.approved.count'));
    }

    /** @test */
    public function admin_can_view_withdrawal_audit_trail(): void
    {
        $this->createSubMerchant();

        $this->balance->update([
            'available_balance' => 100000,
            'total_earned' => 100000,
        ]);

        $withdrawalService = app(WithdrawalService::class);
        $withdrawal = $withdrawalService->createWithdrawalRequest($this->subMerchant, 50000, false);
        
        // Approve the withdrawal
        $withdrawalService->approveWithdrawal($withdrawal, $this->adminUser, 'Verified bank details', false);

        // Admin views audit trail
        $response = $this->actingAs($this->adminUser)
            ->getJson("/api/admin/withdrawals/{$withdrawal->id}/audit-trail");

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'data' => [
                    'withdrawal_id',
                    'current_status',
                    'audit_trail' => [
                        ['action', 'timestamp', 'user', 'details'],
                    ],
                ],
            ]);
    }

    // ==========================================
    // Complete End-to-End Flow
    // ==========================================

    /** @test */
    public function complete_merchant_lifecycle_from_registration_to_withdrawal(): void
    {
        Queue::fake();

        // 1. User registers as sub-merchant
        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/register', [
                'bank_name' => 'BCA',
                'account_number' => '9876543210',
                'account_holder_name' => 'Test Merchant',
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
        $job->handle(app(BalanceService::class));

        // 5. Check balance
        $response = $this->actingAs($this->user)
            ->getJson('/api/sub-merchant/balance');
        $response->assertStatus(200);
        
        $expectedBalance = 200000 - (200000 * 0.025); // 195000
        $this->assertEquals($expectedBalance, $response->json('data.balance.available'));

        // 6. Confirm password and request withdrawal
        $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals/confirm-password', [
                'password' => 'password',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/sub-merchant/withdrawals', [
                'amount' => 100000,
            ]);
        $response->assertStatus(201);
        $withdrawalId = $response->json('data.withdrawal.id');

        // 7. Admin approves and processes withdrawal
        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/withdrawals/{$withdrawalId}/approve");

        $this->actingAs($this->adminUser)
            ->postJson("/api/admin/withdrawals/{$withdrawalId}/mark-processed");

        // 8. Verify final state
        $subMerchant = SubMerchant::where('user_id', $this->user->id)->first();
        $subMerchant->load('balance');
        
        $this->assertEquals(95000, $subMerchant->balance->available_balance); // 195000 - 100000
        $this->assertEquals(100000, $subMerchant->balance->total_withdrawn);

        $withdrawal = WithdrawalRequest::find($withdrawalId);
        $this->assertEquals(WithdrawalRequest::STATUS_PROCESSED, $withdrawal->status);
    }
}
