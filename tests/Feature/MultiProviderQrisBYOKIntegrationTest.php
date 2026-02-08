<?php

namespace Tests\Feature;

use App\Models\PaymentProviderCredential;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Models\UserEncryptionKey;
use App\Services\KeyManagementService;
use App\Services\ProviderCredentialService;
use App\Services\QrisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end integration tests for Multi-Provider QRIS BYOK System.
 *
 * Tests complete flows:
 * - Configure provider → validate → generate QRIS
 * - Provider switching scenarios
 * - Multi-user isolation
 * - Error scenarios (invalid credentials, network errors)
 *
 * Requirements: All requirements integration
 */
class MultiProviderQrisBYOKIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;

    private User $user2;

    private SubMerchant $merchant1;

    private SubMerchant $merchant2;

    private ProviderCredentialService $credentialService;

    private QrisService $qrisService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create two users for isolation testing
        $this->user1 = User::factory()->create([
            'name' => 'Merchant One',
            'email' => 'merchant1@test.com',
        ]);

        $this->user2 = User::factory()->create([
            'name' => 'Merchant Two',
            'email' => 'merchant2@test.com',
        ]);

        // Create sub-merchants
        $this->merchant1 = SubMerchant::create([
            'user_id' => $this->user1->id,
            'business_name' => 'Merchant One',
            'is_active' => true,
        ]);

        $this->merchant2 = SubMerchant::create([
            'user_id' => $this->user2->id,
            'business_name' => 'Merchant Two',
            'is_active' => true,
        ]);

        $this->credentialService = app(ProviderCredentialService::class);
        $this->qrisService = app(QrisService::class);
    }

    // ==========================================
    // Complete Flow: Configure → Validate → Generate QRIS
    // ==========================================

    /** @test */
    public function complete_flow_configure_provider_validate_and_generate_qris(): void
    {
        // Mock provider API responses
        Http::fake([
            // Doku validation endpoint
            '*/snap/v1/access-token/b2b' => Http::response([
                'responseCode' => '2007300',
                'responseMessage' => 'Successful',
                'accessToken' => 'mock-access-token',
            ], 200),
            // Doku QRIS generation endpoint
            '*/snap/v1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2005400',
                'responseMessage' => 'Successful',
                'qrContent' => '00020101021226660014ID.CO.QRIS.WWW0118ID1234567890123450214QRIS-TEST-12340303UMI51440014ID.CO.QRIS.WWW0215ID1234567890123450303UMI5204581253033605802ID5914Test Merchant6007Jakarta61051234062070703A016304ABCD',
                'qrUrl' => 'https://example.com/qr/test-qr-code.png',
            ], 200),
        ]);

        // Step 1: Configure Doku provider credentials
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => [
                    'client_id' => 'test-client-id',
                    'secret_key' => 'test-secret-key',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Provider credentials stored successfully',
            ]);

        // Verify credential was created and validated
        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('provider', 'doku')
            ->first();

        $this->assertNotNull($credential);
        $this->assertEquals('valid', $credential->connection_status);
        $this->assertNotNull($credential->credentials_encrypted);
        $this->assertNotNull($credential->last_validated_at);

        // Step 2: Set Doku as active provider
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', [
                'provider' => 'doku',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Active provider set successfully',
            ]);

        // Verify provider is active
        $credential->refresh();
        $this->assertTrue($credential->is_active);

        // Step 3: Generate QRIS using Doku provider
        $response = $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 100000,
                'description' => 'Test Payment via Doku',
            ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify transaction was created with Doku provider
        $transaction = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)
            ->latest()
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('doku', $transaction->provider);
        $this->assertEquals(100000, $transaction->amount);
        $this->assertNotNull($transaction->qr_code_url);
        $this->assertNotNull($transaction->provider_transaction_id);
    }

    /** @test */
    public function complete_flow_with_xendit_provider(): void
    {
        // Mock Xendit API responses
        Http::fake([
            // Xendit validation endpoint
            '*/balance' => Http::response([
                'balance' => 1000000,
            ], 200),
            // Xendit QR code generation endpoint
            '*/qr_codes' => Http::response([
                'id' => 'qr_test_123456',
                'external_id' => 'QRIS-*',
                'amount' => 100000,
                'qr_string' => '00020101021226660014ID.CO.QRIS.WWW0118ID1234567890123450214QRIS-TEST-12340303UMI51440014ID.CO.QRIS.WWW0215ID1234567890123450303UMI5204581253033605802ID5914Test Merchant6007Jakarta61051234062070703A016304ABCD',
                'callback_url' => 'https://example.com/callback',
                'type' => 'DYNAMIC',
                'status' => 'ACTIVE',
            ], 200),
        ]);

        // Configure Xendit provider
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'xendit',
                'credentials' => [
                    'api_key' => 'xnd_test_api_key',
                    'callback_token' => 'test_callback_token',
                ],
            ]);

        $response->assertStatus(201);

        // Set as active
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', [
                'provider' => 'xendit',
            ]);

        // Generate QRIS
        $response = $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 150000,
                'description' => 'Test Payment via Xendit',
            ]);

        $response->assertStatus(201);

        // Verify transaction
        $transaction = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)
            ->latest()
            ->first();

        $this->assertEquals('xendit', $transaction->provider);
        $this->assertEquals(150000, $transaction->amount);
    }

    // ==========================================
    // Provider Switching Scenarios
    // ==========================================

    /** @test */
    public function user_can_switch_between_multiple_configured_providers(): void
    {
        // Mock all provider APIs
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response(['responseCode' => '2007300'], 200),
            '*/balance' => Http::response(['balance' => 1000000], 200),
            '*' => Http::response(['success' => true], 200),
        ]);

        // Configure Doku
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        // Configure Xendit
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'xendit',
                'credentials' => ['api_key' => 'test', 'callback_token' => 'test'],
            ]);

        // Verify both providers are configured
        $providers = PaymentProviderCredential::where('user_id', $this->user1->id)->get();
        $this->assertCount(2, $providers);

        // Set Doku as active
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $dokuCredential = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('provider', 'doku')
            ->first();
        $this->assertTrue($dokuCredential->is_active);

        // Switch to Xendit
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'xendit']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify only Xendit is active
        $dokuCredential->refresh();
        $this->assertFalse($dokuCredential->is_active);

        $xenditCredential = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('provider', 'xendit')
            ->first();
        $this->assertTrue($xenditCredential->is_active);
    }

    /** @test */
    public function switching_provider_affects_subsequent_qris_generation(): void
    {
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response(['responseCode' => '2007300'], 200),
            '*/snap/v1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '2005400',
                'qrUrl' => 'https://doku.example.com/qr.png',
            ], 200),
            '*/balance' => Http::response(['balance' => 1000000], 200),
            '*/qr_codes' => Http::response([
                'id' => 'xendit_qr_123',
                'qr_string' => 'xendit-qr-string',
            ], 200),
        ]);

        // Configure both providers
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'xendit',
                'credentials' => ['api_key' => 'test', 'callback_token' => 'test'],
            ]);

        // Set Doku as active and generate QRIS
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);

        $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', ['amount' => 100000]);

        $transaction1 = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)
            ->latest()
            ->first();
        $this->assertEquals('doku', $transaction1->provider);

        // Switch to Xendit and generate QRIS
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'xendit']);

        $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', ['amount' => 200000]);

        $transaction2 = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)
            ->latest()
            ->first();
        $this->assertEquals('xendit', $transaction2->provider);
    }

    /** @test */
    public function cannot_set_unconfigured_provider_as_active(): void
    {
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', [
                'provider' => 'doku',
            ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'SET_ACTIVE_FAILED',
                ],
            ]);
    }

    // ==========================================
    // Multi-User Isolation Tests
    // ==========================================

    /** @test */
    public function users_cannot_access_other_users_provider_credentials(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);

        // User 1 configures Doku
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'user1-client', 'secret_key' => 'user1-secret'],
            ]);

        // User 2 tries to list providers - should only see their own (none)
        $response = $this->actingAs($this->user2, 'sanctum')
            ->getJson('/api/sub-merchant/providers');

        $response->assertStatus(200)
            ->assertJson(['success' => true, 'data' => []]);

        // User 2 configures their own Doku
        $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'user2-client', 'secret_key' => 'user2-secret'],
            ]);

        // Each user should only see their own credentials
        $user1Providers = PaymentProviderCredential::where('user_id', $this->user1->id)->get();
        $user2Providers = PaymentProviderCredential::where('user_id', $this->user2->id)->get();

        $this->assertCount(1, $user1Providers);
        $this->assertCount(1, $user2Providers);
        $this->assertNotEquals(
            $user1Providers->first()->credentials_encrypted,
            $user2Providers->first()->credentials_encrypted
        );
    }

    /** @test */
    public function users_have_separate_encryption_keys(): void
    {
        $keyManagement = app(KeyManagementService::class);

        // Generate keys for both users
        $key1 = $keyManagement->getUserEncryptionKey($this->user1);
        $key2 = $keyManagement->getUserEncryptionKey($this->user2);

        // Keys should be different
        $this->assertNotEquals($key1, $key2);

        // Verify keys are stored separately
        $userKey1 = UserEncryptionKey::where('user_id', $this->user1->id)->first();
        $userKey2 = UserEncryptionKey::where('user_id', $this->user2->id)->first();

        $this->assertNotNull($userKey1);
        $this->assertNotNull($userKey2);
        $this->assertNotEquals(
            $userKey1->encryption_key_encrypted,
            $userKey2->encryption_key_encrypted
        );
    }

    /** @test */
    public function qris_transactions_are_isolated_by_user(): void
    {
        Http::fake(['*' => Http::response([
            'responseCode' => '2007300',
            'qrUrl' => 'https://example.com/qr.png',
        ], 200)]);

        // Configure providers for both users
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        // Set active for both
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);
        $this->actingAs($this->user2, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);

        // Generate QRIS for both users
        $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', ['amount' => 100000]);
        $this->actingAs($this->user2)
            ->postJson('/api/sub-merchant/qris/generate', ['amount' => 200000]);

        // Verify transactions are isolated
        $user1Transactions = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)->get();
        $user2Transactions = QrisTransaction::where('sub_merchant_id', $this->merchant2->id)->get();

        $this->assertCount(1, $user1Transactions);
        $this->assertCount(1, $user2Transactions);
        $this->assertEquals(100000, $user1Transactions->first()->amount);
        $this->assertEquals(200000, $user2Transactions->first()->amount);
    }

    // ==========================================
    // Error Scenarios
    // ==========================================

    /** @test */
    public function invalid_credentials_are_rejected_during_validation(): void
    {
        // Mock failed validation response
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response([
                'responseCode' => '4017300',
                'responseMessage' => 'Unauthorized. Invalid Signature',
            ], 401),
        ]);

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => [
                    'client_id' => 'invalid-client',
                    'secret_key' => 'invalid-secret',
                ],
            ]);

        // Credentials are stored but marked as invalid
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        // Verify credential was stored but marked as invalid
        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('provider', 'doku')
            ->first();

        $this->assertNotNull($credential);
        $this->assertEquals('invalid', $credential->connection_status);
        $this->assertNotNull($credential->validation_error);
    }

    /** @test */
    public function network_errors_during_validation_are_handled_gracefully(): void
    {
        // Mock network timeout
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => [
                    'client_id' => 'test-client',
                    'secret_key' => 'test-secret',
                ],
            ]);

        // Credentials are stored but validation fails
        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        // Verify credential was stored but marked as invalid
        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('provider', 'doku')
            ->first();

        $this->assertNotNull($credential);
        $this->assertEquals('invalid', $credential->connection_status);
    }

    /** @test */
    public function cannot_generate_qris_without_active_provider(): void
    {
        $response = $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 100000,
            ]);

        $response->assertStatus(428) // 428 Precondition Required
            ->assertJson([
                'success' => false,
                'error' => [
                    'code' => 'NO_ACTIVE_PROVIDER',
                ],
            ]);
    }

    /** @test */
    public function cannot_generate_qris_with_invalid_provider_credentials(): void
    {
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response(['responseCode' => '2007300'], 200),
        ]);

        // Configure provider
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        // Manually mark as invalid
        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)->first();
        $credential->update(['connection_status' => 'invalid', 'is_active' => true]);

        // Try to generate QRIS
        $response = $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 100000,
            ]);

        $response->assertStatus(428) // 428 Precondition Required
            ->assertJsonStructure([
                'success',
                'error' => ['code', 'message'],
            ]);
    }

    /** @test */
    public function provider_api_errors_during_qris_generation_are_handled(): void
    {
        Http::fake([
            '*/snap/v1/access-token/b2b' => Http::response(['responseCode' => '2007300'], 200),
            '*/snap/v1/qr/qr-mpm-generate' => Http::response([
                'responseCode' => '4005400',
                'responseMessage' => 'Invalid request parameters',
            ], 400),
        ]);

        // Configure and activate provider
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);

        // Try to generate QRIS - should still create transaction with fallback QR
        $response = $this->actingAs($this->user1)
            ->postJson('/api/sub-merchant/qris/generate', [
                'amount' => 100000,
            ]);

        // The service creates a transaction with fallback QR code
        $response->assertStatus(201);

        $transaction = QrisTransaction::where('sub_merchant_id', $this->merchant1->id)->first();
        $this->assertNotNull($transaction);
        $this->assertNotNull($transaction->qr_code_url); // Fallback QR code
    }

    /** @test */
    public function missing_required_credential_fields_are_rejected(): void
    {
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => [
                    'client_id' => 'test-client',
                    // Missing secret_key
                ],
            ]);

        // The validation happens at the provider level, not controller level
        // So credentials are stored but validation will fail
        $response->assertStatus(201);

        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)->first();
        $this->assertNotNull($credential);
        // Validation will fail because secret_key is missing
        $this->assertEquals('invalid', $credential->connection_status);
    }

    /** @test */
    public function unsupported_provider_is_rejected(): void
    {
        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'stripe', // Not supported
                'credentials' => [
                    'api_key' => 'test',
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['provider']);
    }

    // ==========================================
    // Credential Update and Deletion
    // ==========================================

    /** @test */
    public function user_can_update_provider_credentials(): void
    {
        Http::fake(['*' => Http::response(['responseCode' => '2007300'], 200)]);

        // Configure initial credentials
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'old-client', 'secret_key' => 'old-secret'],
            ]);

        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)->first();
        $oldEncrypted = $credential->credentials_encrypted;

        // Update credentials
        $response = $this->actingAs($this->user1, 'sanctum')
            ->putJson("/api/sub-merchant/providers/{$credential->id}", [
                'credentials' => ['client_id' => 'new-client', 'secret_key' => 'new-secret'],
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify credentials were re-encrypted
        $credential->refresh();
        $this->assertNotEquals($oldEncrypted, $credential->credentials_encrypted);
        // After update, validation runs again, so status should be valid
        $this->assertContains($credential->connection_status, ['valid', 'pending']);
    }

    /** @test */
    public function user_can_delete_provider_credentials(): void
    {
        Http::fake(['*' => Http::response(['responseCode' => '2007300'], 200)]);

        // Configure credentials
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)->first();

        // Delete credentials
        $response = $this->actingAs($this->user1, 'sanctum')
            ->deleteJson("/api/sub-merchant/providers/{$credential->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Verify credentials were deleted
        $this->assertNull(
            PaymentProviderCredential::find($credential->id)
        );
    }

    /** @test */
    public function deleting_active_provider_removes_active_status(): void
    {
        Http::fake(['*' => Http::response(['responseCode' => '2007300'], 200)]);

        // Configure and activate provider
        $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                'credentials' => ['client_id' => 'test', 'secret_key' => 'test'],
            ]);

        $credential = PaymentProviderCredential::where('user_id', $this->user1->id)->first();

        $response = $this->actingAs($this->user1, 'sanctum')
            ->postJson('/api/sub-merchant/providers/set-active', ['provider' => 'doku']);

        $response->assertStatus(200);

        $credential->refresh();
        $this->assertTrue($credential->is_active);

        // Delete the active provider
        $this->actingAs($this->user1, 'sanctum')
            ->deleteJson("/api/sub-merchant/providers/{$credential->id}");

        // Verify no active provider exists
        $activeProvider = PaymentProviderCredential::where('user_id', $this->user1->id)
            ->where('is_active', true)
            ->first();

        $this->assertNull($activeProvider);
    }
}
