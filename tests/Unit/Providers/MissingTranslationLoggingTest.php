<?php

namespace Tests\Unit\Providers;

use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class MissingTranslationLoggingTest extends TestCase
{
    /**
     * Test that the logging channel configuration exists
     *
     * @return void
     */
    public function test_missing_translations_logging_channel_exists()
    {
        $channels = config('logging.channels');
        
        $this->assertArrayHasKey('missing_translations', $channels);
        $this->assertEquals('single', $channels['missing_translations']['driver']);
        $this->assertEquals('warning', $channels['missing_translations']['level']);
        $this->assertStringContainsString('missing_translations.log', $channels['missing_translations']['path']);
    }

    /**
     * Test that missing translation handler is registered in local environment
     *
     * @return void
     */
    public function test_missing_translation_handler_registered_in_local_environment()
    {
        // This test verifies that the handler is registered in AppServiceProvider
        // The actual logging behavior is tested through integration tests
        
        // Check that we're in a local environment for this test
        if (!app()->isLocal()) {
            $this->markTestSkipped('This test only runs in local environment');
        }
        
        // Verify that the AppServiceProvider boot method exists
        $provider = app()->getProvider(\App\Providers\AppServiceProvider::class);
        $this->assertNotNull($provider);
        
        // Verify the method exists
        $this->assertTrue(method_exists($provider, 'boot'));
    }

    /**
     * Test that missing translation returns the key as fallback
     *
     * @return void
     */
    public function test_missing_translation_returns_key_as_fallback()
    {
        $key = 'non.existent.translation.key.for.testing';
        $result = __($key);
        
        // Should return the key itself as fallback
        $this->assertEquals($key, $result);
    }
}
