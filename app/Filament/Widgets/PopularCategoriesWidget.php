<?php

namespace App\Filament\Widgets;

use App\Models\BlogCategory;
use Filament\Widgets\ChartWidget;

class PopularCategoriesWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    public function getHeading(): ?string
    {
        return 'Kategori Populer';
    }

    protected function getData(): array
    {
        $categories = BlogCategory::query()
            ->withCount('posts')
            ->orderByDesc('posts_count')
            ->limit(5)
            ->get();

        $total = $categories->sum('posts_count');

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Posts',
                    'data' => $categories->pluck('posts_count')->toArray(),
                    'backgroundColor' => [
                        'rgba(139, 92, 246, 0.8)',
                        'rgba(167, 139, 250, 0.8)',
                        'rgba(196, 181, 253, 0.8)',
                        'rgba(221, 214, 254, 0.8)',
                        'rgba(237, 233, 254, 0.8)',
                    ],
                ],
            ],
            'labels' => $categories->map(function ($category) use ($total) {
                $percentage = $total > 0 ? round(($category->posts_count / $total) * 100, 1) : 0;

                return $category->name.' ('.$percentage.'%)';
            })->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
