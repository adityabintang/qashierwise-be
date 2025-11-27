<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_accounts';

    protected $fillable = [
        'user_id',
        'phone_number_id',
        'business_account_id',
        'access_token',
        'is_active',
        'webhook_config',
        'display_name',
        'quality_rating',
        'verified_name',
        'about',
        'address',
        'description',
        'email',
        'vertical',
        'websites',
        'profile_picture_url',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'webhook_config' => 'array',
        'websites' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function contacts()
    {
        return $this->hasMany(WhatsAppContact::class, 'whatsapp_account_id');
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_account_id');
    }

    public function templates()
    {
        return $this->hasMany(WhatsAppTemplate::class, 'whatsapp_account_id');
    }

    public function media()
    {
        return $this->hasMany(WhatsAppMedia::class, 'whatsapp_account_id');
    }
}
