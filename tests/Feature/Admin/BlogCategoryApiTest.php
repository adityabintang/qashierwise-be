<?php

namespace Tests\Feature\Admin;

use App\Models\BlogCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogCategoryApiTest extends TestCase
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
        $this->getJson('/api/admin/categories')->assertUnauthorized();
    }

    public function test_admin_can_list_categories(): void
    {
        BlogCategory::factory()->count(2)->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'is_active', 'posts_count']], 'meta']);
    }

    public function test_all_param_returns_unpaginated_list(): void
    {
        BlogCategory::factory()->count(2)->create();
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/categories?all=1')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_admin_can_create_category_with_auto_slug(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/categories', [
            'name' => 'Berita Terbaru',
            'description' => null,
            'is_active' => true,
        ])->assertCreated()->assertJsonPath('data.slug', 'berita-terbaru');

        $this->assertDatabaseHas('blog_categories', ['name' => 'Berita Terbaru', 'slug' => 'berita-terbaru']);
    }

    public function test_name_is_required(): void
    {
        Sanctum::actingAs($this->admin);
        $this->postJson('/api/admin/categories', ['is_active' => true])
            ->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_admin_can_update_and_delete_category(): void
    {
        $category = BlogCategory::factory()->create(['name' => 'Lama']);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Baru',
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/admin/categories/{$category->id}")->assertOk();
        $this->assertDatabaseMissing('blog_categories', ['id' => $category->id]);
    }
}
