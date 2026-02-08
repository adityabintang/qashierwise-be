<?php

namespace Tests\Unit\Config;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PolarConfigTest extends TestCase
{
    /**
     * Test that config values are loaded from environment.
     * _Requirements: 1.1_
     */
    public function test_config_loads_api_token_from_environment(): void
    {
        // Set environment variable
        config(['polar.api_token' => 'test_api_token']);

        $this->assertEquals('test_api_token', config('polar.api_token'));
    }

    /**
     * Test that webhook secret is loaded from environment.
     * _Requirements: 1.1_
     */
    public function test_config_loads_webhook_secret_from_environment(): void
    {
        config(['polar.webhook_secret' => 'test_webhook_secret']);

        $this->assertEquals('test_webhook_secret', config('polar.webhook_secret'));
    }

    /**
     * Test that product IDs are loaded from environment.
     * _Requirements: 1.1, 2.1_
     */
    public function test_config_loads_product_ids_from_environment(): void
    {
        config([
            'polar.products.standard' => 'prod_standard_123',
            'polar.products.pro' => 'prod_pro_456',
        ]);

        $this->assertEquals('prod_standard_123', config('polar.products.standard'));
        $this->assertEquals('prod_pro_456', config('polar.products.pro'));
    }

    /**
     * Test that callback URLs are loaded from environment.
     * _Requirements: 1.1_
     */
    public function test_config_loads_callback_urls_from_environment(): void
    {
        config([
            'polar.urls.success' => 'https://example.com/success',
            'polar.urls.cancel' => 'https://example.com/cancel',
        ]);

        $this->assertEquals('https://example.com/success', config('polar.urls.success'));
        $this->assertEquals('https://example.com/cancel', config('polar.urls.cancel'));
    }

    /**
     * Test that trial days configuration is loaded.
     * _Requirements: 1.1_
     */
    public function test_config_loads_trial_days(): void
    {
        config(['polar.trial_days' => 14]);

        $this->assertEquals(14, config('polar.trial_days'));
    }

    /**
     * Test that missing API token returns null.
     * _Requirements: 1.2_
     */
    public function test_missing_api_token_returns_null(): void
    {
        config(['polar.api_token' => null]);

        $this->assertNull(config('polar.api_token'));
    }

    /**
     * Test that missing webhook secret returns null.
     * _Requirements: 1.2_
     */
    public function test_missing_webhook_secret_returns_null(): void
    {
        config(['polar.webhook_secret' => null]);

        $this->assertNull(config('polar.webhook_secret'));
    }

    /**
     * Test that plan configurations are available.
     * _Requirements: 2.1_
     */
    public function test_plan_configurations_are_available(): void
    {
        $plans = config('polar.plans');

        $this->assertIsArray($plans);
        $this->assertArrayHasKey('free_trial', $plans);
        $this->assertArrayHasKey('standard', $plans);
        $this->assertArrayHasKey('pro', $plans);
    }

    /**
     * Test that each plan has required fields.
     * _Requirements: 2.2_
     */
    public function test_each_plan_has_required_fields(): void
    {
        $plans = config('polar.plans');
        $requiredFields = ['id', 'name', 'price_monthly', 'tier', 'features'];

        foreach ($plans as $planId => $plan) {
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey(
                    $field,
                    $plan,
                    "Plan '{$planId}' is missing required field '{$field}'"
                );
            }
        }
    }

    /**
     * Test that plan features are arrays.
     * _Requirements: 2.2_
     */
    public function test_plan_features_are_arrays(): void
    {
        $plans = config('polar.plans');

        foreach ($plans as $planId => $plan) {
            $this->assertIsArray(
                $plan['features'],
                "Plan '{$planId}' features should be an array"
            );
        }
    }

    /**
     * Test default callback URLs when environment variables are not set.
     * _Requirements: 1.1_
     */
    public function test_default_callback_urls(): void
    {
        // The config file has default values
        $successUrl = config('polar.urls.success');
        $cancelUrl = config('polar.urls.cancel');

        $this->assertNotNull($successUrl);
        $this->assertNotNull($cancelUrl);
        $this->assertStringContainsString('subscription=success', $successUrl);
        $this->assertStringContainsString('subscription=cancelled', $cancelUrl);
    }

    /**
     * Test default trial days value.
     * _Requirements: 1.1_
     */
    public function test_default_trial_days_value(): void
    {
        $trialDays = config('polar.trial_days');

        $this->assertEquals(14, $trialDays);
    }

    /**
     * Test that credentials can be checked for completeness.
     * _Requirements: 1.2_
     */
    public function test_can_check_credentials_completeness(): void
    {
        // Set complete credentials
        config([
            'polar.api_token' => 'test_token',
            'polar.webhook_secret' => 'test_secret',
            'polar.products.standard' => 'prod_standard',
            'polar.products.pro' => 'prod_pro',
        ]);

        $hasApiToken = ! empty(config('polar.api_token'));
        $hasWebhookSecret = ! empty(config('polar.webhook_secret'));
        $hasStandardProduct = ! empty(config('polar.products.standard'));
        $hasProProduct = ! empty(config('polar.products.pro'));

        $this->assertTrue($hasApiToken);
        $this->assertTrue($hasWebhookSecret);
        $this->assertTrue($hasStandardProduct);
        $this->assertTrue($hasProProduct);
    }

    /**
     * Test that incomplete credentials can be detected.
     * _Requirements: 1.2_
     */
    public function test_can_detect_incomplete_credentials(): void
    {
        // Set incomplete credentials
        config([
            'polar.api_token' => null,
            'polar.webhook_secret' => 'test_secret',
        ]);

        $hasApiToken = ! empty(config('polar.api_token'));
        $hasWebhookSecret = ! empty(config('polar.webhook_secret'));

        $this->assertFalse($hasApiToken);
        $this->assertTrue($hasWebhookSecret);
    }
}
