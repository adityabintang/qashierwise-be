<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminLoginFaviconTest extends TestCase
{
    public function test_admin_shell_references_favicon_without_insecure_url(): void
    {
        $response = $this->get('/admin/login');

        $response->assertOk();

        $responseContent = $response->getContent();

        $this->assertIsString($responseContent);
        // The React admin shell links the local favicon...
        $this->assertMatchesRegularExpression('/<link\s+rel="icon"[^>]*href="[^"]*favicon\.ico"/', $responseContent);
        // ...and never via an insecure (http://) URL that would trigger mixed content.
        $this->assertDoesNotMatchRegularExpression('/<link\s+rel="icon"[^>]*href="http:\/\//', $responseContent);
    }
}
