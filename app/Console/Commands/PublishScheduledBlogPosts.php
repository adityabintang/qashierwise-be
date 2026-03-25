<?php

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\BlogPost;
use Illuminate\Console\Command;

class PublishScheduledBlogPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish blog posts with scheduled status when publish time has passed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $publishedCount = BlogPost::query()
            ->where('status', PostStatus::Scheduled)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->update([
                'status' => PostStatus::Published,
                'updated_at' => now(),
            ]);

        $this->info("Published {$publishedCount} scheduled blog post(s).");

        return self::SUCCESS;
    }
}
