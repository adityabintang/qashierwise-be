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

    const STATUS_PAID = 'paid';

    const STATUS_CANCELLED = 'cancelled';

    /**
     * Order source constants.
     */
    const DELIVERY_TYPE_PICKUP = 'pickup';

    const DELIVERY_TYPE_DELIVERY = 'delivery';

    /**
     * Fulfillment lifecycle constants (separate from payment `status`).
     */
    const FULFILLMENT_AWAITING = 'awaiting_confirmation';

    const FULFILLMENT_CONFIRMED = 'confirmed';

    const FULFILLMENT_OUT_FOR_DELIVERY = 'out_for_delivery';

    const FULFILLMENT_DELIVERED = 'delivered';

    const FULFILLMENT_COMPLAINT = 'complaint';

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
        'delivery_type',
        'alamat',
        'ongkir',
        'catatan',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'fulfillment_status',
        'delivery_driver_id',
        'courier_name',
        'courier_phone',
        'delivery_token',
        'proof_image_url',
        'customer_lat',
        'customer_lng',
        'confirmed_at',
        'out_for_delivery_at',
        'delivered_at',
        'complaint_note',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ongkir' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'customer_lat' => 'decimal:7',
            'customer_lng' => 'decimal:7',
            'confirmed_at' => 'datetime',
            'out_for_delivery_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * Get the assigned delivery driver (if any).
     */
    public function deliveryDriver(): BelongsTo
    {
        return $this->belongsTo(DeliveryDriver::class);
    }

    /**
     * Is this a delivery (not pickup) order?
     */
    public function isDelivery(): bool
    {
        return $this->delivery_type === self::DELIVERY_TYPE_DELIVERY;
    }

    /**
     * Is this a pickup order?
     */
    public function isPickup(): bool
    {
        return $this->delivery_type === self::DELIVERY_TYPE_PICKUP;
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
