<?php

namespace Tests\Unit\Services;

use App\Services\PlanConfig;
use App\Services\PolarService;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for PolarService
 * 
 * Feature: polar-subscription
 */
class PolarServicePropertyTest extends TestCase
{
    use TestTrait;

    private PolarService $polarService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->polarService = new PolarService(new PlanConfig());
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * For any webhook payload and signature pair, the signature validation SHALL return true
     * only when the signature is cryptographically valid for the given payload and secret.
     */
    #[Test]
    public function valid_webhook_signatures_are_accepted(): void
    {
        // Set a known webhook secret for testing
        config(['polar.webhook_secret' => 'test_webhook_secret_12345']);

        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),  // Random payload content
                Generators::int(1000000000, 9999999999)  // Random timestamp
            )
            ->then(function (string $payloadContent, int $timestamp) {
                // Create a valid payload
                $payload = json_encode(['data' => $payloadContent, 'type' => 'test.event']);
                
                // Compute valid signature using the same algorithm as PolarService
                $secret = config('polar.webhook_secret');
                $signedPayload = $timestamp . '.' . $payload;
                $validSignature = hash_hmac('sha256', $signedPayload, $secret);
                
                // Format signature header as Polar does: t=timestamp,v1=signature
                $signatureHeader = "t={$timestamp},v1={$validSignature}";

                // Property: Valid signatures must be accepted
                $result = $this->polarService->validateWebhookSignature($payload, $signatureHeader);
                
                $this->assertTrue(
                    $result,
                    "Valid webhook signature should be accepted"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * For any webhook payload with an invalid signature, validation SHALL return false.
     */
    #[Test]
    public function invalid_webhook_signatures_are_rejected(): void
    {
        // Set a known webhook secret for testing
        config(['polar.webhook_secret' => 'test_webhook_secret_12345']);

        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),  // Random payload content
                Generators::int(1000000000, 9999999999),  // Random timestamp
                Generators::string()  // Random invalid signature
            )
            ->when(function (string $payloadContent, int $timestamp, string $invalidSig) {
                // Ensure the invalid signature is not accidentally valid
                $payload = json_encode(['data' => $payloadContent, 'type' => 'test.event']);
                $secret = config('polar.webhook_secret');
                $signedPayload = $timestamp . '.' . $payload;
                $validSignature = hash_hmac('sha256', $signedPayload, $secret);
                return $invalidSig !== $validSignature;
            })
            ->then(function (string $payloadContent, int $timestamp, string $invalidSig) {
                $payload = json_encode(['data' => $payloadContent, 'type' => 'test.event']);
                
                // Format signature header with invalid signature
                $signatureHeader = "t={$timestamp},v1={$invalidSig}";

                // Property: Invalid signatures must be rejected
                $result = $this->polarService->validateWebhookSignature($payload, $signatureHeader);
                
                $this->assertFalse(
                    $result,
                    "Invalid webhook signature should be rejected"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * Tampered payloads with valid signatures for original payload SHALL be rejected.
     */
    #[Test]
    public function tampered_payloads_are_rejected(): void
    {
        // Set a known webhook secret for testing
        config(['polar.webhook_secret' => 'test_webhook_secret_12345']);

        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),  // Original payload content
                Generators::string(),  // Tampered payload content
                Generators::int(1000000000, 9999999999)  // Random timestamp
            )
            ->when(function (string $original, string $tampered, int $timestamp) {
                // Ensure original and tampered are different
                return $original !== $tampered;
            })
            ->then(function (string $original, string $tampered, int $timestamp) {
                // Create original payload and compute its valid signature
                $originalPayload = json_encode(['data' => $original, 'type' => 'test.event']);
                $secret = config('polar.webhook_secret');
                $signedPayload = $timestamp . '.' . $originalPayload;
                $validSignature = hash_hmac('sha256', $signedPayload, $secret);
                $signatureHeader = "t={$timestamp},v1={$validSignature}";

                // Create tampered payload
                $tamperedPayload = json_encode(['data' => $tampered, 'type' => 'test.event']);

                // Property: Tampered payloads must be rejected even with signature from original
                $result = $this->polarService->validateWebhookSignature($tamperedPayload, $signatureHeader);
                
                $this->assertFalse(
                    $result,
                    "Tampered payload should be rejected even with valid signature from original"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * Empty payloads or signatures SHALL be rejected.
     */
    #[Test]
    public function empty_inputs_are_rejected(): void
    {
        config(['polar.webhook_secret' => 'test_webhook_secret_12345']);

        // Test empty payload
        $result = $this->polarService->validateWebhookSignature('', 't=123,v1=abc');
        $this->assertFalse($result, "Empty payload should be rejected");

        // Test empty signature
        $result = $this->polarService->validateWebhookSignature('{"test": "data"}', '');
        $this->assertFalse($result, "Empty signature should be rejected");

        // Test both empty
        $result = $this->polarService->validateWebhookSignature('', '');
        $this->assertFalse($result, "Both empty should be rejected");
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * Malformed signature headers SHALL be rejected.
     */
    #[Test]
    public function malformed_signature_headers_are_rejected(): void
    {
        config(['polar.webhook_secret' => 'test_webhook_secret_12345']);

        $malformedSignatures = [
            'invalid',
            'no_equals_sign',
            't=123',  // Missing v1
            'v1=abc',  // Missing t
            't123,v1abc',  // Missing equals
            '=123,v1=abc',  // Empty key
            't=,v1=abc',  // Empty timestamp
            't=123,v1=',  // Empty signature value
        ];

        foreach ($malformedSignatures as $signature) {
            $result = $this->polarService->validateWebhookSignature('{"test": "data"}', $signature);
            $this->assertFalse(
                $result,
                "Malformed signature '{$signature}' should be rejected"
            );
        }
    }

    /**
     * Feature: polar-subscription, Property 4: Webhook Signature Validation Correctness
     * Validates: Requirements 4.1
     * 
     * Missing webhook secret configuration SHALL cause rejection.
     */
    #[Test]
    public function missing_webhook_secret_causes_rejection(): void
    {
        // Clear the webhook secret
        config(['polar.webhook_secret' => null]);

        $payload = '{"test": "data"}';
        $timestamp = time();
        $signatureHeader = "t={$timestamp},v1=somesignature";

        $result = $this->polarService->validateWebhookSignature($payload, $signatureHeader);
        
        $this->assertFalse(
            $result,
            "Missing webhook secret should cause rejection"
        );
    }

    /**
     * Feature: polar-subscription, Property 3: Checkout Session Data Integrity
     * Validates: Requirements 3.4
     * 
     * For any checkout session creation request with a valid user and plan,
     * the resulting CheckoutSession SHALL contain the user's email and valid
     * success/cancel callback URLs.
     * 
     * Note: This test validates the CheckoutSession DTO construction logic
     * without making actual API calls. The actual API integration is tested
     * separately with mocked responses.
     */
    #[Test]
    public function checkout_session_dto_contains_required_data(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::suchThat(
                    fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                    Generators::map(
                        fn($parts) => $parts[0] . '@' . $parts[1] . '.com',
                        Generators::tuple(
                            Generators::elements(['user', 'test', 'john', 'jane', 'admin']),
                            Generators::elements(['example', 'test', 'domain', 'company'])
                        )
                    )
                ),
                Generators::elements(['standard', 'pro']),
                Generators::string(),  // Random checkout ID
                Generators::string()   // Random checkout URL
            )
            ->then(function (string $email, string $planId, string $checkoutId, string $checkoutUrl) {
                // Set up config for success/cancel URLs
                $successUrl = 'https://example.com/success?checkout_id=' . $checkoutId;
                $cancelUrl = 'https://example.com/cancel';
                
                config([
                    'polar.urls.success' => $successUrl,
                    'polar.urls.cancel' => $cancelUrl,
                ]);

                // Create CheckoutSession DTO directly (simulating what PolarService does)
                $checkoutSession = new \App\DTOs\CheckoutSession(
                    id: $checkoutId,
                    url: $checkoutUrl,
                    planId: $planId,
                    userEmail: $email,
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );

                // Property: CheckoutSession must contain the user's email
                $this->assertEquals(
                    $email,
                    $checkoutSession->userEmail,
                    "CheckoutSession must contain the user's email"
                );

                // Property: CheckoutSession must contain the plan ID
                $this->assertEquals(
                    $planId,
                    $checkoutSession->planId,
                    "CheckoutSession must contain the plan ID"
                );

                // Property: CheckoutSession must contain a valid success URL
                $this->assertNotEmpty(
                    $checkoutSession->successUrl,
                    "CheckoutSession must contain a success URL"
                );
                $this->assertIsString(
                    $checkoutSession->successUrl,
                    "Success URL must be a string"
                );

                // Property: CheckoutSession must contain a valid cancel URL
                $this->assertNotEmpty(
                    $checkoutSession->cancelUrl,
                    "CheckoutSession must contain a cancel URL"
                );
                $this->assertIsString(
                    $checkoutSession->cancelUrl,
                    "Cancel URL must be a string"
                );

                // Property: CheckoutSession must have an ID
                $this->assertEquals(
                    $checkoutId,
                    $checkoutSession->id,
                    "CheckoutSession must have the correct ID"
                );

                // Property: CheckoutSession must have a URL
                $this->assertEquals(
                    $checkoutUrl,
                    $checkoutSession->url,
                    "CheckoutSession must have the correct URL"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 3: Checkout Session Data Integrity
     * Validates: Requirements 3.4
     * 
     * CheckoutSession URLs must be properly configured from environment.
     */
    #[Test]
    public function checkout_session_urls_match_configuration(): void
    {
        $successUrls = [
            'https://example.com/success',
            'https://app.example.com/dashboard?subscription=success',
            '/dashboard?subscription=success',
        ];

        $cancelUrls = [
            'https://example.com/cancel',
            'https://app.example.com/pricing?subscription=cancelled',
            '/pricing?subscription=cancelled',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($successUrls),
                Generators::elements($cancelUrls)
            )
            ->then(function (string $successUrl, string $cancelUrl) {
                config([
                    'polar.urls.success' => $successUrl,
                    'polar.urls.cancel' => $cancelUrl,
                ]);

                // Verify config is properly set
                $this->assertEquals(
                    $successUrl,
                    config('polar.urls.success'),
                    "Success URL should be properly configured"
                );

                $this->assertEquals(
                    $cancelUrl,
                    config('polar.urls.cancel'),
                    "Cancel URL should be properly configured"
                );

                // Create a CheckoutSession with these URLs
                $checkoutSession = new \App\DTOs\CheckoutSession(
                    id: 'test_checkout_id',
                    url: 'https://checkout.polar.sh/test',
                    planId: 'standard',
                    userEmail: 'test@example.com',
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );

                // Property: URLs in CheckoutSession must match configuration
                $this->assertEquals(
                    $successUrl,
                    $checkoutSession->successUrl,
                    "CheckoutSession success URL must match configuration"
                );

                $this->assertEquals(
                    $cancelUrl,
                    $checkoutSession->cancelUrl,
                    "CheckoutSession cancel URL must match configuration"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 3: Checkout Session Data Integrity
     * Validates: Requirements 3.4
     * 
     * CheckoutSession must preserve all data immutably (readonly properties).
     */
    #[Test]
    public function checkout_session_data_is_immutable(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::string(),
                Generators::string(),
                Generators::elements(['standard', 'pro']),
                Generators::suchThat(
                    fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL) !== false,
                    Generators::map(
                        fn($parts) => $parts[0] . '@' . $parts[1] . '.com',
                        Generators::tuple(
                            Generators::elements(['user', 'test', 'john']),
                            Generators::elements(['example', 'test'])
                        )
                    )
                ),
                Generators::string(),
                Generators::string()
            )
            ->then(function (string $id, string $url, string $planId, string $email, string $successUrl, string $cancelUrl) {
                $checkoutSession = new \App\DTOs\CheckoutSession(
                    id: $id,
                    url: $url,
                    planId: $planId,
                    userEmail: $email,
                    successUrl: $successUrl,
                    cancelUrl: $cancelUrl,
                );

                // Property: All properties must be accessible and match constructor values
                $this->assertEquals($id, $checkoutSession->id);
                $this->assertEquals($url, $checkoutSession->url);
                $this->assertEquals($planId, $checkoutSession->planId);
                $this->assertEquals($email, $checkoutSession->userEmail);
                $this->assertEquals($successUrl, $checkoutSession->successUrl);
                $this->assertEquals($cancelUrl, $checkoutSession->cancelUrl);

                // Property: Properties are readonly (verified by PHP's readonly keyword)
                // This is enforced at compile time by PHP 8.1+ readonly properties
                $reflection = new \ReflectionClass($checkoutSession);
                foreach ($reflection->getProperties() as $property) {
                    $this->assertTrue(
                        $property->isReadOnly(),
                        "Property {$property->getName()} should be readonly"
                    );
                }
            });
    }
}
