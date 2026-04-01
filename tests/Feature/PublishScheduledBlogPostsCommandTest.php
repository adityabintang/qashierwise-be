<?php

namespace Tests\Feature;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishScheduledBlogPostsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_publishes_due_scheduled_posts(): void
    {
        BlogPost::factory()->scheduled()->create([
            'published_at' => now()->subMinute(),
        ]);

        $this->artisan('blog:publish-scheduled')
            ->expectsOutput('Published 1 scheduled blog post(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('blog_posts', [
            'status' => PostStatus::Published->value,
        ]);
    }

    public function test_command_keeps_future_scheduled_posts_unchanged(): void
    {
        $futurePost = BlogPost::factory()->scheduled()->create([
            'published_at' => now()->addHour(),
        ]);

        $this->artisan('blog:publish-scheduled')
            ->expectsOutput('Published 0 scheduled blog post(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('blog_posts', [
            'id' => $futurePost->id,
            'status' => PostStatus::Scheduled->value,
        ]);
    }
}
