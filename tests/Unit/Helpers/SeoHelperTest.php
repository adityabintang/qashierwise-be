<?php

namespace Tests\Unit\Helpers;

use App\Helpers\SeoHelper;
use Tests\TestCase;

class SeoHelperTest extends TestCase
{
    /**
     * Test getting meta tags for landing page
     */
    public function test_get_meta_tags_returns_correct_structure(): void
    {
        app()->setLocale('en');

        $metaTags = SeoHelper::getMetaTags('landing');

        $this->assertIsArray($metaTags);
        $this->assertArrayHasKey('title', $metaTags);
        $this->assertArrayHasKey('description', $metaTags);
        $this->assertArrayHasKey('keywords', $metaTags);
        $this->assertArrayHasKey('canonical', $metaTags);
        $this->assertArrayHasKey('og_locale', $metaTags);
        $this->assertArrayHasKey('og_title', $metaTags);
        $this->assertArrayHasKey('og_description', $metaTags);
    }

    /**
     * Test meta tags are localized
     */
    public function test_meta_tags_are_localized(): void
    {
        // Test English
        app()->setLocale('en');
        $enMetaTags = SeoHelper::getMetaTags('landing');
        $this->assertStringContainsString('AI WhatsApp Chatbot', $enMetaTags['title']);
        $this->assertEquals('en_US', $enMetaTags['og_locale']);

        // Test Indonesian
        app()->setLocale('id');
        $idMetaTags = SeoHelper::getMetaTags('landing');
        $this->assertStringContainsString('AI Chatbot WhatsApp', $idMetaTags['title']);
        $this->assertEquals('id_ID', $idMetaTags['og_locale']);
    }

    /**
     * Test hreflang tags generation
     */
    public function test_get_hreflang_tags_returns_all_locales(): void
    {
        $hreflangTags = SeoHelper::getHreflangTags();

        $this->assertIsArray($hreflangTags);
        $this->assertGreaterThanOrEqual(3, count($hreflangTags)); // en, id, x-default

        $locales = array_column($hreflangTags, 'locale');
        $this->assertContains('en', $locales);
        $this->assertContains('id', $locales);
        $this->assertContains('x-default', $locales);
    }

    /**
     * Test hreflang tags have valid URLs
     */
    public function test_hreflang_tags_have_valid_urls(): void
    {
        $hreflangTags = SeoHelper::getHreflangTags();

        foreach ($hreflangTags as $tag) {
            $this->assertArrayHasKey('locale', $tag);
            $this->assertArrayHasKey('url', $tag);
            $this->assertNotEmpty($tag['url']);
            $this->assertStringStartsWith('http', $tag['url']);
        }
    }

    /**
     * Test organization structured data
     */
    public function test_get_organization_structured_data(): void
    {
        app()->setLocale('en');

        $data = SeoHelper::getOrganizationStructuredData();

        $this->assertIsArray($data);
        $this->assertEquals('https://schema.org', $data['@context']);
        $this->assertEquals('Organization', $data['@type']);
        $this->assertEquals('QashierWise', $data['name']);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('founder', $data);
    }

    /**
     * Test website structured data
     */
    public function test_get_website_structured_data(): void
    {
        app()->setLocale('en');

        $data = SeoHelper::getWebsiteStructuredData();

        $this->assertIsArray($data);
        $this->assertEquals('https://schema.org', $data['@context']);
        $this->assertEquals('WebSite', $data['@type']);
        $this->assertEquals('QashierWise', $data['name']);
        $this->assertArrayHasKey('description', $data);
    }

    /**
     * Test FAQ structured data
     */
    public function test_get_faq_structured_data(): void
    {
        app()->setLocale('en');

        $data = SeoHelper::getFaqStructuredData();

        $this->assertIsArray($data);
        $this->assertEquals('https://schema.org', $data['@context']);
        $this->assertEquals('FAQPage', $data['@type']);
        $this->assertArrayHasKey('mainEntity', $data);
        $this->assertIsArray($data['mainEntity']);
        $this->assertGreaterThan(0, count($data['mainEntity']));
    }

    /**
     * Test FAQ structured data is localized
     */
    public function test_faq_structured_data_is_localized(): void
    {
        // Test English
        app()->setLocale('en');
        $enData = SeoHelper::getFaqStructuredData();
        $firstQuestion = $enData['mainEntity'][0]['name'];
        $this->assertStringContainsString('What is QashierWise', $firstQuestion);

        // Test Indonesian
        app()->setLocale('id');
        $idData = SeoHelper::getFaqStructuredData();
        $firstQuestion = $idData['mainEntity'][0]['name'];
        $this->assertStringContainsString('Apa itu QashierWise', $firstQuestion);
    }

    /**
     * Test rendering meta tags as HTML
     */
    public function test_render_meta_tags_as_html(): void
    {
        app()->setLocale('en');

        $metaTags = SeoHelper::getMetaTags('landing');
        $html = SeoHelper::renderMetaTags($metaTags);

        $this->assertIsString($html);
        $this->assertStringContainsString('<title>', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta name="twitter:card"', $html);
    }

    /**
     * Test rendering hreflang tags as HTML
     */
    public function test_render_hreflang_tags_as_html(): void
    {
        $hreflangTags = SeoHelper::getHreflangTags();
        $html = SeoHelper::renderHreflangTags($hreflangTags);

        $this->assertIsString($html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="en"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="id"', $html);
        $this->assertStringContainsString('<link rel="alternate" hreflang="x-default"', $html);
    }

    /**
     * Test rendering structured data as JSON-LD
     */
    public function test_render_structured_data_as_json_ld(): void
    {
        $data = SeoHelper::getOrganizationStructuredData();
        $html = SeoHelper::renderStructuredData($data);

        $this->assertIsString($html);
        $this->assertStringContainsString('<script type="application/ld+json">', $html);
        $this->assertStringContainsString('"@context"', $html);
        $this->assertStringContainsString('"@type"', $html);
        $this->assertStringContainsString('</script>', $html);
    }
}
