<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reservation extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Reservation status constants.
     */
    const STATUS_PENDING_PAYMENT = 'pending_payment';

    const STATUS_CONFIRMED = 'confirmed';

    const STATUS_COMPLETED = 'completed';

    const STATUS_CANCELLED = 'cancelled';

    /**
     * Payment type constants.
     */
    const PAYMENT_TYPE_DP = 'dp';

    const PAYMENT_TYPE_FULL = 'full';

    /**
     * Payment method constants.
     */
    const PAYMENT_METHOD_QRIS = 'qris';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'customer_name',
        'phone',
        'email',
        'reservation_date',
        'reservation_time',
        'guest_count',
        'table_id',
        'notes',
        'selected_products',
        'payment_type',
        'payment_method',
        'qris_transaction_id',
        'total_amount',
        'paid_amount',
        'remaining_amount',
        'status',
        'order_id',
        'calendar_event_id',
        'notified_at',
        'cancelled_reason',
        'scheduled_reminder_jobs',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'guest_count' => 'integer',
            'selected_products' => 'array',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'remaining_amount' => 'decimal:2',
            'notified_at' => 'datetime',
            'scheduled_reminder_jobs' => 'array',
        ];
    }

    /**
     * Get the user that owns this reservation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store associated with this reservation.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the table associated with this reservation.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the QRIS transaction for this reservation.
     */
    public function qrisTransaction(): BelongsTo
    {
        return $this->belongsTo(QrisTransaction::class);
    }

    /**
     * Scope for reservations on a specific date.
     */
    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('reservation_date', $date);
    }

    /**
     * Scope for confirmed reservations.
     */
    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    /**
     * Scope for pending payment reservations.
     */
    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING_PAYMENT);
    }

    /**
     * Calculate total amount based on selected products and config.
     */
    public function calculateTotalAmount(): float
    {
        // This will be implemented in the service layer
        // For now, return the stored amount
        return (float) $this->total_amount;
    }

    /**
     * Calculate deposit amount based on total and percentage.
     */
    public function calculateDpAmount(): float
    {
        $config = ReservationConfig::where('user_id', $this->user_id)
            ->where('store_id', $this->store_id)
            ->first();

        if (! $config) {
            return $this->total_amount * 0.5; // Default 50%
        }

        return $this->total_amount * ($config->dp_percentage / 100);
    }

    /**
     * Check if reservation is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if reservation can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_PAYMENT,
            self::STATUS_CONFIRMED,
        ]);
    }
}
