<?php

namespace Tests\Feature\Admin;

use App\Enums\PostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlogPostApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        // Super admin email bypasses granular permissions (see User::isSuperAdmin).
        $this->admin = User::factory()->create(['email' => User::SUPER_ADMIN_EMAIL]);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/admin/posts')->assertUnauthorized();
    }

    public function test_non_admin_is_forbidden(): void
    {
        Sanctum::actingAs(User::factory()->create(['email' => 'someone@example.com']));
        $this->getJson('/api/admin/posts')->assertForbidden();
    }

    public function test_admin_can_list_posts(): void
    {
        BlogPost::factory()->count(3)->create(['user_id' => $this->admin->id]);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/posts')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'featured_image_url']], 'meta']);
    }

    public function test_admin_can_create_a_published_post(): void
    {
        $category = BlogCategory::factory()->create();
        $tag = BlogTag::factory()->create();
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/posts', [
            'title' => 'Halo Dunia',
            'slug' => 'halo-dunia',
            'blog_category_id' => $category->id,
            'excerpt' => 'ringkasan',
            'content' => '<p>isi</p>',
            'status' => PostStatus::Published->value,
            'published_at' => null,
            'tags' => [$tag->id],
        ])->assertCreated()->assertJsonPath('data.title', 'Halo Dunia');

        $post = BlogPost::first();
        $this->assertSame($this->admin->id, $post->user_id);
        $this->assertNotNull($post->published_at); // auto-set when publishing now
        $this->assertTrue($post->tags->contains($tag));
    }

    public function test_scheduled_post_requires_future_date(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/posts', [
            'title' => 'Terjadwal',
            'slug' => 'terjadwal',
            'content' => '<p>isi</p>',
            'status' => PostStatus::Scheduled->value,
            'published_at' => now()->subDay()->toIso8601String(),
        ])->assertStatus(422)->assertJsonValidationErrors('published_at');
    }

    public function test_slug_must_be_unique(): void
    {
        BlogPost::factory()->create(['slug' => 'dipakai', 'user_id' => $this->admin->id]);
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/posts', [
            'title' => 'Lain',
            'slug' => 'dipakai',
            'content' => '<p>x</p>',
            'status' => PostStatus::Draft->value,
        ])->assertStatus(422)->assertJsonValidationErrors('slug');
    }

    public function test_admin_can_update_a_post(): void
    {
        $post = BlogPost::factory()->create(['user_id' => $this->admin->id, 'title' => 'Lama']);
        Sanctum::actingAs($this->admin);

        $this->putJson("/api/admin/posts/{$post->id}", [
            'title' => 'Baru',
            'slug' => $post->slug,
            'content' => $post->content,
            'status' => $post->status->value,
        ])->assertOk()->assertJsonPath('data.title', 'Baru');

        $this->assertSame('Baru', $post->fresh()->title);
    }

    public function test_admin_can_delete_and_bulk_delete(): void
    {
        $posts = BlogPost::factory()->count(3)->create(['user_id' => $this->admin->id]);
        Sanctum::actingAs($this->admin);

        $this->deleteJson("/api/admin/posts/{$posts[0]->id}")->assertOk();
        $this->assertDatabaseMissing('blog_posts', ['id' => $posts[0]->id]);

        $this->deleteJson('/api/admin/posts/bulk', ['ids' => [$posts[1]->id, $posts[2]->id]])
            ->assertOk();
        $this->assertDatabaseCount('blog_posts', 0);
    }

    public function test_list_can_be_filtered_by_status_and_searched(): void
    {
        BlogPost::factory()->create(['user_id' => $this->admin->id, 'title' => 'Apel', 'status' => PostStatus::Published]);
        BlogPost::factory()->create(['user_id' => $this->admin->id, 'title' => 'Jeruk', 'status' => PostStatus::Draft]);
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/admin/posts?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/admin/posts?search=Apel')->assertOk()->assertJsonPath('data.0.title', 'Apel');
    }
}
