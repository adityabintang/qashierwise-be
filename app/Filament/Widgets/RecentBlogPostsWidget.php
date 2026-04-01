<?php

namespace App\Filament\Widgets;

use App\Models\BlogPost;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentBlogPostsWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BlogPost::query()
                    ->with(['category', 'tags'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Judul')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value ?? $state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        'scheduled' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => is_object($state) ? $state->value : $state),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Tanggal Publish')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->heading('Recent Blog Posts')
            ->description('5 artikel blog terbaru');
    }
}
