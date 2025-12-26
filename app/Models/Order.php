<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    /**
     * Order status constants.
     */
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_PAID = 'paid';

    /**
     * Order source constants.
     */
    const SOURCE_POS = 'pos';
    const SOURCE_WHATSAPP_AI = 'whatsapp_ai';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'store_id',
        'table_id',
        'pos_user_id',
        'order_number',
        'status',
        'source',
        'customer_name',
        'customer_phone',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * Get the store that owns this order.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the table associated with this order.
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    /**
     * Get the POS user who created this order.
     */
    public function posUser(): BelongsTo
    {
        return $this->belongsTo(PosUser::class);
    }

    /**
     * Get the items for this order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payments for this order.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the QRIS transaction linked to this order.
     */
    public function qrisTransaction(): HasOne
    {
        return $this->hasOne(QrisTransaction::class, 'linked_order_id');
    }

    /**
     * Scope to filter orders from WhatsApp AI.
     */
    public function scopeFromWhatsAppAi($query)
    {
        return $query->where('source', self::SOURCE_WHATSAPP_AI);
    }

    /**
     * Scope to filter orders from POS.
     */
    public function scopeFromPos($query)
    {
        return $query->where('source', self::SOURCE_POS);
    }

    /**
     * Check if order is from WhatsApp AI.
     */
    public function isFromWhatsAppAi(): bool
    {
        return $this->source === self::SOURCE_WHATSAPP_AI;
    }
}
