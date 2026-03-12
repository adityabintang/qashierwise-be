<?php

namespace Tests\Feature;

use App\Filament\Resources\BlogCategories\Pages\CreateBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\EditBlogCategory;
use App\Filament\Resources\BlogCategories\Pages\ListBlogCategories;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BlogCategoryResourceTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_blog_categories_list(): void
    {
        $this->get('/admin/blog-categories')
            ->assertRedirect('/admin/login');
    }

    public function test_admin_can_render_blog_categories_list_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogCategories::class)
            ->assertSuccessful();
    }

    public function test_blog_categories_list_shows_existing_categories(): void
    {
        $category = BlogCategory::factory()->create([
            'name' => 'Technology News',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(ListBlogCategories::class)
            ->assertSee('Technology News');
    }

    public function test_admin_can_render_create_blog_category_page(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogCategory::class)
            ->assertSuccessful();
    }

    public function test_admin_can_create_blog_category(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogCategory::class)
            ->fillForm([
                'name' => 'Tech Category',
                'slug' => 'tech-category',
                'description' => 'Technology related posts',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_categories', [
            'name' => 'Tech Category',
            'slug' => 'tech-category',
            'is_active' => true,
        ]);
    }

    public function test_create_blog_category_requires_name(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogCategory::class)
            ->fillForm([
                'name' => '',
                'slug' => 'test-slug',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_create_blog_category_requires_unique_slug(): void
    {
        BlogCategory::factory()->create(['slug' => 'existing-category']);

        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogCategory::class)
            ->fillForm([
                'name' => 'New Category',
                'slug' => 'existing-category',
            ])
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_admin_can_render_edit_blog_category_page(): void
    {
        $category = BlogCategory::factory()->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogCategory::class, ['record' => $category->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_admin_can_update_blog_category(): void
    {
        $category = BlogCategory::factory()->create([
            'name' => 'Old Name',
        ]);

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Name',
                'slug' => 'updated-name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'slug' => 'updated-name',
        ]);
    }

    public function test_admin_can_delete_blog_category(): void
    {
        $category = BlogCategory::factory()->create();

        Livewire::actingAs($this->admin, 'web')
            ->test(EditBlogCategory::class, ['record' => $category->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('blog_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_blog_category_has_posts_relationship(): void
    {
        $category = BlogCategory::factory()->create();
        BlogPost::factory()->count(3)->create([
            'user_id' => $this->admin->id,
            'blog_category_id' => $category->id,
        ]);

        $this->assertCount(3, $category->posts);
    }

    public function test_blog_category_slug_auto_generated_from_name(): void
    {
        $category = BlogCategory::factory()->create([
            'name' => 'My Category Name',
            'slug' => null,
        ]);

        $this->assertEquals('my-category-name', $category->slug);
    }

    public function test_blog_category_with_seo_fields(): void
    {
        Livewire::actingAs($this->admin, 'web')
            ->test(CreateBlogCategory::class)
            ->fillForm([
                'name' => 'SEO Category',
                'slug' => 'seo-category',
                'seo_title' => 'SEO Title Here',
                'seo_description' => 'Meta description here',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'seo-category',
            'seo_title' => 'SEO Title Here',
            'seo_description' => 'Meta description here',
        ]);
    }

    public function test_blog_category_inactive_factory_state(): void
    {
        $category = BlogCategory::factory()->inactive()->create();

        $this->assertFalse($category->is_active);
    }
}
