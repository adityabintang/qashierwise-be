<?php

namespace Tests\Unit\Services;

use App\Models\PaymentProviderCredential;
use App\Models\User;
use App\Services\EncryptionService;
use App\Services\ProviderCredentialService;
use App\Services\ProviderValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProviderCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProviderCredentialService $service;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // Mock the validation service to avoid actual API calls
        $mockValidation = Mockery::mock(ProviderValidationService::class);
        $mockValidation->shouldReceive('validateCredentials')->andReturn(
            new \App\DTOs\ValidationResult(true)
        );

        $this->service = new ProviderCredentialService(
            app(EncryptionService::class),
            $mockValidation
        );
    }

    public function test_can_store_credentials(): void
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $credential = $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            $credentials
        );

        $this->assertInstanceOf(PaymentProviderCredential::class, $credential);
        $this->assertEquals($this->user->id, $credential->user_id);
        $this->assertEquals(PaymentProviderCredential::PROVIDER_DOKU, $credential->provider);
        $this->assertNotEmpty($credential->credentials_encrypted);
    }

    public function test_can_update_credentials(): void
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $credential = $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            $credentials
        );

        $newCredentials = [
            'client_id' => 'new_client_id',
            'secret_key' => 'new_secret_key',
        ];

        $result = $this->service->updateCredentials($credential, $newCredentials);

        $this->assertTrue($result);

        $credential->refresh();
        $this->assertNotEmpty($credential->credentials_encrypted);
    }

    public function test_can_delete_credentials(): void
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $credential = $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            $credentials
        );

        $result = $this->service->deleteCredentials($credential);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('payment_provider_credentials', [
            'id' => $credential->id,
        ]);
    }

    public function test_can_set_active_provider(): void
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            $credentials
        );

        $result = $this->service->setActiveProvider(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU
        );

        $this->assertTrue($result);

        $activeProvider = $this->service->getActiveProvider($this->user);
        $this->assertNotNull($activeProvider);
        $this->assertEquals(PaymentProviderCredential::PROVIDER_DOKU, $activeProvider->provider);
        $this->assertTrue($activeProvider->is_active);
    }

    public function test_only_one_provider_can_be_active(): void
    {
        // Store two providers
        $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            ['client_id' => 'test1', 'secret_key' => 'test1']
        );

        $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_XENDIT,
            ['api_key' => 'test2', 'callback_token' => 'test2']
        );

        // Activate first provider
        $this->service->setActiveProvider($this->user, PaymentProviderCredential::PROVIDER_DOKU);

        // Activate second provider
        $this->service->setActiveProvider($this->user, PaymentProviderCredential::PROVIDER_XENDIT);

        // Check that only Xendit is active
        $activeProvider = $this->service->getActiveProvider($this->user);
        $this->assertEquals(PaymentProviderCredential::PROVIDER_XENDIT, $activeProvider->provider);

        // Check that Doku is not active
        $dokuProvider = PaymentProviderCredential::where('user_id', $this->user->id)
            ->where('provider', PaymentProviderCredential::PROVIDER_DOKU)
            ->first();
        $this->assertFalse($dokuProvider->is_active);
    }

    public function test_cannot_set_active_provider_without_credentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->setActiveProvider(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU
        );
    }

    public function test_can_decrypt_credentials(): void
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $credential = $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            $credentials
        );

        $decrypted = $this->service->decryptCredentials($credential);

        $this->assertEquals($credentials, $decrypted);
    }

    public function test_invalid_provider_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->storeCredentials(
            $this->user,
            'invalid_provider',
            ['key' => 'value']
        );
    }

    public function test_empty_credentials_throw_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->storeCredentials(
            $this->user,
            PaymentProviderCredential::PROVIDER_DOKU,
            []
        );
    }
}
