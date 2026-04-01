<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogTags\Pages\CreateBlogTag;
use App\Filament\Resources\BlogTags\Pages\EditBlogTag;
use App\Filament\Resources\BlogTags\Pages\ListBlogTags;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogTagResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'is_master_admin' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_blog_tags_list(): void
    {
        $this->get('/admin/blog-tags')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_render_blog_tags_list_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogTags::class)
            ->assertSuccessful();
    }

    public function test_blog_tags_list_shows_existing_tags(): void
    {
        $tag = BlogTag::factory()->create([
            'name' => 'Laravel',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogTags::class)
            ->assertSee('Laravel');
    }

    public function test_admin_can_render_create_blog_tag_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogTag::class)
            ->assertSuccessful();
    }

    public function test_admin_can_create_blog_tag(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogTag::class)
            ->fillForm([
                'name' => 'PHP',
                'slug' => 'php',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_tags', [
            'name' => 'PHP',
            'slug' => 'php',
        ]);
    }

    public function test_create_blog_tag_requires_name(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogTag::class)
            ->fillForm([
                'name' => '',
                'slug' => 'test-slug',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_create_blog_tag_requires_unique_slug(): void
    {
        BlogTag::factory()->create(['slug' => 'existing-tag']);

        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogTag::class)
            ->fillForm([
                'name' => 'New Tag',
                'slug' => 'existing-tag',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_render_edit_blog_tag_page(): void
    {
        $tag = BlogTag::factory()->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogTag::class, ['record' => $tag->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_admin_can_update_blog_tag(): void
    {
        $tag = BlogTag::factory()->create([
            'name' => 'Old Tag',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogTag::class, ['record' => $tag->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Tag',
                'slug' => 'updated-tag',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
            'slug' => 'updated-tag',
        ]);
    }

    public function test_admin_can_delete_blog_tag(): void
    {
        $tag = BlogTag::factory()->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogTag::class, ['record' => $tag->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('blog_tags', [
            'id' => $tag->id,
        ]);
    }

    public function test_blog_tag_has_posts_relationship(): void
    {
        $tag = BlogTag::factory()->create();
        $posts = BlogPost::factory()->count(2)->create([
            'user_id' => $this->admin->id,
        ]);

        $tag->posts()->attach($posts->pluck('id'));

        $this->assertCount(2, $tag->fresh()->posts);
    }

    public function test_blog_tag_slug_auto_generated_from_name(): void
    {
        $tag = BlogTag::factory()->create([
            'name' => 'My Tag',
            'slug' => null,
        ]);

        $this->assertEquals('my-tag', $tag->slug);
    }
}
