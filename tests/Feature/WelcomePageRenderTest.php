<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageRenderTest extends TestCase
{
    public function test_welcome_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }
}
