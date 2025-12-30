<?php

namespace Tests\Unit\Providers;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BladeDirectivesTest extends TestCase
{
    /**
     * Test @trans directive renders translation
     */
    public function test_trans_directive_renders_translation(): void
    {
        // Create a test translation
        app('translator')->addLines([
            'test.message' => 'Hello World',
        ], 'en');

        // Compile the Blade directive
        $compiled = Blade::compileString("@trans('test.message')");
        
        // The directive should compile to PHP code that calls __()
        $this->assertStringContainsString("<?php echo __('test.message'); ?>", $compiled);
    }

    /**
     * Test @trans directive with parameters
     */
    public function test_trans_directive_with_parameters(): void
    {
        // Create a test translation with parameter
        app('translator')->addLines([
            'test.greeting' => 'Hello :name',
        ], 'en');

        // Compile the Blade directive with parameters
        $compiled = Blade::compileString("@trans('test.greeting', ['name' => 'John'])");
        
        // The directive should compile to PHP code
        $this->assertStringContainsString("<?php echo __('test.greeting', ['name' => 'John']); ?>", $compiled);
    }

    /**
     * Test @locale directive returns current locale
     */
    public function test_locale_directive_returns_current_locale(): void
    {
        // Set locale to English
        app()->setLocale('en');
        
        // Compile the Blade directive
        $compiled = Blade::compileString("@locale");
        
        // The directive should compile to PHP code that calls app()->getLocale()
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
    }

    /**
     * Test @locale directive in different locales
     */
    public function test_locale_directive_in_different_locales(): void
    {
        // Test with English
        app()->setLocale('en');
        $compiled = Blade::compileString("Current locale: @locale");
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
        
        // Test with Indonesian
        app()->setLocale('id');
        $compiled = Blade::compileString("Current locale: @locale");
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
    }

    /**
     * Test @trans directive handles missing translation keys
     */
    public function test_trans_directive_handles_missing_keys(): void
    {
        // Compile directive with non-existent key
        $compiled = Blade::compileString("@trans('nonexistent.key')");
        
        // Should still compile correctly
        $this->assertStringContainsString("<?php echo __('nonexistent.key'); ?>", $compiled);
    }
}
