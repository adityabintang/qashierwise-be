<?php

namespace App\Models;

use App\Enums\PostStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    /** @use HasFactory<\Database\Factories\BlogPostFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'blog_category_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'seo_title',
        'seo_description',
        'seo_image',
    ];

    protected $appends = ['featured_image_url', 'seo_image_url'];

    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected function featuredImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->featured_image ? Storage::disk('r2')->url($this->featured_image) : null
        );
    }

    protected function seoImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->seo_image 
                ? Storage::disk('r2')->url($this->seo_image) 
                : ($this->featured_image ? Storage::disk('r2')->url($this->featured_image) : null)
        );
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (BlogPost $post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class, 'blog_category_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_blog_tag');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Published);
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Draft);
    }

    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', PostStatus::Scheduled)
            ->where('published_at', '>', now());
    }
}
