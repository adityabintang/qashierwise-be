<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppAccount extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_accounts';

    /**
     * The "booted" method of the model.
     * Apply Row Level Security - only show accounts for authenticated user
     */
    protected static function booted(): void
    {
        static::addGlobalScope('userAccounts', function (Builder $builder) {
            if (auth()->check()) {
                $builder->where('user_id', auth()->id());
            } else {
                // If not authenticated, return no results (security default)
                $builder->whereRaw('1 = 0');
            }
        });
    }

    protected $fillable = [
        'user_id',
        'phone_number_id',
        'business_account_id',
        'waba_id',
        'access_token',
        'token_expires_at',
        'is_active',
        'coexistence_enabled',
        'connection_method',
        'webhook_config',
        'display_phone_number', // Maps to display_name in API
        'quality_rating',
        'name', // Maps to verified_name in API
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
        'coexistence_enabled' => 'boolean',
        'webhook_config' => 'array',
        'websites' => 'array',
        'token_expires_at' => 'datetime',
        'access_token' => 'encrypted',
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
