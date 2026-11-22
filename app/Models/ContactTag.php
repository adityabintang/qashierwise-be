<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactTag extends Model
{
    use HasFactory;

    protected $table = 'contact_tags';

    /**
     * The "booted" method of the model.
     * Apply Row Level Security - only show tags for authenticated user.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userTags', function (Builder $builder) {
            if (auth()->check()) {
                $userId = auth()->user()->getEffectiveUserId();
                $builder->where('user_id', $userId);
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'color',
    ];

    /**
     * Preset color palette for tags.
     */
    public const PRESET_COLORS = [
        '#a855f7', // purple
        '#3b82f6', // blue
        '#06b6d4', // cyan
        '#14b8a6', // teal
        '#22c55e', // green
        '#84cc16', // lime
        '#eab308', // yellow
        '#f97316', // orange
        '#ef4444', // red
        '#ec4899', // pink
        '#f43f5e', // rose
        '#64748b', // slate
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The contacts that have this tag.
     */
    public function contacts()
    {
        return $this->belongsToMany(
            WhatsAppContact::class,
            'contact_tag_pivot',
            'contact_tag_id',
            'whatsapp_contact_id'
        )->withTimestamps();
    }
}
