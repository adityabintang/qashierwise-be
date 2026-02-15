<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationConfig extends Model
{
    use HasFactory;

    /**
     * Default deposit percentage constant.
     */
    const DEFAULT_DP_PERCENTAGE = 50.00;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'store_id',
        'is_active',
        'available_slots',
        'capacity_per_slot',
        'guest_options',
        'dp_percentage',
        'allow_full_payment',
        'allow_dp_payment',
        'reservation_fee',
        'available_tables',
        'available_products',
        'enable_menu_selection',
        'require_menu_selection',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_slots' => 'array',
            'capacity_per_slot' => 'integer',
            'guest_options' => 'array',
            'dp_percentage' => 'decimal:2',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'reservation_fee' => 'integer',
            'available_tables' => 'array',
            'available_products' => 'array',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this config.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store that owns this config.
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get available dates formatted for dropdown.
     */
    public function getAvailableDatesFormatted(): array
    {
        if (! $this->available_slots) {
            return [];
        }

        return collect($this->available_slots)->map(function ($date) {
            return [
                'id' => $date,
                'title' => \Carbon\Carbon::parse($date)->isoFormat('ddd, D MMM YYYY HH:mm'),
            ];
        })->toArray();
    }
}
