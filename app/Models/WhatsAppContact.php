<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'user_id',
        'wa_id',
        'name',
        'profile_pic_url',
        'last_message_at',
        'last_message_text',
        'unread_count',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    protected $appends = ['phone_number'];

    public function user()
    {
        return $this->belongsTo(User::class);
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
