<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CatalogProduct extends Model
{
    protected $fillable = [
        'user_id',
        'catalog_id',
        'meta_product_id',
        'retailer_id',
        'name',       // nullable — stub rows created by stock-only updates won't have a name yet
        'price',
        'currency',
        'stock_quantity',
        'is_available',
        'availability',
        'category',
    ];

    // Availability values that make is_available = true
    public const AVAILABLE_STATUSES = ['in stock', 'preorder', 'available for order', 'pending'];

    public static function isAvailableFromStatus(string $availability): bool
    {
        return in_array($availability, self::AVAILABLE_STATUSES, true);
    }

    protected function casts(): array
    {
        return [
            'price'          => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_available'   => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
