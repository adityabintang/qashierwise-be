<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\PaymentProviderCredential;
use App\Services\ProviderValidationService;
use App\Services\EncryptionService;
use App\Services\KeyManagementService;
use App\Services\PaymentProviders\ProviderFactory;
use App\Contracts\PaymentProviderInterface;
use App\DTOs\ValidationResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ProviderValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ProviderValidationService $service;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_can_validate_credentials_successfully(): void
    {
        // Create a credential
        $credential = $this->createCredential();

        // Mock provider to return success
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(ValidationResult::success());

        $mockFactory = Mockery::mock(ProviderFactory::class);
        $mockFactory->shouldReceive('make')
            ->with(PaymentProviderCredential::PROVIDER_DOKU)
            ->andReturn($mockProvider);

        $service = new ProviderValidationService(
            $mockFactory,
            app(EncryptionService::class)
        );

        $result = $service->validateCredentials($credential);

        $this->assertTrue($result->isValid);
        
        $credential->refresh();
        $this->assertEquals(PaymentProviderCredential::STATUS_VALID, $credential->connection_status);
        $this->assertNotNull($credential->last_validated_at);
    }

    public function test_can_validate_credentials_with_failure(): void
    {
        // Create a credential
        $credential = $this->createCredential();

        // Mock provider to return failure
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(ValidationResult::failure('Invalid credentials', 'auth_error'));

        $mockFactory = Mockery::mock(ProviderFactory::class);
        $mockFactory->shouldReceive('make')
            ->with(PaymentProviderCredential::PROVIDER_DOKU)
            ->andReturn($mockProvider);

        $service = new ProviderValidationService(
            $mockFactory,
            app(EncryptionService::class)
        );

        $result = $service->validateCredentials($credential);

        $this->assertFalse($result->isValid);
        $this->assertEquals('Invalid credentials', $result->errorMessage);
        
        $credential->refresh();
        $this->assertEquals(PaymentProviderCredential::STATUS_INVALID, $credential->connection_status);
        $this->assertEquals('Invalid credentials', $credential->validation_error);
        $this->assertNotNull($credential->last_validated_at);
    }

    public function test_can_update_connection_status(): void
    {
        $credential = $this->createCredential();

        $service = new ProviderValidationService(
            app(ProviderFactory::class),
            app(EncryptionService::class)
        );

        $validationResult = ValidationResult::success();
        $result = $service->updateConnectionStatus($credential, $validationResult);

        $this->assertTrue($result);
        
        $credential->refresh();
        $this->assertEquals(PaymentProviderCredential::STATUS_VALID, $credential->connection_status);
        $this->assertNull($credential->validation_error);
    }

    public function test_can_capture_provider_error(): void
    {
        $credential = $this->createCredential();

        $service = new ProviderValidationService(
            app(ProviderFactory::class),
            app(EncryptionService::class)
        );

        $errorMessage = 'API key is invalid';
        $result = $service->captureProviderError($credential, $errorMessage);

        $this->assertTrue($result);
        
        $credential->refresh();
        $this->assertEquals(PaymentProviderCredential::STATUS_INVALID, $credential->connection_status);
        $this->assertEquals($errorMessage, $credential->validation_error);
        $this->assertNotNull($credential->last_validated_at);
    }

    public function test_can_revalidate_credentials(): void
    {
        $credential = $this->createCredential();
        
        // Set initial status
        $credential->update([
            'connection_status' => PaymentProviderCredential::STATUS_VALID,
            'validation_error' => null,
        ]);

        // Mock provider to return success
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(ValidationResult::success());

        $mockFactory = Mockery::mock(ProviderFactory::class);
        $mockFactory->shouldReceive('make')
            ->andReturn($mockProvider);

        $service = new ProviderValidationService(
            $mockFactory,
            app(EncryptionService::class)
        );

        $result = $service->revalidate($credential);

        $this->assertTrue($result->isValid);
        
        $credential->refresh();
        $this->assertEquals(PaymentProviderCredential::STATUS_VALID, $credential->connection_status);
    }

    public function test_can_get_validation_status(): void
    {
        $credential = $this->createCredential();
        $credential->update([
            'connection_status' => PaymentProviderCredential::STATUS_VALID,
            'validation_error' => null,
            'last_validated_at' => now(),
        ]);

        $service = new ProviderValidationService(
            app(ProviderFactory::class),
            app(EncryptionService::class)
        );

        $status = $service->getValidationStatus($credential);

        $this->assertEquals(PaymentProviderCredential::PROVIDER_DOKU, $status['provider']);
        $this->assertEquals(PaymentProviderCredential::STATUS_VALID, $status['connection_status']);
        $this->assertTrue($status['is_valid']);
        $this->assertFalse($status['needs_revalidation']);
        $this->assertNotNull($status['last_validated_at']);
        $this->assertNull($status['validation_error']);
    }

    public function test_validate_if_needed_validates_when_needed(): void
    {
        $credential = $this->createCredential();
        
        // Set status to pending (needs validation)
        $credential->update([
            'connection_status' => PaymentProviderCredential::STATUS_PENDING,
        ]);

        // Mock provider
        $mockProvider = Mockery::mock(PaymentProviderInterface::class);
        $mockProvider->shouldReceive('validateCredentials')
            ->once()
            ->andReturn(ValidationResult::success());

        $mockFactory = Mockery::mock(ProviderFactory::class);
        $mockFactory->shouldReceive('make')
            ->andReturn($mockProvider);

        $service = new ProviderValidationService(
            $mockFactory,
            app(EncryptionService::class)
        );

        $result = $service->validateIfNeeded($credential);

        $this->assertNotNull($result);
        $this->assertTrue($result->isValid);
    }

    public function test_validate_if_needed_skips_when_not_needed(): void
    {
        $credential = $this->createCredential();
        
        // Set status to valid and recently validated
        $credential->update([
            'connection_status' => PaymentProviderCredential::STATUS_VALID,
            'last_validated_at' => now(),
        ]);

        $service = new ProviderValidationService(
            app(ProviderFactory::class),
            app(EncryptionService::class)
        );

        $result = $service->validateIfNeeded($credential);

        $this->assertNull($result);
    }

    protected function createCredential(): PaymentProviderCredential
    {
        $credentials = [
            'client_id' => 'test_client_id',
            'secret_key' => 'test_secret_key',
        ];

        $encryptionService = app(EncryptionService::class);
        $encrypted = $encryptionService->encryptCredentials($this->user, $credentials);

        return PaymentProviderCredential::create([
            'user_id' => $this->user->id,
            'provider' => PaymentProviderCredential::PROVIDER_DOKU,
            'credentials_encrypted' => $encrypted,
            'connection_status' => PaymentProviderCredential::STATUS_PENDING,
        ]);
    }
}
