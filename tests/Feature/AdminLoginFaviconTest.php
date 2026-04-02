<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginFaviconTest extends TestCase
{
    public function test_admin_login_uses_scheme_agnostic_favicon_url(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();

        $responseContent = $response->getContent();

        $this->assertIsString($responseContent);
        $this->assertMatchesRegularExpression('/<link\s+rel="icon"\s+href="\/favicon\.ico\?v=\d+"\s*\/>/', $responseContent);
        $this->assertDoesNotMatchRegularExpression('/<link\s+rel="icon"\s+href="http:\/\//', $responseContent);
    }
}
