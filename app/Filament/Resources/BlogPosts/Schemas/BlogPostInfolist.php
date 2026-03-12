<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogPostInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Content')
                    ->schema([
                        TextEntry::make('title'),
                        TextEntry::make('slug'),
                        TextEntry::make('category.name')
                            ->label('Category')
                            ->badge(),
                        TextEntry::make('author.name')
                            ->label('Author'),
                        TextEntry::make('excerpt')
                            ->columnSpanFull(),
                        TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Media')
                    ->schema([
                        ImageEntry::make('featured_image')
                            ->disk('r2'),
                    ]),

                Section::make('SEO')
                    ->schema([
                        TextEntry::make('seo_title')
                            ->placeholder('—'),
                        TextEntry::make('seo_description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        ImageEntry::make('seo_image')
                            ->label('OG Image')
                            ->disk('r2'),
                    ])
                    ->collapsible(),

                Section::make('Publishing')
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn ($state): string => $state->color()),
                        TextEntry::make('published_at')
                            ->dateTime('d M Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('tags.name')
                            ->badge()
                            ->separator(','),
                        TextEntry::make('created_at')
                            ->dateTime('d M Y H:i'),
                        TextEntry::make('updated_at')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
            ]);
    }
}
