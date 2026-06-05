<?php

namespace Tests\Feature\Admin;

use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogTagApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/admin/tags')->assertUnauthorized();
    }

    public function test_admin_can_crud_tag(): void
    {
        Sanctum::actingAs($this->admin);

        // create (auto slug)
        $this->postJson('/api/admin/tags', ['name' => 'Promo Spesial'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'promo-spesial');

        $tag = BlogTag::first();

        // list
        $this->getJson('/api/admin/tags')->assertOk()->assertJsonCount(1, 'data');

        // update
        $this->putJson("/api/admin/tags/{$tag->id}", ['name' => 'Promo'])
            ->assertOk()->assertJsonPath('data.name', 'Promo');

        // delete
        $this->deleteJson("/api/admin/tags/{$tag->id}")->assertOk();
        $this->assertDatabaseMissing('blog_tags', ['id' => $tag->id]);
    }

    public function test_name_is_required(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/tags', [])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }
}
