<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BlogPostsChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    public function getHeading(): ?string
    {
        return 'Blog Posts per Bulan';
    }

    protected function getData(): array
    {
        $data = BlogPost::query()
            ->select(
                DB::raw('strftime("%Y-%m", created_at) as month'),
                DB::raw('count(*) as count')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Blog Posts',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => 'rgba(139, 92, 246, 0.2)',
                    'borderColor' => 'rgba(139, 92, 246, 1)',
                    'borderWidth' => 2,
                    'fill' => true,
                ],
            ],
            'labels' => $data->map(function ($item) {
                return date('M Y', strtotime($item->month.'-01'));
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
