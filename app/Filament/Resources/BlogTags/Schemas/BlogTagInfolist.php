<?php

namespace App\Filament\Resources\BlogTags\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BlogTagInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tag Details')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug'),
                    ])
                    ->columns(2),

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
