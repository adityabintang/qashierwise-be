<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    use HasFactory;

    /**
     * Payment type constants.
     */
    public const PAYMENT_TYPE_DP = 'dp';

    public const PAYMENT_TYPE_LUNAS = 'lunas';

    /**
     * Payment method constants.
     */
    public const PAYMENT_METHOD_QRIS = 'qris';

    public const PAYMENT_METHOD_CASH = 'cash';

    protected $fillable = [
        'user_id',
        'whatsapp_contact_id',
        'customer_name',
        'phone',
        'reservation_date',
        'reservation_time',
        'guest_count',
        'email',
        'event_type',
        'special_notes',
        'preferences',
        'deposit',
        'deposit_paid',
        'pre_order_items',
        'flow_token',
        'flow_id',
        'status',
        'cancellation_reason',
        'confirmed_at',
        'cancelled_at',
        'completed_at',
        'reminder_sent',
        'reminder_sent_at',
        // New payment fields
        'table_id',
        'payment_type',
        'payment_method',
        'payment_label',
        'qris_transaction_id',
        'table_fee',
        'menu_total',
        'total_amount',
        'paid_amount',
        'order_id',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'reservation_time' => 'datetime:H:i',
            'preferences' => 'array',
            'pre_order_items' => 'array',
            'deposit' => 'decimal:2',
            'deposit_paid' => 'boolean',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
            'reminder_sent' => 'boolean',
            'reminder_sent_at' => 'datetime',
            // New payment field casts
            'table_fee' => 'decimal:2',
            'menu_total' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }

    // ==================== Relationships ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappContact(): BelongsTo
    {
        return $this->belongsTo(WhatsAppContact::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function qrisTransaction(): BelongsTo
    {
        return $this->belongsTo(QrisTransaction::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ==================== Scopes ====================

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeConfirmed($query)
    {
        return $query->where('status', 'confirmed');
    }

    public function scopeUpcoming($query)
    {
        return $query->where('reservation_date', '>=', now()->toDateString())
            ->whereIn('status', ['pending', 'confirmed']);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('reservation_date', now()->toDateString());
    }

    public function scopeNeedsReminder($query)
    {
        return $query->where('reminder_sent', false)
            ->where('status', 'confirmed')
            ->whereDate('reservation_date', now()->addDay()->toDateString());
    }

    // ==================== Actions ====================

    public function confirm(): bool
    {
        return $this->update([
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function cancel(?string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function complete(): bool
    {
        return $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markNoShow(): bool
    {
        return $this->update([
            'status' => 'no_show',
            'completed_at' => now(),
        ]);
    }

    public function markReminderSent(): bool
    {
        return $this->update([
            'reminder_sent' => true,
            'reminder_sent_at' => now(),
        ]);
    }

    // ==================== Helpers ====================

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isConfirmed(): bool
    {
        return $this->status === 'confirmed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->reservation_date->format('d M Y');
    }

    public function getFormattedTimeAttribute(): string
    {
        return $this->reservation_time->format('H:i');
    }

    public function getPreferencesListAttribute(): array
    {
        $labels = [
            'window' => 'Dekat jendela',
            'quiet' => 'Area tenang',
            'smoking' => 'Area smoking',
            'baby_chair' => 'Kursi bayi',
        ];

        return collect($this->preferences ?? [])
            ->map(fn ($pref) => $labels[$pref] ?? $pref)
            ->toArray();
    }

    public function getEventTypeLabelAttribute(): ?string
    {
        $labels = [
            'regular' => 'Makan biasa',
            'birthday' => 'Ulang tahun',
            'meeting' => 'Meeting/Bisnis',
            'anniversary' => 'Anniversary',
            'family' => 'Gathering Keluarga',
            'other' => 'Lainnya',
        ];

        return $labels[$this->event_type] ?? $this->event_type;
    }

    // ==================== Payment Helpers ====================

    /**
     * Check if reservation is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->payment_type === self::PAYMENT_TYPE_LUNAS
            || $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if reservation has deposit paid.
     */
    public function hasDepositPaid(): bool
    {
        return $this->deposit_paid || $this->paid_amount > 0;
    }

    /**
     * Get remaining amount to pay.
     */
    public function getRemainingAmountAttribute(): float
    {
        return max(0, $this->total_amount - $this->paid_amount);
    }

    /**
     * Get formatted total amount.
     */
    public function getFormattedTotalAttribute(): string
    {
        return 'Rp'.number_format($this->total_amount, 0, ',', '.');
    }

    /**
     * Get formatted paid amount.
     */
    public function getFormattedPaidAttribute(): string
    {
        return 'Rp'.number_format($this->paid_amount, 0, ',', '.');
    }

    /**
     * Get formatted remaining amount.
     */
    public function getFormattedRemainingAttribute(): string
    {
        return 'Rp'.number_format($this->remaining_amount, 0, ',', '.');
    }

    /**
     * Get payment type label for display.
     */
    public function getPaymentTypeLabelAttribute(): string
    {
        return match ($this->payment_type) {
            self::PAYMENT_TYPE_DP => 'DP (50%)',
            self::PAYMENT_TYPE_LUNAS => 'Lunas',
            default => '-',
        };
    }

    /**
     * Get payment method label for display.
     */
    public function getPaymentMethodLabelAttribute(): string
    {
        return match ($this->payment_method) {
            self::PAYMENT_METHOD_QRIS => 'QRIS',
            self::PAYMENT_METHOD_CASH => 'Bayar di Tempat',
            default => '-',
        };
    }

    /**
     * Scope for reservations with DP payment.
     */
    public function scopeWithDp($query)
    {
        return $query->where('payment_type', self::PAYMENT_TYPE_DP);
    }

    /**
     * Scope for fully paid reservations.
     */
    public function scopeFullyPaid($query)
    {
        return $query->where('payment_type', self::PAYMENT_TYPE_LUNAS);
    }

    /**
     * Scope for reservations with QRIS payment.
     */
    public function scopeWithQris($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_QRIS);
    }

    /**
     * Scope for reservations with cash payment.
     */
    public function scopeWithCash($query)
    {
        return $query->where('payment_method', self::PAYMENT_METHOD_CASH);
    }
}
