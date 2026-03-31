<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Filament\Resources\BlogPosts\Pages\CreateBlogPost;
use App\Filament\Resources\BlogPosts\Pages\EditBlogPost;
use App\Filament\Resources\BlogPosts\Pages\ListBlogPosts;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogPostResourceTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_blog_posts_list(): void
    {
        $this->get('/admin/blog-posts')
            ->assertRedirect('/admin/login');
    }

    public function test_non_admin_user_cannot_access_blog_posts_list(): void
    {
        $user = User::factory()->create(['is_master_admin' => false]);

        $this->actingAs($user, 'web')
            ->get('/admin/blog-posts')
            ->assertForbidden();
    }

    public function test_admin_can_access_blog_posts_list(): void
    {
        $this->actingAs($this->admin, 'web')
            ->get('/admin/blog-posts')
            ->assertOk();
    }

    public function test_admin_can_render_blog_posts_list_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogPosts::class)
            ->assertSuccessful();
    }

    public function test_blog_posts_list_shows_existing_posts(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Test Blog Post Title',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogPosts::class)
            ->assertSee('Test Blog Post Title');
    }

    public function test_admin_can_render_create_blog_post_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->assertSuccessful();
    }

    public function test_admin_can_create_blog_post(): void
    {
        $category = BlogCategory::factory()->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'My New Post',
                'slug' => 'my-new-post',
                'content' => '<p>Some content here</p>',
                'blog_category_id' => $category->id,
                'status' => PostStatus::Draft,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_posts', [
            'title' => 'My New Post',
            'slug' => 'my-new-post',
            'user_id' => $this->admin->id,
            'blog_category_id' => $category->id,
            'status' => 'draft',
        ]);
    }

    public function test_create_blog_post_requires_title(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => '',
                'slug' => 'test-slug',
                'content' => '<p>Content</p>',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_create_blog_post_requires_content(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'Title',
                'slug' => 'title',
                'content' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['content']);
    }

    public function test_create_blog_post_requires_unique_slug(): void
    {
        BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'slug' => 'existing-slug',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'New Post',
                'slug' => 'existing-slug',
                'content' => '<p>Content</p>',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_render_edit_blog_post_page(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_admin_can_update_blog_post(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'Original Title',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->fillForm([
                'title' => 'Updated Title',
                'slug' => 'updated-title',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNotified('Blog Post Berhasil Disimpan');

        $this->assertDatabaseHas('blog_posts', [
            'id' => $post->id,
            'title' => 'Updated Title',
            'slug' => 'updated-title',
        ]);
    }

    public function test_edit_blog_post_rejects_future_publish_date_for_published_status_and_shows_footer_error(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-31 10:00:00', 'Asia/Jakarta'));

        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->fillForm([
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $post->content,
                'status' => PostStatus::Published->value,
                'published_at' => Carbon::now('Asia/Jakarta')->addHour()->format('Y-m-d H:i:s'),
            ])
            ->call('save')
            ->assertSet('publishDateValidationDetailsHtml', fn (?string $value): bool => filled($value))
            ->assertSee('Validasi Tanggal Publikasi Gagal')
            ->assertSee('Status')
            ->assertSee('Published');

        Notification::assertNotified('Gagal Menyimpan Blog Post');

        $post->refresh();

        $this->assertSame('draft', $post->status->value);
        $this->assertNull($post->published_at);

        Carbon::setTestNow();
    }

    public function test_admin_can_delete_blog_post(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogPost::class, ['record' => $post->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('blog_posts', [
            'id' => $post->id,
        ]);
    }

    public function test_blog_post_can_be_created_with_tags(): void
    {
        $tags = BlogTag::factory()->count(3)->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogPost::class)
            ->fillForm([
                'title' => 'Tagged Post',
                'slug' => 'tagged-post',
                'content' => '<p>Content with tags</p>',
                'status' => PostStatus::Draft,
                'tags' => $tags->pluck('id')->toArray(),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $post = BlogPost::where('slug', 'tagged-post')->first();
        $this->assertNotNull($post);
        $this->assertCount(3, $post->tags);
    }

    public function test_blog_post_published_state(): void
    {
        $post = BlogPost::factory()->published()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->assertEquals(PostStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);
    }

    public function test_blog_post_scheduled_state(): void
    {
        $post = BlogPost::factory()->scheduled()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->assertEquals(PostStatus::Scheduled, $post->status);
        $this->assertTrue($post->published_at->isFuture());
    }

    public function test_blog_post_slug_auto_generated_from_title(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'title' => 'This Is My Title',
            'slug' => null,
        ]);

        $this->assertEquals('this-is-my-title', $post->slug);
    }

    public function test_blog_post_scopes(): void
    {
        BlogPost::factory()->published()->create(['user_id' => $this->admin->id]);
        BlogPost::factory()->create(['user_id' => $this->admin->id, 'status' => PostStatus::Draft]);
        BlogPost::factory()->scheduled()->create(['user_id' => $this->admin->id]);

        $this->assertCount(1, BlogPost::published()->get());
        $this->assertCount(1, BlogPost::draft()->get());
        $this->assertCount(1, BlogPost::scheduled()->get());
    }

    public function test_blog_post_belongs_to_author(): void
    {
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
        ]);

        $this->assertInstanceOf(User::class, $post->author);
        $this->assertEquals($this->admin->id, $post->author->id);
    }

    public function test_blog_post_belongs_to_category(): void
    {
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->create([
            'user_id' => $this->admin->id,
            'blog_category_id' => $category->id,
        ]);

        $this->assertInstanceOf(BlogCategory::class, $post->category);
        $this->assertEquals($category->id, $post->category->id);
    }
}
