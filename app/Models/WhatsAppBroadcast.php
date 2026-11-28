<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppBroadcast extends Model
{
    protected $table = 'whatsapp_broadcasts';

    protected $guarded = ['id'];

    protected $hidden = ['request_hash', 'idempotency_key', 'template_payload', 'template_hash', 'portfolio_id'];

    protected $casts = ['template_payload' => 'array', 'preview' => 'array'];

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsAppBroadcastRecipient::class, 'whatsapp_broadcast_id');
    }

    public function scopeWithProgress($query)
    {
        return $query->withCount([
            'recipients as queued_count' => fn ($rows) => $rows->whereIn('status', ['queued', 'sending']),
            'recipients as accepted_count' => fn ($rows) => $rows->whereNotNull('accepted_at'),
            'recipients as delivered_count' => fn ($rows) => $rows->whereIn('status', ['delivered', 'read']),
            'recipients as failed_count' => fn ($rows) => $rows->where('status', 'failed'),
            'recipients as skipped_count' => fn ($rows) => $rows->where('status', 'skipped'),
            'recipients as review_count' => fn ($rows) => $rows->where('status', 'review'),
        ]);
    }
}
