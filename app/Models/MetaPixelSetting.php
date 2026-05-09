<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetaPixelSetting extends Model
{
    protected $table = 'meta_pixel_settings';

    protected $fillable = [
        'user_id',
        'pixel_id',
        'access_token',
        'is_active',
        'test_event_code',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'access_token' => 'encrypted',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
