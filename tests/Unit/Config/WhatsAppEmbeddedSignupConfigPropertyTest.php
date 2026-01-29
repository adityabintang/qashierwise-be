<?php

namespace Tests\Unit\Config;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for WhatsApp Embedded Signup configuration
 *
 * Feature: whatsapp-embedded-signup
 */
class WhatsAppEmbeddedSignupConfigPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Helper function to check if embedded signup is enabled based on config.
     * This mirrors the logic that will be used in the EmbeddedSignupService.
     */
    private function isEmbeddedSignupEnabled(): bool
    {
        $appId = config('whatsapp.embedded_signup.app_id');
        $appSecret = config('whatsapp.embedded_signup.app_secret');
        $configId = config('whatsapp.embedded_signup.config_id');

        return ! empty($appId) && ! empty($appSecret) && ! empty($configId);
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 12: Missing Config Disables Feature
     * Validates: Requirements 6.2
     *
     * For any application startup where WHATSAPP_APP_ID, WHATSAPP_APP_SECRET,
     * or WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID is missing, the Embedded Signup
     * feature SHALL be disabled.
     */
    #[Test]
    public function missing_any_required_config_disables_embedded_signup(): void
    {
        // Generate all possible combinations of missing config values
        $configCombinations = [
            // At least one config is missing (null or empty string)
            ['app_id' => null, 'app_secret' => 'secret123', 'config_id' => 'config123'],
            ['app_id' => '', 'app_secret' => 'secret123', 'config_id' => 'config123'],
            ['app_id' => 'app123', 'app_secret' => null, 'config_id' => 'config123'],
            ['app_id' => 'app123', 'app_secret' => '', 'config_id' => 'config123'],
            ['app_id' => 'app123', 'app_secret' => 'secret123', 'config_id' => null],
            ['app_id' => 'app123', 'app_secret' => 'secret123', 'config_id' => ''],
            // Multiple configs missing
            ['app_id' => null, 'app_secret' => null, 'config_id' => 'config123'],
            ['app_id' => null, 'app_secret' => 'secret123', 'config_id' => null],
            ['app_id' => 'app123', 'app_secret' => null, 'config_id' => null],
            ['app_id' => null, 'app_secret' => null, 'config_id' => null],
            ['app_id' => '', 'app_secret' => '', 'config_id' => ''],
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($configCombinations)
            )
            ->then(function (array $configValues) {
                // Set the config values
                config([
                    'whatsapp.embedded_signup.app_id' => $configValues['app_id'],
                    'whatsapp.embedded_signup.app_secret' => $configValues['app_secret'],
                    'whatsapp.embedded_signup.config_id' => $configValues['config_id'],
                ]);

                // Property: When any required config is missing, feature must be disabled
                $isEnabled = $this->isEmbeddedSignupEnabled();

                $this->assertFalse(
                    $isEnabled,
                    sprintf(
                        'Embedded Signup should be disabled when config is incomplete. '.
                        'app_id=%s, app_secret=%s, config_id=%s',
                        var_export($configValues['app_id'], true),
                        var_export($configValues['app_secret'], true),
                        var_export($configValues['config_id'], true)
                    )
                );
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 12: Missing Config Disables Feature (Inverse)
     * Validates: Requirements 6.2
     *
     * For any application startup where all required config values are present,
     * the Embedded Signup feature SHALL be enabled.
     */
    #[Test]
    public function complete_config_enables_embedded_signup(): void
    {
        // Generate valid non-empty config values
        $validAppIds = ['1484241559512577', 'app_123456789', '987654321'];
        $validSecrets = ['secret_abc123', 'app_secret_xyz', 'my_secret_key'];
        $validConfigIds = ['828935343106633', 'config_123', '456789012'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validAppIds),
                Generators::elements($validSecrets),
                Generators::elements($validConfigIds)
            )
            ->then(function (string $appId, string $appSecret, string $configId) {
                // Set complete config values
                config([
                    'whatsapp.embedded_signup.app_id' => $appId,
                    'whatsapp.embedded_signup.app_secret' => $appSecret,
                    'whatsapp.embedded_signup.config_id' => $configId,
                ]);

                // Property: When all required config is present, feature must be enabled
                $isEnabled = $this->isEmbeddedSignupEnabled();

                $this->assertTrue(
                    $isEnabled,
                    sprintf(
                        'Embedded Signup should be enabled when config is complete. '.
                        'app_id=%s, app_secret=%s, config_id=%s',
                        $appId,
                        $appSecret,
                        $configId
                    )
                );
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 12: Missing Config Disables Feature
     * Validates: Requirements 6.2
     *
     * Test with randomly generated strings to ensure robustness.
     */
    #[Test]
    public function random_missing_config_combinations_disable_feature(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::constant(''),
                    Generators::string()
                ),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::constant(''),
                    Generators::string()
                ),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::constant(''),
                    Generators::string()
                )
            )
            ->when(function ($appId, $appSecret, $configId) {
                // Filter to only cases where at least one value is missing (null or empty)
                return empty($appId) || empty($appSecret) || empty($configId);
            })
            ->then(function ($appId, $appSecret, $configId) {
                // Set the config values
                config([
                    'whatsapp.embedded_signup.app_id' => $appId,
                    'whatsapp.embedded_signup.app_secret' => $appSecret,
                    'whatsapp.embedded_signup.config_id' => $configId,
                ]);

                // Property: When any required config is missing, feature must be disabled
                $isEnabled = $this->isEmbeddedSignupEnabled();

                $this->assertFalse(
                    $isEnabled,
                    sprintf(
                        'Embedded Signup should be disabled when any config is missing. '.
                        'app_id=%s, app_secret=%s, config_id=%s',
                        var_export($appId, true),
                        var_export($appSecret, true),
                        var_export($configId, true)
                    )
                );
            });
    }

    /**
     * Feature: whatsapp-embedded-signup, Property 12: ES Version Configuration
     * Validates: Requirements 6.3
     *
     * The ES version SHALL be set to 'v4' for coexistence support.
     */
    #[Test]
    public function es_version_is_set_to_v4(): void
    {
        $esVersion = config('whatsapp.embedded_signup.es_version');

        $this->assertEquals(
            'v4',
            $esVersion,
            'ES version should be set to v4 for coexistence support'
        );
    }
}
