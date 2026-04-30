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
                // Include merchant's own tags AND shared system tags (user_id = null)
                $builder->where(function ($q) use ($userId) {
                    $q->where('user_id', $userId)->orWhere('is_system', true);
                });
            } else {
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
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

    /**
     * System tags managed by ML auto-tagging — not editable by merchant.
     */
    public const SYSTEM_TAGS = [
        ['name' => 'potential_buyer',   'color' => '#22c55e'],  // green
        ['name' => 'inquiry',           'color' => '#3b82f6'],  // blue
        ['name' => 'complaint',         'color' => '#ef4444'],  // red
        ['name' => 'churning',          'color' => '#f97316'],  // orange
        ['name' => 'feedback_positive', 'color' => '#a855f7'],  // purple
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
