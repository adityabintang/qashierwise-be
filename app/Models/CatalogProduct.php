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
        'category',
    ];

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
