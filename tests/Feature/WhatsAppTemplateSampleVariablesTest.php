<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTemplateSampleVariablesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WhatsAppAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = WhatsAppAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_active' => true,
        ]);

        // Mock WhatsApp API response for successful template creation
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'template_123',
                'status' => 'PENDING',
            ]),
        ]);
    }

    /** @test */
    public function can_create_template_with_sample_variables(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'reservation_reminder',
                'category' => 'UTILITY',
                'language' => 'id',
                'body' => 'Halo {{1}}, reservasi Anda pada {{2}} di {{3}} sudah dikonfirmasi.',
                'body_examples' => ['John Doe', '2026-02-15', 'Restaurant ABC'],
            ]);

        $response->assertSuccessful();
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'id',
                'template_id',
                'name',
                'status',
            ],
        ]);

        // Verify template stored in database with body_examples
        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'reservation_reminder',
            'language' => 'id',
        ]);

        $template = WhatsAppTemplate::where('name', 'reservation_reminder')->first();
        $this->assertNotNull($template);
        $this->assertEquals(['John Doe', '2026-02-15', 'Restaurant ABC'], $template->body_examples);
    }

    /** @test */
    public function can_create_template_without_sample_variables(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'simple_template',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{1}}, this is a test message.',
            ]);

        $response->assertSuccessful();

        $template = WhatsAppTemplate::where('name', 'simple_template')->first();
        $this->assertNotNull($template);
        $this->assertNull($template->body_examples);
    }

    /** @test */
    public function can_create_template_with_empty_sample_values(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'template_empty_examples',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{1}} and {{2}}.',
                'body_examples' => ['', '', ''], // All empty
            ]);

        $response->assertSuccessful();

        $template = WhatsAppTemplate::where('name', 'template_empty_examples')->first();
        $this->assertNotNull($template);
        // Empty values should not be stored
        $this->assertNull($template->body_examples);
    }

    /** @test */
    public function filters_out_empty_sample_values(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'partial_examples',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{1}}, your order is {{2}} and total is {{3}}.',
                'body_examples' => ['John', '', 'Rp 100.000'], // One empty in middle
            ]);

        $response->assertSuccessful();

        $template = WhatsAppTemplate::where('name', 'partial_examples')->first();
        $this->assertNotNull($template);
        // Should only store non-empty values
        $this->assertEquals(['John', 'Rp 100.000'], $template->body_examples);
    }

    /** @test */
    public function sample_variables_with_special_characters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'special_chars_template',
                'category' => 'UTILITY',
                'language' => 'id',
                'body' => 'Nama: {{1}}, Email: {{2}}.',
                'body_examples' => [
                    'Budi Santoso',
                    'budi@example.com',
                ],
            ]);

        $response->assertSuccessful();

        $template = WhatsAppTemplate::where('name', 'special_chars_template')->first();
        $this->assertNotNull($template);
        $this->assertEquals(['Budi Santoso', 'budi@example.com'], $template->body_examples);
    }

    /** @test */
    public function template_creation_without_authentication_fails(): void
    {
        $response = $this->postJson('/api/whatsapp/templates', [
            'name' => 'unauthorized_template',
            'category' => 'UTILITY',
            'language' => 'en',
            'body' => 'Hello {{1}}.',
            'body_examples' => ['World'],
        ]);

        $response->assertUnauthorized();
    }

    /** @test */
    public function cannot_create_template_with_invalid_name_and_sample_variables(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'Invalid Template Name', // Invalid characters
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{1}}.',
                'body_examples' => ['World'],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    /** @test */
    public function template_with_numeric_variables_and_numeric_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'numeric_vars_template',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{1}}, your order {{2}} total {{3}}.',
                'variable_type' => 'numeric',
                'body_examples' => ['John', 'ORD-123', 'Rp 100.000'],
            ]);

        $response->assertSuccessful();
        $template = WhatsAppTemplate::where('name', 'numeric_vars_template')->first();
        $this->assertNotNull($template);
        $this->assertEquals('numeric', $template->variable_type);
    }

    /** @test */
    public function template_with_named_variables_and_named_type(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'named_vars_template',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{customer_name}}, your order {{order_id}} total {{amount}}.',
                'variable_type' => 'named',
                'body_examples' => ['John', 'ORD-123', 'Rp 100.000'],
            ]);

        $response->assertSuccessful();
        $template = WhatsAppTemplate::where('name', 'named_vars_template')->first();
        $this->assertNotNull($template);
        $this->assertEquals('named', $template->variable_type);
    }

    /** @test */
    public function body_examples_are_sent_as_nested_arrays_for_template_creation(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'template_456',
                'status' => 'PENDING',
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'nested_examples_template',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => 'Hello {{ 1 }}, your order {{2}} is ready.',
                'body_examples' => ['John', 'ORD-123'],
            ]);

        $response->assertSuccessful();

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $components = $payload['components'] ?? [];
            $bodyComponent = collect($components)->firstWhere('type', 'BODY');

            if (! is_array($bodyComponent)) {
                return false;
            }

            $bodyExamples = $bodyComponent['example']['body_text'] ?? null;

            return is_array($bodyExamples)
                && isset($bodyExamples[0])
                && is_array($bodyExamples[0])
                && count($bodyExamples[0]) === 2;
        });
    }

    /** @test */
    public function named_parameter_templates_use_named_example_format(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'id' => 'template_789',
                'status' => 'PENDING',
            ]),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'named_format_template',
                'category' => 'UTILITY',
                'language' => 'id',
                'body' => 'Hi {{nama}}, layanan {{nama_layanan}}.',
                'variable_type' => 'named',
                'body_examples' => ['Budi', 'Reservasi'],
            ]);

        $response->assertSuccessful();

        Http::assertSent(function ($request) {
            $payload = $request->data();
            $components = $payload['components'] ?? [];
            $bodyComponent = collect($components)->firstWhere('type', 'BODY');
            $namedParams = $bodyComponent['example']['body_text_named_params'] ?? null;

            return ($payload['parameter_format'] ?? null) === 'named'
                && is_array($namedParams)
                && count($namedParams) === 2
                && ($namedParams[0]['param_name'] ?? null) === 'nama'
                && ($namedParams[0]['example'] ?? null) === 'Budi';
        });
    }

    /** @test */
    public function template_body_cannot_start_or_end_with_variables(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'leading_variable_template',
                'category' => 'UTILITY',
                'language' => 'id',
                'body' => '{{nama}} reservasi Anda sudah dibuat.',
                'variable_type' => 'named',
                'body_examples' => ['Budi'],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('body');

        $response = $this->actingAs($this->user)
            ->postJson('/api/whatsapp/templates', [
                'name' => 'trailing_variable_template',
                'category' => 'UTILITY',
                'language' => 'id',
                'body' => 'Nomor order {{id_order}}',
                'variable_type' => 'named',
                'body_examples' => ['RSV-123'],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('body');
    }
}
