<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservationFlowConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'flow_id',
        'flow_name',
        'flow_status',
        'opening_time',
        'closing_time',
        'time_interval',
        'blocked_times',
        'operating_days',
        'max_advance_days',
        'min_advance_hours',
        'max_guests',
        'min_guests',
        'enable_table_selection',
        'available_table_ids',
        'enable_menu_selection',
        'available_product_ids',
        'require_menu_selection',
        'enable_payment',
        'table_fee',
        'dp_percentage',
        'allow_full_payment',
        'allow_dp_payment',
        'enable_qris',
        'enable_cash',
        'require_email',
        'require_event_type',
        'enabled_event_types',
        'header_text',
        'body_text',
        'footer_text',
        'cta_text',
    ];

    protected function casts(): array
    {
        return [
            'blocked_times' => 'array',
            'operating_days' => 'array',
            'enable_table_selection' => 'boolean',
            'available_table_ids' => 'array',
            'enable_menu_selection' => 'boolean',
            'available_product_ids' => 'array',
            'require_menu_selection' => 'boolean',
            'enable_payment' => 'boolean',
            'table_fee' => 'decimal:2',
            'dp_percentage' => 'decimal:2',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'enable_qris' => 'boolean',
            'enable_cash' => 'boolean',
            'require_email' => 'boolean',
            'require_event_type' => 'boolean',
            'enabled_event_types' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
