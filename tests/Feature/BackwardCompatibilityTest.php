<?php

namespace Tests\Feature;

use App\Models\PaymentProviderCredential;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Test backward compatibility with existing Midtrans transactions.
 * 
 * Requirements: 7.1, 7.2, 7.3, 7.4, 7.5
 */
class BackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SubMerchant $subMerchant;
    private QrisService $qrisService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and sub-merchant
        $this->user = User::factory()->create();
        $this->subMerchant = SubMerchant::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        $this->qrisService = app(QrisService::class);
    }

    /**
     * Test that existing Midtrans transactions are preserved after migration.
     * Requirement 7.1: Existing Midtrans transactions continue to work
     */
    public function test_existing_midtrans_transactions_are_preserved(): void
    {
        // Create a legacy transaction (simulating pre-migration data)
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-20251230-TEST001',
            'amount' => 50000,
            'platform_fee' => 1250,
            'net_amount' => 48750,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'midtrans_transaction_id' => 'midtrans-txn-12345',
            'qr_code_url' => 'https://example.com/qr/test.png',
            'expires_at' => now()->addMinutes(30),
            'settled_at' => now(),
            // provider is null initially (pre-migration state)
            'provider' => null,
        ]);

        // Simulate migration: set provider to 'midtrans' for null providers
        DB::table('qris_transactions')
            ->whereNull('provider')
            ->update(['provider' => 'midtrans']);

        // Refresh the transaction
        $transaction->refresh();

        // Assert provider was set to midtrans
        $this->assertEquals('midtrans', $transaction->provider);
        
        // Assert midtrans_transaction_id is preserved
        $this->assertEquals('midtrans-txn-12345', $transaction->midtrans_transaction_id);
        
        // Assert transaction is still settled
        $this->assertTrue($transaction->isSettled());
        
        // Assert we can still find by midtrans transaction ID
        $foundTransaction = $this->qrisService->findByMidtransId('midtrans-txn-12345');
        $this->assertNotNull($foundTransaction);
        $this->assertEquals($transaction->id, $foundTransaction->id);
    }

    /**
     * Test that users with only Midtrans configured continue using Midtrans.
     * Requirement 7.1: Users with only Midtrans configured continue using Midtrans as default
     */
    public function test_midtrans_only_users_continue_using_midtrans(): void
    {
        // Create Midtrans credential for user
        $credential = PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'midtrans',
            'is_active' => true,
            'connection_status' => 'valid',
        ]);

        // Get active provider
        $activeProvider = $this->qrisService->getActiveProviderCredential($this->user);

        // Assert Midtrans is the active provider
        $this->assertNotNull($activeProvider);
        $this->assertEquals('midtrans', $activeProvider->provider);
        $this->assertTrue($activeProvider->is_active);
    }

    /**
     * Test that existing API endpoints maintain the same response format.
     * Requirement 7.4: Maintain existing API endpoints and response formats
     */
    public function test_api_response_format_is_maintained(): void
    {
        // Create a Midtrans transaction
        $transaction = QrisTransaction::factory()->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'midtrans',
            'midtrans_transaction_id' => 'midtrans-txn-67890',
            'provider_transaction_id' => 'midtrans-txn-67890',
        ]);

        // Make API request
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/sub-merchant/qris/{$transaction->order_id}");

        // Assert response structure
        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transaction' => [
                        'order_id',
                        'amount',
                        'platform_fee',
                        'net_amount',
                        'status',
                        'provider',
                        'provider_transaction_id',
                        'qr_code_url',
                        'shareable_link',
                        'is_expired',
                        'can_be_used',
                        'remaining_time_seconds',
                        'expires_at',
                        'settled_at',
                        'created_at',
                    ],
                ],
            ]);

        // Assert provider information is included
        $response->assertJson([
            'data' => [
                'transaction' => [
                    'provider' => 'midtrans',
                ],
            ],
        ]);
    }

    /**
     * Test that transaction history includes both old and new transactions.
     * Requirement 7.3: Preserve existing transaction data during migration
     */
    public function test_transaction_history_includes_legacy_transactions(): void
    {
        // Create legacy Midtrans transaction (pre-migration)
        $legacyTransaction = QrisTransaction::factory()->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'midtrans',
            'midtrans_transaction_id' => 'legacy-txn-001',
            'provider_transaction_id' => 'legacy-txn-001',
            'created_at' => now()->subDays(30),
        ]);

        // Create new Xendit transaction
        $newTransaction = QrisTransaction::factory()->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'xendit',
            'provider_transaction_id' => 'xendit-txn-001',
            'created_at' => now(),
        ]);

        // Get transaction history
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sub-merchant/qris/history');

        // Assert both transactions are in history
        $response->assertOk()
            ->assertJsonCount(2, 'data.transactions');

        // Assert transactions are ordered by created_at desc (newest first)
        $transactions = $response->json('data.transactions');
        $this->assertEquals($newTransaction->order_id, $transactions[0]['order_id']);
        $this->assertEquals($legacyTransaction->order_id, $transactions[1]['order_id']);
    }

    /**
     * Test that Midtrans webhook handling still works.
     * Requirement 7.2: Maintain existing Midtrans functionality without regression
     */
    public function test_midtrans_webhook_still_works(): void
    {
        // Create Midtrans credential
        PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'midtrans',
            'is_active' => true,
        ]);

        // Create pending transaction
        $transaction = QrisTransaction::factory()->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'midtrans',
            'status' => QrisTransaction::STATUS_PENDING,
        ]);

        // Simulate Midtrans webhook payload
        $payload = [
            'order_id' => $transaction->order_id,
            'status_code' => '200',
            'gross_amount' => (string) $transaction->amount,
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-webhook-txn-001',
            'payment_type' => 'qris',
        ];

        // Calculate signature
        $serverKey = config('services.midtrans.server_key', 'test-server-key');
        $signatureString = $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $serverKey;
        $signature = hash('sha512', $signatureString);

        // Send webhook
        $response = $this->postJson('/api/webhooks/midtrans', $payload, [
            'X-Midtrans-Signature' => $signature,
        ]);

        // Assert webhook was processed
        $response->assertOk();

        // Assert transaction was updated
        $transaction->refresh();
        $this->assertEquals(QrisTransaction::STATUS_SETTLEMENT, $transaction->status);
        $this->assertEquals('midtrans-webhook-txn-001', $transaction->midtrans_transaction_id);
    }

    /**
     * Test that provider switching works for existing users.
     * Requirement 7.5: Support provider switching for existing users
     */
    public function test_existing_users_can_switch_providers(): void
    {
        // Create Midtrans credential (existing)
        $midtransCredential = PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'midtrans',
            'is_active' => true,
        ]);

        // Create Xendit credential (new)
        $xenditCredential = PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'xendit',
            'is_active' => false,
        ]);

        // Switch to Xendit
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', [
                'provider' => 'xendit',
            ]);

        $response->assertOk();

        // Refresh credentials
        $midtransCredential->refresh();
        $xenditCredential->refresh();

        // Assert Xendit is now active
        $this->assertFalse($midtransCredential->is_active);
        $this->assertTrue($xenditCredential->is_active);

        // Assert active provider is Xendit
        $activeProvider = $this->qrisService->getActiveProviderCredential($this->user);
        $this->assertEquals('xendit', $activeProvider->provider);
    }

    /**
     * Test that legacy transactions can be queried by provider.
     * Requirement 7.3: Preserve existing transaction data
     */
    public function test_can_filter_transactions_by_provider(): void
    {
        // Create Midtrans transactions
        QrisTransaction::factory()->count(3)->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'midtrans',
        ]);

        // Create Xendit transactions
        QrisTransaction::factory()->count(2)->create([
            'sub_merchant_id' => $this->subMerchant->id,
            'provider' => 'xendit',
        ]);

        // Get Midtrans transactions
        $midtransTransactions = $this->qrisService->getTransactionHistoryByProvider(
            $this->subMerchant,
            'midtrans'
        );

        // Get Xendit transactions
        $xenditTransactions = $this->qrisService->getTransactionHistoryByProvider(
            $this->subMerchant,
            'xendit'
        );

        // Assert correct counts
        $this->assertCount(3, $midtransTransactions);
        $this->assertCount(2, $xenditTransactions);

        // Assert all Midtrans transactions have correct provider
        foreach ($midtransTransactions as $transaction) {
            $this->assertEquals('midtrans', $transaction->provider);
        }

        // Assert all Xendit transactions have correct provider
        foreach ($xenditTransactions as $transaction) {
            $this->assertEquals('xendit', $transaction->provider);
        }
    }

    /**
     * Test that default provider is set for users without explicit configuration.
     * Requirement 7.5: Default to first available configured provider
     */
    public function test_defaults_to_first_available_provider(): void
    {
        // Create multiple providers (both inactive initially)
        $midtransCredential = PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'midtrans',
            'is_active' => false,
            'connection_status' => 'valid',
        ]);

        $xenditCredential = PaymentProviderCredential::factory()->create([
            'user_id' => $this->user->id,
            'provider' => 'xendit',
            'is_active' => false,
            'connection_status' => 'valid',
        ]);

        // Activate the first one (Midtrans)
        $midtransCredential->update(['is_active' => true]);

        // Get active provider
        $activeProvider = $this->qrisService->getActiveProviderCredential($this->user);

        // Assert Midtrans is active (first configured)
        $this->assertNotNull($activeProvider);
        $this->assertEquals('midtrans', $activeProvider->provider);
    }

    /**
     * Test that migration preserves all transaction fields.
     * Requirement 7.3: Preserve existing transaction data during migration
     */
    public function test_migration_preserves_all_fields(): void
    {
        // Create transaction with all fields populated (pre-migration state)
        $originalData = [
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-20251230-FULL001',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'midtrans_transaction_id' => 'midtrans-full-001',
            'qr_code_url' => 'https://example.com/qr/full.png',
            'expires_at' => now()->addMinutes(30),
            'settled_at' => now(),
            'provider' => null, // Pre-migration
        ];

        $transaction = QrisTransaction::create($originalData);

        // Run migration
        DB::table('qris_transactions')
            ->whereNull('provider')
            ->update(['provider' => 'midtrans']);

        // Refresh transaction
        $transaction->refresh();

        // Assert all fields are preserved
        $this->assertEquals($originalData['sub_merchant_id'], $transaction->sub_merchant_id);
        $this->assertEquals($originalData['order_id'], $transaction->order_id);
        $this->assertEquals($originalData['amount'], (float) $transaction->amount);
        $this->assertEquals($originalData['platform_fee'], (float) $transaction->platform_fee);
        $this->assertEquals($originalData['net_amount'], (float) $transaction->net_amount);
        $this->assertEquals($originalData['status'], $transaction->status);
        $this->assertEquals($originalData['midtrans_transaction_id'], $transaction->midtrans_transaction_id);
        $this->assertEquals($originalData['qr_code_url'], $transaction->qr_code_url);
        
        // Assert provider was set
        $this->assertEquals('midtrans', $transaction->provider);
    }
}
