<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginSecurityHeadersTest extends TestCase
{
    public function test_admin_login_includes_security_headers(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        $contentSecurityPolicy = $response->headers->get('Content-Security-Policy');
        $this->assertIsString($contentSecurityPolicy);
        $this->assertStringContainsString('upgrade-insecure-requests', $contentSecurityPolicy);
    }
}
