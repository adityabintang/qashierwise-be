<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtwaSetting extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['events_token'];

    protected $casts = [
        'events_token' => 'encrypted',
        'enabled_at' => 'immutable_datetime',
        'first_enabled_at' => 'immutable_datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id')->withoutGlobalScope('userAccounts');
    }
}
