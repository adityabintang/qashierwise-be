<?php

namespace App\Filament\Resources\BlogPosts\Schemas;

use App\Enums\PostStatus;
use App\Helpers\TimezoneDisplayHelper;
use Carbon\Carbon;
use DateTimeZone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BlogPostForm
{
    protected static function normalizeInputDateTimeToUtc(string $dateTime, ?string $viewerTimezone = null): Carbon
    {
        $hasExplicitTimezone = preg_match('/(Z|[+-]\d{2}:\d{2})$/', $dateTime) === 1;

        if ($hasExplicitTimezone) {
            return Carbon::parse($dateTime)->utc();
        }

        $viewerTimezone = self::resolveInputTimezone($viewerTimezone);
        $systemTimezone = config('app.timezone', 'UTC');
        $looksLikeLocalDatetimeInput = str_contains($dateTime, 'T');

        $candidateFormats = $looksLikeLocalDatetimeInput
            ? ['Y-m-d\\TH:i:s', 'Y-m-d\\TH:i']
            : ['Y-m-d H:i:s', 'Y-m-d H:i'];

        $sourceTimezone = $looksLikeLocalDatetimeInput ? $viewerTimezone : $systemTimezone;

        foreach ($candidateFormats as $format) {
            try {
                $parsedDateTime = Carbon::createFromFormat($format, $dateTime, $sourceTimezone);

                if ($parsedDateTime !== false) {
                    return $parsedDateTime->utc();
                }
            } catch (\Throwable) {
                // Fall through to generic parser below.
            }
        }

        return Carbon::parse($dateTime, $sourceTimezone)->utc();
    }

    protected static function resolveInputTimezone(?string $viewerTimezone): string
    {
        if (is_string($viewerTimezone) && in_array($viewerTimezone, DateTimeZone::listIdentifiers(), true)) {
            return $viewerTimezone;
        }

        return TimezoneDisplayHelper::resolveDisplayTimezone()['timezone'];
    }

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
                                Hidden::make('viewer_timezone')
                                    ->default(fn () => TimezoneDisplayHelper::resolveDisplayTimezone()['timezone']),
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
                                    ->imageResizeMode('contain')
                                    ->imageResizeTargetWidth('1920')
                                    ->imageResizeTargetHeight(null)
                                    ->helperText(__('admin.resources.blog_post.fields.featured_image_helper')),
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
                                    ->helperText(__('admin.resources.blog_post.fields.og_image_helper')),
                            ])
                            ->collapsible()
                            ->collapsed(),

                        Section::make(__('admin.resources.blog_post.sections.publishing'))
                            ->schema([
                                Select::make('status')
                                    ->label(__('admin.resources.blog_post.fields.status'))
                                    ->options(PostStatus::class)
                                    ->default(PostStatus::Draft)
                                    ->required()
                                    ->live(),
                                DateTimePicker::make('published_at')
                                    ->label(__('admin.resources.blog_post.fields.publish_date'))
                                    ->timezone(fn ($get) => self::resolveInputTimezone($get('viewer_timezone')))
                                    ->maxDate(fn ($get) => $get('status') === PostStatus::Published->value
                                        ? now(self::resolveInputTimezone($get('viewer_timezone')))
                                        : null)
                                    ->helperText(fn ($get) => match ($get('status')) {
                                        PostStatus::Published->value => 'Tanggal publikasi tidak boleh melebihi waktu saat ini (timezone lokal Anda, dibandingkan ke UTC sistem).',
                                        PostStatus::Scheduled->value => 'Tanggal publikasi wajib lebih besar dari waktu saat ini (timezone lokal Anda, dibandingkan ke UTC sistem).',
                                        default => __('admin.resources.blog_post.fields.publish_date_helper'),
                                    })
                                    ->rules([
                                        fn ($get): \Closure => function (string $attribute, $value, \Closure $fail) use ($get) {
                                            if ($get('status') === PostStatus::Published->value && $value) {
                                                $publishedAtUtc = self::normalizeInputDateTimeToUtc($value, $get('viewer_timezone'));
                                                $nowUtc = Carbon::now('UTC');

                                                if ($publishedAtUtc->isAfter($nowUtc)) {
                                                    $fail('Tanggal publikasi tidak boleh melebihi waktu saat ini untuk status Published. '.
                                                          'Waktu yang Anda pilih: '.$publishedAtUtc->format('Y-m-d H:i:s').' UTC, '.
                                                          'Waktu sekarang: '.$nowUtc->format('Y-m-d H:i:s').' UTC');
                                                }
                                            }

                                            if ($get('status') === PostStatus::Scheduled->value) {
                                                if (empty($value)) {
                                                    $fail(__('admin.resources.blog_post.notifications.invalid_schedule_date_body'));

                                                    return;
                                                }

                                                $publishedAtUtc = self::normalizeInputDateTimeToUtc($value, $get('viewer_timezone'));
                                                $nowUtc = Carbon::now('UTC');

                                                if (! $publishedAtUtc->isAfter($nowUtc)) {
                                                    $fail(__('admin.resources.blog_post.notifications.invalid_schedule_date_body'));
                                                }
                                            }
                                        },
                                    ]),
                                Select::make('tags')
                                    ->label(__('admin.resources.blog_post.fields.tags'))
                                    ->relationship('tags', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpanFull()
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
