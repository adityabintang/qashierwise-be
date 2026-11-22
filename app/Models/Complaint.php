<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer complaint raised via the WhatsApp "Complain" button after a
 * delivery. While status=open the AI bot stays paused for the customer's number;
 * resolving it (with a resolution note) re-activates the bot and notifies them.
 */
class Complaint extends Model
{
    use HasFactory;

    const STATUS_OPEN = 'open';

    const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'order_id',
        'user_id',
        'whatsapp_contact_id',
        'customer_name',
        'customer_phone',
        'status',
        'resolution_note',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappContact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function scopeOpen($query)
    {
        return $query->where('status', self::STATUS_OPEN);
    }
}
