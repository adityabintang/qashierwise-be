<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReservationFlowConfig extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_DEPRECATED = 'deprecated';

    public const DEFAULT_EVENT_TYPES = [
        'regular' => 'Makan biasa',
        'birthday' => 'Ulang Tahun',
        'meeting' => 'Meeting/Bisnis',
        'anniversary' => 'Anniversary',
        'family' => 'Gathering Keluarga',
        'other' => 'Lainnya',
    ];

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
            'available_table_ids' => 'array',
            'available_product_ids' => 'array',
            'enabled_event_types' => 'array',
            'enable_table_selection' => 'boolean',
            'enable_menu_selection' => 'boolean',
            'require_menu_selection' => 'boolean',
            'enable_payment' => 'boolean',
            'allow_full_payment' => 'boolean',
            'allow_dp_payment' => 'boolean',
            'enable_qris' => 'boolean',
            'enable_cash' => 'boolean',
            'require_email' => 'boolean',
            'require_event_type' => 'boolean',
            'table_fee' => 'decimal:2',
            'dp_percentage' => 'decimal:2',
            'max_advance_days' => 'integer',
            'min_advance_hours' => 'integer',
            'max_guests' => 'integer',
            'min_guests' => 'integer',
            'time_interval' => 'integer',
        ];
    }

    // ==================== Relationships ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availableTables(): HasMany
    {
        return $this->hasMany(Table::class, 'user_id', 'user_id')
            ->when($this->available_table_ids, function ($query) {
                $query->whereIn('id', $this->available_table_ids);
            });
    }

    public function availableProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id', 'user_id')
            ->where('is_active', true)
            ->when($this->available_product_ids, function ($query) {
                $query->whereIn('id', $this->available_product_ids);
            });
    }

    // ==================== Helpers ====================

    public function isPublished(): bool
    {
        return $this->flow_status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->flow_status === self::STATUS_DRAFT;
    }

    public function hasFlow(): bool
    {
        return !empty($this->flow_id);
    }

    /**
     * Generate time slots based on config.
     */
    public function getTimeSlots(): array
    {
        $slots = [];
        $opening = \Carbon\Carbon::parse($this->opening_time);
        $closing = \Carbon\Carbon::parse($this->closing_time);
        $interval = $this->time_interval ?? 60;
        $blockedTimes = $this->blocked_times ?? [];

        while ($opening <= $closing) {
            $timeStr = $opening->format('H:i');
            
            if (!in_array($timeStr, $blockedTimes)) {
                $slots[] = [
                    'id' => $timeStr,
                    'title' => "{$timeStr} WIB",
                ];
            }
            
            $opening->addMinutes($interval);
        }

        return $slots;
    }

    /**
     * Get available dates based on config.
     */
    public function getAvailableDates(): array
    {
        $dates = [];
        $start = \Carbon\Carbon::tomorrow();
        $maxDays = $this->max_advance_days ?? 30;
        $operatingDays = $this->operating_days ?? [1, 2, 3, 4, 5, 6, 7];

        for ($i = 0; $i < $maxDays; $i++) {
            $date = $start->copy()->addDays($i);
            
            // Check if day of week is in operating days (1=Mon, 7=Sun)
            if (in_array($date->dayOfWeekIso, $operatingDays)) {
                $dates[] = [
                    'id' => $date->format('Y-m-d'),
                    'title' => $date->locale('id')->isoFormat('ddd, DD MMM YYYY'),
                ];
            }
        }

        return $dates;
    }

    /**
     * Get guest count options.
     */
    public function getGuestCountOptions(): array
    {
        $options = [];
        $min = $this->min_guests ?? 1;
        $max = $this->max_guests ?? 20;

        for ($i = $min; $i <= $max; $i++) {
            $options[] = [
                'id' => (string) $i,
                'title' => "{$i} orang",
            ];
        }

        return $options;
    }

    /**
     * Get event types for dropdown.
     */
    public function getEventTypes(): array
    {
        $enabledTypes = $this->enabled_event_types ?? array_keys(self::DEFAULT_EVENT_TYPES);
        
        return collect($enabledTypes)
            ->filter(fn ($type) => isset(self::DEFAULT_EVENT_TYPES[$type]))
            ->map(fn ($type) => [
                'id' => $type,
                'title' => self::DEFAULT_EVENT_TYPES[$type],
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get payment type options.
     */
    public function getPaymentTypes(float $grandTotal): array
    {
        $dpAmount = ceil($grandTotal * ($this->dp_percentage / 100));
        $options = [];

        if ($this->allow_dp_payment) {
            $options[] = [
                'id' => 'dp',
                'title' => "DP ({$this->dp_percentage}%) - " . $this->formatCurrency($dpAmount),
            ];
        }

        if ($this->allow_full_payment) {
            $options[] = [
                'id' => 'lunas',
                'title' => "Lunas - " . $this->formatCurrency($grandTotal),
            ];
        }

        return $options;
    }

    /**
     * Get payment method options.
     */
    public function getPaymentMethods(): array
    {
        $methods = [];

        if ($this->enable_qris) {
            $methods[] = ['id' => 'qris', 'title' => 'QRIS'];
        }

        if ($this->enable_cash) {
            $methods[] = ['id' => 'cash', 'title' => 'Bayar di Tempat (Cash)'];
        }

        return $methods;
    }

    /**
     * Calculate DP amount.
     */
    public function calculateDpAmount(float $total): float
    {
        return ceil($total * ($this->dp_percentage / 100));
    }

    /**
     * Format currency.
     */
    private function formatCurrency(float $amount): string
    {
        return 'Rp' . number_format($amount, 0, ',', '.');
    }

    /**
     * Get or create config for user.
     */
    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'flow_name' => 'Reservasi',
                'flow_status' => self::STATUS_DRAFT,
                'operating_days' => [1, 2, 3, 4, 5, 6, 7],
                'enabled_event_types' => array_keys(self::DEFAULT_EVENT_TYPES),
            ]
        );
    }
}
