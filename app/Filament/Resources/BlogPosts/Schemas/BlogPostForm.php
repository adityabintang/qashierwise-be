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
                        Section::make('Content')
                            ->schema([
                                Hidden::make('user_id')
                                    ->default(fn () => auth()->id()),
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state))),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true),
                                Select::make('blog_category_id')
                                    ->label('Category')
                                    ->relationship('category', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->createOptionForm([
                                        TextInput::make('name')->required(),
                                        TextInput::make('slug')->required(),
                                    ]),
                                Textarea::make('excerpt')
                                    ->rows(3)
                                    ->columnSpanFull(),
                                RichEditor::make('content')
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
                        Section::make('Media')
                            ->schema([
                                FileUpload::make('featured_image')
                                    ->image()
                                    ->disk('r2')
                                    ->directory('blog/featured')
                                    ->maxSize(5120)
                                    ->imageEditor()
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1920')
                                    ->imageResizeTargetHeight(null)
                                    ->helperText('Gambar akan otomatis dikonversi ke WebP dan diresize maksimal 1920px lebar.')
                            ]),

                        Section::make('SEO')
                            ->schema([
                                TextInput::make('seo_title')
                                    ->maxLength(70),
                                Textarea::make('seo_description')
                                    ->maxLength(160)
                                    ->rows(3)
                                    ->columnSpanFull(),
                                FileUpload::make('seo_image')
                                    ->label('OG Image')
                                    ->image()
                                    ->disk('r2')
                                    ->directory('blog/seo')
                                    ->maxSize(2048)
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1200')
                                    ->imageResizeTargetHeight(null)
                                    ->helperText('Gambar untuk preview saat di-share di social media (1200x630px). Jika tidak diupload, akan menggunakan gambar dari Media. Gambar akan otomatis dikonversi ke WebP dan diresize maksimal 1200px lebar.')
                            ])
                            ->collapsible()
                            ->collapsed(),

                        Section::make('Publishing')
                            ->schema([
                                Select::make('status')
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::Draft)
                                    ->required(),
                                DateTimePicker::make('published_at')
                                    ->label('Publish Date')
                                    ->timezone('Asia/Jakarta')
                                    ->helperText('Input menggunakan WIB. Sistem tetap menyimpan waktu dalam UTC.'),
                                Select::make('tags')
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
