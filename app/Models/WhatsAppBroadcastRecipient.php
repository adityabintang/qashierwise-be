<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppBroadcastRecipient extends Model
{
    protected $table = 'whatsapp_broadcast_recipients';

    protected $guarded = ['id'];

    protected $hidden = ['callback_id'];

    protected $casts = ['attempted_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(WhatsAppBroadcast::class, 'whatsapp_broadcast_id');
    }
}
