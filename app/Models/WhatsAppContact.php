<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppContact extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_contacts';

    protected $fillable = [
        'whatsapp_account_id',
        'wa_id',
        'phone_number',
        'name',
        'profile_name',
        'labels',
        'custom_fields',
        'is_blocked',
        'last_message_at'
    ];

    protected $casts = [
        'labels' => 'array',
        'custom_fields' => 'array',
        'is_blocked' => 'boolean',
        'last_message_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_contact_id');
    }
}
