<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    /**
     * The "booted" method of the model.
     * Apply Row Level Security - only show contacts for authenticated user with ACTIVE WhatsApp account
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userContacts', function (Builder $builder) {
            if (auth()->check()) {
                $userId = auth()->id();

                // Get user's active WhatsApp account
                $activeAccount = WhatsAppAccount::where('user_id', $userId)
                    ->where('is_active', true)
                    ->first();

                if ($activeAccount) {
                    // Only show contacts for this user AND this specific phone number
                    $builder->where('user_id', $userId)
                        ->where('phone_number_id', $activeAccount->phone_number_id);
                } else {
                    // No active account = no data shown
                    $builder->whereRaw('1 = 0');
                }
            } else {
                // If not authenticated, return no results
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'phone_number_id',
        'wa_id',
        'name',
        'profile_pic_url',
        'last_message_at',
        'last_message_text',
        'unread_count',
        'ai_active',
        'ctwa_clid',
        'first_source_type',
        'ctwa_headline',
        'attribution_expires_at',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
        'ai_active' => 'boolean',
        'attribution_expires_at' => 'datetime',
    ];

    protected $appends = ['phone_number'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'phone_number_id', 'phone_number_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'contact_id');
    }

    public function latestMessage()
    {
        return $this->hasOne(WhatsAppMessage::class, 'contact_id')->latest();
    }

    /**
     * The tags assigned to this contact.
     */
    public function tags()
    {
        return $this->belongsToMany(
            ContactTag::class,
            'contact_tag_pivot',
            'whatsapp_contact_id',
            'contact_tag_id'
        )->withTimestamps();
    }

    /**
     * Get phone number (formatted wa_id)
     */
    public function getPhoneNumberAttribute()
    {
        $waId = $this->wa_id;

        if (! $waId) {
            return null;
        }

        // Add + prefix if not present
        if (! str_starts_with($waId, '+')) {
            return '+'.$waId;
        }

        return $waId;
    }
}
