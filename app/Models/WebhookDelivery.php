<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_id', 'event_type', 'payload', 'status', 'attempt_count', 'last_error', 'next_retry_at'];

    protected $casts = [
        'payload' => 'array',
        'next_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function markDelivered(): void
    {
        $this->update([
            'status' => 'delivered',
            'next_retry_at' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'last_error' => $error,
        ]);
    }

    public function scheduleRetry(): void
    {
        $attempt = $this->attempt_count + 1;

        $delays = [
            1 => 5,      // 5 minutes
            2 => 30,     // 30 minutes
            3 => 120,    // 2 hours
            4 => 720,    // 12 hours
        ];

        if ($attempt >= 5) {
            $this->update([
                'status' => 'failed',
                'next_retry_at' => null,
            ]);
            return;
        }

        $delayMinutes = $delays[$attempt] ?? 1440; // Default 24 hours

        $this->update([
            'status' => 'pending',
            'attempt_count' => $attempt,
            'next_retry_at' => Carbon::now()->addMinutes($delayMinutes),
        ]);
    }

    public function scopeReadyForRetry($query)
    {
        return $query->where('status', 'pending')
            ->whereNotNull('next_retry_at')
            ->where('next_retry_at', '<=', Carbon::now());
    }
}
