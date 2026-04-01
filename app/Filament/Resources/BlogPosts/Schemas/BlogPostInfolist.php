<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Helpers\TimezoneDisplayHelper;
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
                Section::make(__('admin.resources.blog_post.sections.content'))
                    ->schema([
                        TextEntry::make('title')
                            ->label(__('admin.resources.blog_post.fields.title')),
                        TextEntry::make('slug'),
                        TextEntry::make('category.name')
                            ->label(__('admin.resources.blog_post.fields.category'))
                            ->badge(),
                        TextEntry::make('author.name')
                            ->label(__('admin.resources.blog_post.fields.author')),
                        TextEntry::make('excerpt')
                            ->label(__('admin.resources.blog_post.fields.excerpt'))
                            ->columnSpanFull(),
                        TextEntry::make('content')
                            ->label(__('admin.resources.blog_post.fields.content'))
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make(__('admin.resources.blog_post.sections.media'))
                    ->schema([
                        ImageEntry::make('featured_image')
                            ->label(__('admin.resources.blog_post.fields.featured_image'))
                            ->disk('r2'),
                    ]),

                Section::make(__('admin.resources.blog_post.sections.seo'))
                    ->schema([
                        TextEntry::make('seo_title')
                            ->label(__('admin.resources.blog_post.fields.seo_title'))
                            ->placeholder('—'),
                        TextEntry::make('seo_description')
                            ->label(__('admin.resources.blog_post.fields.seo_description'))
                            ->placeholder('—')
                            ->columnSpanFull(),
                        ImageEntry::make('seo_image')
                            ->label(__('admin.resources.blog_post.fields.og_image'))
                            ->disk('r2'),
                    ])
                    ->collapsible(),

                Section::make(__('admin.resources.blog_post.sections.publishing'))
                    ->schema([
                        TextEntry::make('status')
                            ->label(__('admin.resources.blog_post.fields.status'))
                            ->badge()
                            ->color(fn ($state): string => $state->color()),
                        TextEntry::make('published_at')
                            ->label(__('admin.resources.blog_post.fields.published_at'))
                            ->formatStateUsing(fn (mixed $state): string => TimezoneDisplayHelper::formatHtml($state))
                            ->html()
                            ->placeholder('—'),
                        TextEntry::make('tags.name')
                            ->label(__('admin.resources.blog_post.fields.tags'))
                            ->badge()
                            ->separator(','),
                        TextEntry::make('created_at')
                            ->label(__('admin.resources.blog_post.fields.created_at'))
                            ->formatStateUsing(fn (mixed $state): string => TimezoneDisplayHelper::formatHtml($state))
                            ->html(),
                        TextEntry::make('updated_at')
                            ->label(__('admin.resources.blog_post.fields.updated_at'))
                            ->formatStateUsing(fn (mixed $state): string => TimezoneDisplayHelper::formatHtml($state))
                            ->html(),
                    ])
                    ->columns(2),
            ]);
    }
}
