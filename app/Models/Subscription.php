<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'polar_subscription_id',
        'polar_customer_id',
        'midtrans_subscription_id',
        'midtrans_customer_id',
        'provider',
        'plan_name',
        'status',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the current period end as a Carbon instance.
     */
    protected function getPeriodEnd(): Carbon
    {
        $value = $this->current_period_end;
        
        if ($value instanceof Carbon) {
            return $value;
        }
        
        return Carbon::parse($value);
    }

    /**
     * Get the cancelled_at as a Carbon instance or null.
     */
    protected function getCancelledAtValue(): ?Carbon
    {
        $value = $this->cancelled_at;
        
        if ($value === null) {
            return null;
        }
        
        if ($value instanceof Carbon) {
            return $value;
        }
        
        return Carbon::parse($value);
    }

    /**
     * Check if the subscription is active.
     * A subscription is active if status is 'active' and current period has not ended.
     */
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        return $this->getPeriodEnd()->isFuture();
    }

    /**
     * Check if the subscription is cancelled.
     * A subscription is cancelled if cancelled_at is set.
     */
    public function isCancelled(): bool
    {
        return $this->getCancelledAtValue() !== null;
    }

    /**
     * Check if the subscription is expired.
     * A subscription is expired if the current period end date has passed.
     */
    public function isExpired(): bool
    {
        return $this->getPeriodEnd()->isPast();
    }
}
