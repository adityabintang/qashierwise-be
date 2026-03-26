<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Enums\PostStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 3,
            ])
            ->components([
                Grid::make(1)
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 2,
                    ])
                    ->schema([
                        Section::make(__('admin.resources.blog_post.sections.content'))
                            ->schema([
                                Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                                TextInput::make('title')
                                    ->label(__('admin.resources.blog_post.fields.title'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Select::make('blog_category_id')
                                    ->label(__('admin.resources.blog_post.fields.category'))
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('slug')->required(),
                                    ]),
                                Textarea::make('excerpt')
                                    ->label(__('admin.resources.blog_post.fields.excerpt'))
                                    ->rows(3)
                                    ->columnSpanFull(),
                                RichEditor::make('content')
                                    ->label(__('admin.resources.blog_post.fields.content'))
                                    ->required()
                                    ->fileAttachmentsDisk('r2')
                                    ->fileAttachmentsDirectory('blog/attachments')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ]),

                Grid::make(1)
                    ->columnSpan([
                        'default' => 'full',
                        'lg' => 1,
                    ])
                    ->schema([
                        Section::make(__('admin.resources.blog_post.sections.media'))
                            ->schema([
                                FileUpload::make('featured_image')
                                    ->label(__('admin.resources.blog_post.fields.featured_image'))
                                    ->image()
                                    ->disk('r2')
                                    ->directory('blog/featured')
                                    ->maxSize(5120)
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1920')
                                    ->imageResizeTargetHeight(null)
                                    ->helperText(__('admin.resources.blog_post.fields.featured_image_helper'))
                            ]),

                        Section::make(__('admin.resources.blog_post.sections.seo'))
                            ->schema([
                                TextInput::make('seo_title')
                                    ->label(__('admin.resources.blog_post.fields.seo_title'))
                                    ->maxLength(70),
                                Textarea::make('seo_description')
                                    ->label(__('admin.resources.blog_post.fields.seo_description'))
                                    ->maxLength(160)
                                    ->rows(3)
                                    ->columnSpanFull(),
                                FileUpload::make('seo_image')
                                    ->label(__('admin.resources.blog_post.fields.og_image'))
                                    ->image()
                                    ->disk('r2')
                                    ->directory('blog/seo')
                                    ->maxSize(2048)
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1200')
                                    ->imageResizeTargetHeight(null)
                                    ->helperText(__('admin.resources.blog_post.fields.og_image_helper'))
                            ])
                            ->collapsible()
                            ->collapsed(),

                        Section::make(__('admin.resources.blog_post.sections.publishing'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('admin.resources.blog_post.fields.status'))
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::Draft)
                                    ->required(),
                                DateTimePicker::make('published_at')
                                    ->label(__('admin.resources.blog_post.fields.publish_date'))
                                    ->timezone('Asia/Jakarta')
                                    ->helperText(__('admin.resources.blog_post.fields.publish_date_helper')),
                                Select::make('tags')
                                    ->label(__('admin.resources.blog_post.fields.tags'))
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('slug')->required(),
                                    ]),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }
}
