<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginBlogCoverUrlTest extends TestCase
{
    public function test_admin_login_contains_direct_simple_layout_background_rule(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();

        $responseContent = $response->getContent();

        $this->assertIsString($responseContent);
        $this->assertStringContainsString('.fi-simple-layout {', $responseContent);
        $this->assertMatchesRegularExpression('/\.fi-simple-layout\s*\{[^\}]*background-image:\s*url\(\'https?:\/\/[^\']+\/blog-cover\.webp\'\)\s*!important;[^\}]*\}/s', $responseContent);
    }
}
