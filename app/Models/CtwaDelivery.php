<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtwaDelivery extends Model
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const FAILED = 'failed';

    public const UNATTRIBUTED = 'unattributed';

    protected $guarded = ['id'];

    protected $casts = [
        'accepted_at' => 'immutable_datetime',
        'next_attempt_at' => 'immutable_datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(CtwaOrder::class, 'ctwa_order_id');
    }
}
