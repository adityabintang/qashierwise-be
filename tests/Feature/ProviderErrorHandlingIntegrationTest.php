<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test for provider error handling middleware.
 *
 * Verifies that the SanitizeProviderErrors middleware properly
 * handles errors in real API endpoints.
 */
class ProviderErrorHandlingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * Test that unsupported provider error is caught by validation.
     */
    public function test_unsupported_provider_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'stripe', // Unsupported provider
                'credentials' => [
                    'api_key' => 'test_key',
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['provider']);
    }

    /**
     * Test that missing credentials return proper validation error.
     */
    public function test_missing_credentials_returns_validation_error(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/sub-merchant/providers', [
                'provider' => 'doku',
                // Missing credentials
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['credentials']);
    }

    /**
     * Test that accessing provider endpoints without authentication fails.
     */
    public function test_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/sub-merchant/providers');

        $response->assertStatus(401);
    }

    /**
     * Test that listing providers works correctly.
     */
    public function test_listing_providers_returns_empty_array_initially(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/sub-merchant/providers');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }
}
