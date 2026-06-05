<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates for the admin dashboard. Replaces the four Filament widgets:
 * BlogStatsWidget, BlogPostsChartWidget, PopularCategoriesWidget, RecentBlogPostsWidget.
 */
class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $this->overview(),
                'posts_per_month' => $this->postsPerMonth(),
                'popular_categories' => $this->popularCategories(),
                'recent_posts' => $this->recentPosts(),
            ],
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function overview(): array
    {
        return [
            'total_posts' => BlogPost::count(),
            'published_posts' => BlogPost::where('status', PostStatus::Published)->count(),
            'draft_posts' => BlogPost::where('status', PostStatus::Draft)->count(),
            'scheduled_posts' => BlogPost::where('status', PostStatus::Scheduled)->count(),
            'total_categories' => BlogCategory::count(),
            'total_tags' => BlogTag::count(),
        ];
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function postsPerMonth(): array
    {
        $driver = DB::connection()->getDriverName();
        $monthExpr = $driver === 'sqlite'
            ? DB::raw("strftime('%Y-%m', created_at) as month")
            : DB::raw("to_char(created_at, 'YYYY-MM') as month");

        return BlogPost::query()
            ->select($monthExpr, DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'label' => date('M Y', strtotime($row->month.'-01')),
                'count' => (int) $row->count,
            ])
            ->all();
    }

    /**
     * @return array<int, array{name: string, count: int}>
     */
    private function popularCategories(): array
    {
        return BlogCategory::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get()
            ->map(fn (BlogCategory $c) => ['name' => $c->name, 'count' => (int) $c->posts_count])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPosts(): array
    {
        return BlogPost::query()
            ->with('category:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'category' => $p->category?->name,
                'status' => $p->status->value,
                'status_color' => $p->status->color(),
                'published_at' => $p->published_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
