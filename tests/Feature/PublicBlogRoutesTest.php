<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
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

    public function test_blog_index_transitions_due_scheduled_post_to_published_status(): void
    {
        $scheduledPost = BlogPost::factory()->scheduled()->create([
            'title' => 'Artikel Scheduled Menjadi Published',
            'slug' => 'artikel-scheduled-menjadi-published',
            'published_at' => now()->subMinute(),
        ]);

        $this->assertSame('scheduled', $scheduledPost->status->value);

        $this->get(route('blog.index'))->assertOk();

        $scheduledPost->refresh();

        $this->assertSame('published', $scheduledPost->status->value);
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
        $response->assertSee('WIB');
        $response->assertDontSee('WITA');
        $response->assertDontSee('WIT');
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
        $response->assertSee('UTC');
        $response->assertDontSee('WIB');
        $response->assertDontSee('WITA');
        $response->assertDontSee('WIT');
    }

    public function test_blog_load_more_returns_published_posts_only(): void
    {
        BlogPost::factory()->published()->count(12)->create();
        BlogPost::factory()->create([
            'title' => 'Draft Should Not Appear',
            'slug' => 'draft-should-not-appear',
        ]);

        $initialResponse = $this->get(route('blog.index'));
        $initialResponse->assertOk();

        preg_match('/data-next-cursor="([^"]+)"/', $initialResponse->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $response = $this->getJson(route('blog.load-more', [
            'cursor' => $matches[1],
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            'html',
            'next_cursor',
            'has_more',
        ]);

        $this->assertStringContainsString('Read more', $response->json('html'));
        $this->assertStringNotContainsString('Draft Should Not Appear', $response->json('html'));
    }

    public function test_blog_index_can_filter_posts_by_category_slug(): void
    {
        $paymentCategory = BlogCategory::factory()->create([
            'name' => 'Payments',
            'slug' => 'payments',
        ]);

        $opsCategory = BlogCategory::factory()->create([
            'name' => 'Operations',
            'slug' => 'operations',
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Panduan Pembayaran QRIS',
            'slug' => 'panduan-pembayaran-qris',
            'blog_category_id' => $paymentCategory->id,
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Panduan Operasional Toko',
            'slug' => 'panduan-operasional-toko',
            'blog_category_id' => $opsCategory->id,
        ]);

        $response = $this->get(route('blog.index', ['category' => $paymentCategory->slug]));

        $response->assertOk();
        $response->assertSee('Panduan Pembayaran QRIS');
        $response->assertDontSee('Panduan Operasional Toko');
    }

    public function test_blog_index_search_is_case_insensitive_for_title(): void
    {
        $technologyCategory = BlogCategory::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Analisis Data untuk Keputusan Bisnis yang Lebih Baik',
            'slug' => 'analisis-data-untuk-keputusan-bisnis-yang-lebih-baik',
            'blog_category_id' => $technologyCategory->id,
        ]);

        $response = $this->get(route('blog.index', [
            'search' => 'anali',
            'sort' => 'latest',
            'category' => $technologyCategory->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Analisis Data untuk Keputusan Bisnis yang Lebih Baik');
    }

    public function test_blog_load_more_can_filter_posts_by_category_slug(): void
    {
        $paymentCategory = BlogCategory::factory()->create([
            'name' => 'Payments',
            'slug' => 'payments',
        ]);

        $opsCategory = BlogCategory::factory()->create([
            'name' => 'Operations',
            'slug' => 'operations',
        ]);

        BlogPost::factory()->published()->count(12)->create([
            'blog_category_id' => $paymentCategory->id,
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Artikel Kategori Lain',
            'slug' => 'artikel-kategori-lain',
            'blog_category_id' => $opsCategory->id,
        ]);

        $initialResponse = $this->get(route('blog.index', ['category' => $paymentCategory->slug]));
        $initialResponse->assertOk();

        preg_match('/data-next-cursor="([^"]+)"/', $initialResponse->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null);

        $response = $this->getJson(route('blog.load-more', [
            'cursor' => $matches[1],
            'category' => $paymentCategory->slug,
        ]));

        $response->assertOk();
        $this->assertStringNotContainsString('Artikel Kategori Lain', $response->json('html'));
    }

    public function test_blog_index_keeps_hero_visible_when_search_has_no_results(): void
    {
        $technologyCategory = BlogCategory::factory()->create([
            'name' => 'Technology',
            'slug' => 'technology',
        ]);

        BlogPost::factory()->published()->create([
            'title' => 'Analisis Sistem Kasir Modern',
            'slug' => 'analisis-sistem-kasir-modern',
            'blog_category_id' => $technologyCategory->id,
        ]);

        $response = $this->get(route('blog.index', [
            'search' => 'anali123',
            'sort' => 'latest',
            'category' => 'technology',
        ]));

        $response->assertOk();
        $response->assertSee('fly-hero-section is-empty', false);
        $response->assertSee(__('blog.no_posts'));
    }
}
