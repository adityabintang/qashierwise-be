<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBlogRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_index_only_shows_published_posts(): void
    {
        $publishedPost = BlogPost::factory()->published()->create([
            'title' => 'Artikel Published',
            'slug' => 'artikel-published',
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Artikel Published Future Timezone',
            'slug' => 'artikel-published-future-timezone',
            'published_at' => now()->addHours(7),
        ]);

        BlogPost::factory()->create([
            'title' => 'Artikel Draft',
            'slug' => 'artikel-draft',
        ]);

        BlogPost::factory()->scheduled()->create([
            'title' => 'Artikel Scheduled',
            'slug' => 'artikel-scheduled',
        ]);

        $response = $this->get(route('blog.index'));

        $response->assertOk();
        $response->assertSee($publishedPost->title);
        $response->assertSee('Artikel Published Future Timezone');
        $response->assertDontSee('Artikel Draft');
        $response->assertDontSee('Artikel Scheduled');
    }

    public function test_blog_show_displays_published_post_by_slug(): void
    {
        $publishedPost = BlogPost::factory()->published()->create([
            'title' => 'Panduan Transaksi QRIS',
            'slug' => 'panduan-transaksi-qris',
        ]);

        $response = $this->get(route('blog.show', $publishedPost->slug));

        $response->assertOk();
        $response->assertSee('Panduan Transaksi QRIS');
    }

    public function test_blog_show_returns_not_found_for_non_published_post(): void
    {
        $draftPost = BlogPost::factory()->create([
            'slug' => 'draft-private-post',
        ]);

        $response = $this->get(route('blog.show', $draftPost->slug));

        $response->assertNotFound();
    }

    public function test_welcome_page_contains_blog_link_on_navbar(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="/blog"', false);
    }

    public function test_blog_show_uses_indonesia_timezones_for_indonesian_locale(): void
    {
        $publishedPost = BlogPost::factory()->published()->create([
            'slug' => 'artikel-zona-indonesia',
            'published_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Accept-Language' => 'id-ID,id;q=0.9',
        ])->get(route('blog.show', $publishedPost->slug));

        $response->assertOk();
        $response->assertSee('WIB:');
        $response->assertSee('WITA:');
        $response->assertSee('WIT:');
    }

    public function test_blog_show_uses_utc_for_non_indonesian_locale(): void
    {
        $publishedPost = BlogPost::factory()->published()->create([
            'slug' => 'artikel-zona-utc',
            'published_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Accept-Language' => 'en-US,en;q=0.9',
        ])->get(route('blog.show', $publishedPost->slug));

        $response->assertOk();
        $response->assertSee('UTC:');
        $response->assertDontSee('WIB:');
        $response->assertDontSee('WITA:');
        $response->assertDontSee('WIT:');
    }
}
