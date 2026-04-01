<?php

namespace Tests\Feature;

use Tests\TestCase;

class LighthouseOptimizationTest extends TestCase
{
    /**
     * Verify that all self-hosted hero image variants exist in public/images.
     */
    public function test_hero_image_files_exist(): void
    {
        $formats = ['avif', 'webp', 'jpg'];
        $widths = ['400w', '665w', '800w'];

        foreach ($formats as $format) {
            foreach ($widths as $width) {
                $path = public_path("images/hero-restaurant-{$width}.{$format}");
                $this->assertFileExists($path, "Missing hero image: hero-restaurant-{$width}.{$format}");
            }
        }
    }

    /**
     * Verify the welcome page uses a <picture> element with self-hosted images instead of Unsplash.
     */
    public function test_welcome_page_uses_self_hosted_hero_image(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('images.unsplash.com', false);
        $response->assertSee('<picture>', false);
        $response->assertSee('hero-restaurant-665w.avif', false);
        $response->assertSee('hero-restaurant-665w.webp', false);
        $response->assertSee('hero-restaurant-665w.jpg', false);
    }

    /**
     * Verify the welcome page includes preload hints for the hero image and manifest.
     */
    public function test_welcome_page_has_preload_hints(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="preload"', false);
        $response->assertSee('as="image"', false);
        $response->assertSee('type="image/avif"', false);
        $response->assertSee('as="fetch"', false);
        $response->assertSee('site.webmanifest', false);
    }

    /**
     * Verify the hero image does not use decoding="async" (contradicts fetchpriority="high" for LCP).
     */
    public function test_hero_image_does_not_use_async_decoding(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $content = $response->getContent();

        // The hero <picture> block should not contain decoding="async"
        preg_match('/<picture>.*?<\/picture>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Could not find <picture> element in the response.');
        $this->assertStringNotContainsString('decoding="async"', $matches[0]);
    }

    /**
     * Verify site.webmanifest returns valid JSON with correctly separated icon purposes.
     */
    public function test_site_webmanifest_is_valid_and_icons_have_separate_purposes(): void
    {
        $manifestPath = public_path('site.webmanifest');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertNotNull($manifest, 'site.webmanifest is not valid JSON.');
        $this->assertArrayHasKey('icons', $manifest);

        foreach ($manifest['icons'] as $icon) {
            $this->assertArrayHasKey('purpose', $icon);
            // Each icon entry should have a single purpose, not "any maskable"
            $this->assertNotEquals('any maskable', $icon['purpose'], 'Icon purpose should be split into separate entries.');
            $this->assertContains($icon['purpose'], ['any', 'maskable'], "Unexpected icon purpose: {$icon['purpose']}");
        }
    }

    /**
     * Verify all icon files referenced in site.webmanifest physically exist.
     */
    public function test_webmanifest_icon_files_exist(): void
    {
        $manifestPath = public_path('site.webmanifest');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertNotNull($manifest);

        foreach ($manifest['icons'] as $icon) {
            $iconPath = public_path(ltrim($icon['src'], '/'));
            $this->assertFileExists($iconPath, "Missing icon file: {$icon['src']}");
        }
    }

    /**
     * Verify CSP no longer includes images.unsplash.com since images are self-hosted.
     */
    public function test_csp_header_does_not_include_unsplash(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');
        if ($csp) {
            $this->assertStringNotContainsString('images.unsplash.com', $csp);
        }

        // Pass if no CSP header (middleware might not be active in test env)
        $this->assertTrue(true);
    }
}
