<?php

namespace App\Filament\Widgets;

use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BlogStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalPosts = BlogPost::count();
        $publishedPosts = BlogPost::where('status', 'published')->count();
        $draftPosts = BlogPost::where('status', 'draft')->count();
        $totalCategories = BlogCategory::count();
        $totalTags = BlogTag::count();

        return [
            Stat::make('Total Blog Posts', $totalPosts)
                ->description('Semua artikel blog')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary')
                ->chart([7, 12, 15, 18, 22, 25, $totalPosts]),

            Stat::make('Published Posts', $publishedPosts)
                ->description('Artikel yang sudah dipublikasi')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->chart([5, 8, 12, 15, 18, 20, $publishedPosts]),

            Stat::make('Draft Posts', $draftPosts)
                ->description('Artikel dalam draft')
                ->descriptionIcon('heroicon-m-pencil-square')
                ->color('warning')
                ->chart([2, 4, 3, 3, 4, 5, $draftPosts]),

            Stat::make('Categories', $totalCategories)
                ->description('Total kategori blog')
                ->descriptionIcon('heroicon-m-folder')
                ->color('info'),

            Stat::make('Tags', $totalTags)
                ->description('Total tag blog')
                ->descriptionIcon('heroicon-m-tag')
                ->color('gray'),
        ];
    }
}
