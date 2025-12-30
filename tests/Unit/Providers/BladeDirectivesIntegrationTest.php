<?php

namespace Tests\Unit\Providers;

use Illuminate\Support\Facades\View;
use Tests\TestCase;

class BladeDirectivesIntegrationTest extends TestCase
{
    /**
     * Test @trans directive renders actual translation in view
     */
    public function test_trans_directive_renders_in_view(): void
    {
        // Create a test translation
        app('translator')->addLines([
            'test.welcome' => 'Welcome to our application',
        ], 'en');

        // Create a test view with @trans directive
        $blade = "@trans('test.welcome')";
        
        // Compile the view
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        
        $this->assertStringContainsString("<?php echo __('test.welcome'); ?>", $compiled);
    }

    /**
     * Test @locale directive renders current locale in view
     */
    public function test_locale_directive_renders_in_view(): void
    {
        // Set locale
        app()->setLocale('en');
        
        // Create a test view with @locale directive
        $blade = "Current language: @locale";
        
        // Compile the view
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
    }

    /**
     * Test both directives work together in a view
     */
    public function test_both_directives_work_together(): void
    {
        // Create test translations
        app('translator')->addLines([
            'test.language_info' => 'You are viewing this page in',
        ], 'en');

        app()->setLocale('en');
        
        // Create a test view with both directives
        $blade = "@trans('test.language_info') @locale";
        
        // Compile the view
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        
        $this->assertStringContainsString("<?php echo __('test.language_info'); ?>", $compiled);
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
    }

    /**
     * Test @trans directive with nested keys
     */
    public function test_trans_directive_with_nested_keys(): void
    {
        // Create nested translation
        app('translator')->addLines([
            'dashboard.menu.products' => 'Products',
        ], 'en');
        
        // Create a test view with nested key
        $blade = "@trans('dashboard.menu.products')";
        
        // Compile the view
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        
        $this->assertStringContainsString("<?php echo __('dashboard.menu.products'); ?>", $compiled);
    }

    /**
     * Test @locale directive changes with locale
     */
    public function test_locale_directive_reflects_locale_changes(): void
    {
        // Test with English
        app()->setLocale('en');
        $blade = "@locale";
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
        
        // Test with Indonesian
        app()->setLocale('id');
        $blade = "@locale";
        $compiled = app('view')->getEngineResolver()->resolve('blade')->getCompiler()->compileString($blade);
        $this->assertStringContainsString("<?php echo app()->getLocale(); ?>", $compiled);
    }
}
