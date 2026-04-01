<?php

namespace App\Filament\Resources\BlogCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        IconEntry::make('is_active')
                            ->boolean(),
                    ])
                    ->columns(2),

                Section::make('SEO')
                    ->schema([
                        TextEntry::make('seo_title')
                            ->placeholder('—'),
                        TextEntry::make('seo_description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                Section::make('Statistics')
                    ->schema([
                        TextEntry::make('posts_count')
                            ->label('Total Posts')
                            ->state(fn ($record) => $record->posts()->count()),
                        TextEntry::make('created_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(3),
            ]);
    }
}
