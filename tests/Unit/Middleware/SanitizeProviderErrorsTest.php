<?php

namespace Tests\Unit\Middleware;

use App\Exceptions\EncryptionException;
use App\Exceptions\NoActiveProviderException;
use App\Exceptions\ProviderException;
use App\Exceptions\RLSViolationException;
use App\Exceptions\UnsupportedProviderException;
use App\Http\Middleware\SanitizeProviderErrors;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Test suite for SanitizeProviderErrors middleware.
 *
 * Tests error handling and sanitization for:
 * - Encryption errors (Requirement 12.2)
 * - RLS violations (Requirement 12.5)
 * - Provider errors with classification (Requirement 12.3)
 * - User-friendly error message mapping (Requirement 12.1)
 */
class SanitizeProviderErrorsTest extends TestCase
{
    use RefreshDatabase;

    private SanitizeProviderErrors $middleware;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new SanitizeProviderErrors;
        $this->user = User::factory()->create();
    }

    /**
     * Test encryption error sanitization.
     * Requirement 12.2: Encryption errors should not expose technical details
     */
    public function test_encryption_error_sanitization(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Encryption error occurred', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new EncryptionException('AES-256-CBC encryption failed with key derivation error');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('ENCRYPTION_ERROR', $data['error']['code']);

        // Verify technical details are not exposed
        $this->assertStringNotContainsString('AES-256-CBC', $data['error']['message']);
        $this->assertStringNotContainsString('key derivation', $data['error']['message']);
        $this->assertStringContainsString('credentials securely', $data['error']['message']);
    }

    /**
     * Test RLS violation error sanitization.
     * Requirement 12.5: RLS violations should display generic security errors
     */
    public function test_rls_violation_sanitization(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('RLS violation detected', \Mockery::type('array'));

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $this->user);

        $exception = new RLSViolationException('Row level security policy violation on table payment_provider_credentials');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('ACCESS_DENIED', $data['error']['code']);

        // Verify no technical details are exposed
        $this->assertStringNotContainsString('policy', $data['error']['message']);
        $this->assertStringNotContainsString('payment_provider_credentials', $data['error']['message']);
        $this->assertEquals('You do not have permission to access this resource.', $data['error']['message']);
    }

    /**
     * Test network error classification.
     * Requirement 12.3: Network errors should be distinguished from credential errors
     */
    public function test_network_error_classification(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Provider error occurred', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = ProviderException::networkError('xendit');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(503, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('PROVIDER_NETWORK_ERROR', $data['error']['code']);
        $this->assertEquals('network', $data['error']['error_type']);
        $this->assertEquals('xendit', $data['error']['provider']);
        $this->assertStringContainsString('connect to the payment provider', $data['error']['message']);
    }

    /**
     * Test credential error classification.
     * Requirement 12.3: Credential errors should be distinguished from network errors
     */
    public function test_credential_error_classification(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Provider error occurred', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = ProviderException::credentialError('doku', 'Invalid client_id or secret_key', 'AUTH_FAILED');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('PROVIDER_CREDENTIAL_ERROR', $data['error']['code']);
        $this->assertEquals('credential', $data['error']['error_type']);
        $this->assertEquals('doku', $data['error']['provider']);
        $this->assertStringContainsString('Invalid payment provider credentials', $data['error']['message']);
    }

    /**
     * Test provider-specific error handling.
     * Requirement 12.1: Provider errors should be mapped to user-friendly messages
     */
    public function test_provider_error_handling(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Provider error occurred', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = ProviderException::providerError('midtrans', 'Transaction amount exceeds limit', 'AMOUNT_LIMIT');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('PROVIDER_ERROR', $data['error']['code']);
        $this->assertEquals('provider', $data['error']['error_type']);
        $this->assertEquals('midtrans', $data['error']['provider']);
        $this->assertEquals('Transaction amount exceeds limit', $data['error']['message']);
    }

    /**
     * Test no active provider error handling.
     * Requirement 12.1: Clear error messages for configuration issues
     */
    public function test_no_active_provider_error(): void
    {
        Log::shouldReceive('info')
            ->once()
            ->with('No active provider configured', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new NoActiveProviderException;

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('NO_ACTIVE_PROVIDER', $data['error']['code']);
        $this->assertStringContainsString('configure and activate a payment provider', $data['error']['message']);
    }

    /**
     * Test unsupported provider error handling.
     * Requirement 12.1: Clear error messages with supported provider list
     */
    public function test_unsupported_provider_error(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('Unsupported provider requested', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new UnsupportedProviderException('stripe');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(400, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('UNSUPPORTED_PROVIDER', $data['error']['code']);
        $this->assertArrayHasKey('supported_providers', $data['error']);
        $this->assertContains('doku', $data['error']['supported_providers']);
        $this->assertContains('xendit', $data['error']['supported_providers']);
        $this->assertContains('midtrans', $data['error']['supported_providers']);
        $this->assertContains('duitku', $data['error']['supported_providers']);
    }

    /**
     * Test QueryException RLS violation detection.
     * Requirement 12.5: Database-level RLS violations should be sanitized
     */
    public function test_query_exception_rls_detection(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->with('RLS violation detected', \Mockery::type('array'));

        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $this->user);

        // Simulate PostgreSQL RLS violation
        $pdoException = new \PDOException('SQLSTATE[42501]: Insufficient privilege: row level security policy violation');
        $exception = new QueryException(
            'default',
            'select * from payment_provider_credentials',
            [],
            $pdoException
        );

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(403, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('ACCESS_DENIED', $data['error']['code']);
    }

    /**
     * Test unknown exception classification as network error.
     * Requirement 12.3: Unknown errors should be classified when possible
     */
    public function test_unknown_exception_network_classification(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Network error detected', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new \Exception('Connection timed out after 30 seconds');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(503, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('NETWORK_ERROR', $data['error']['code']);
        $this->assertStringContainsString('connect to the payment provider', $data['error']['message']);
    }

    /**
     * Test unknown exception classification as credential error.
     * Requirement 12.3: Credential-related errors should be identified
     */
    public function test_unknown_exception_credential_classification(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Credential error detected', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new \Exception('401 Unauthorized: Invalid API key provided');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(401, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('CREDENTIAL_ERROR', $data['error']['code']);
        $this->assertStringContainsString('Invalid payment provider credentials', $data['error']['message']);
    }

    /**
     * Test generic error handling for unclassified exceptions.
     * Requirement 12.1: Unknown errors should have generic user-friendly messages
     */
    public function test_generic_error_handling(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Unknown error occurred', \Mockery::type('array'));

        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $this->user);

        $exception = new \Exception('Some internal application error');

        $response = $this->middleware->handle($request, function () use ($exception) {
            throw $exception;
        });

        $this->assertEquals(500, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('INTERNAL_ERROR', $data['error']['code']);

        // Verify internal error details are not exposed
        $this->assertStringNotContainsString('internal application error', $data['error']['message']);
        $this->assertStringContainsString('try again later', $data['error']['message']);
    }

    /**
     * Test successful request passes through middleware.
     */
    public function test_successful_request_passes_through(): void
    {
        $request = Request::create('/api/test', 'GET');
        $request->setUserResolver(fn () => $this->user);

        $response = $this->middleware->handle($request, function ($req) {
            return response()->json(['success' => true, 'data' => 'test']);
        });

        $this->assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('test', $data['data']);
    }
}
