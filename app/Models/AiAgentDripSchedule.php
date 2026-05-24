<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One scheduled drip touch. Rows are created when a "stuck" condition is
 * detected (cart abandon, etc) and consumed by a periodic dispatcher.
 *
 * status transitions:
 *   pending → sent       (dispatcher fired the message)
 *   pending → cancelled  (conversation resolved before fire_at)
 *   pending → skipped    (anti-spam / quiet hours / session window)
 */
class AiAgentDripSchedule extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_SKIPPED = 'skipped';

    public const SEQ_ABANDONED_CART = 'abandoned_cart';

    public const SEQ_ABANDONED_PAYMENT = 'abandoned_payment';

    public const SEQ_RE_ENGAGEMENT = 're_engagement';

    protected $fillable = [
        'conversation_id',
        'sequence',
        'step',
        'fire_at',
        'status',
        'template_name',
        'payload',
        'fired_at',
    ];

    protected function casts(): array
    {
        return [
            'fire_at' => 'datetime',
            'fired_at' => 'datetime',
            'payload' => 'array',
            'step' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiAgentConversation::class, 'conversation_id');
    }
}
