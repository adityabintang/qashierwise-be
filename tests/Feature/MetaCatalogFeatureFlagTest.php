<?php

namespace Tests\Feature;

use Tests\TestCase;

class MetaCatalogFeatureFlagTest extends TestCase
{
    public function test_ai_agent_page_locks_meta_catalog_when_feature_disabled(): void
    {
        config(['catalog.meta_enabled' => false]);

        $response = $this->get('/dashboard/ai-agent');

        $response->assertOk();
        $response->assertSee('-- Tidak menggunakan katalog (mode AI ) --', false);
        $response->assertSee('Fitur Meta Catalog sedang dinonaktifkan', false);
        $response->assertDontSee('href="/dashboard/meta-catalog"', false);
    }

    public function test_ai_agent_page_allows_meta_catalog_when_feature_enabled(): void
    {
        config(['catalog.meta_enabled' => true]);

        $response = $this->get('/dashboard/ai-agent');

        $response->assertOk();
        $response->assertSee('href="/dashboard/meta-catalog"', false);
        $response->assertSee('-- Tidak menggunakan katalog (mode AI text) --', false);
    }
}
