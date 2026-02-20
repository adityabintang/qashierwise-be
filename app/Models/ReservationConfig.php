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
        'available_slots_metadata',
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
        // Reminder settings
        'reminder_enabled',
        'reminder_template',
        'reminder_template_language',
        'reminder_param_mapping',
        'reminder_timing',
        'scheduled_reminder_jobs',
        // Auto cleanup settings
        'auto_cleanup_enabled',
        'auto_cleanup_reference_date',
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
            'available_slots_metadata' => 'array',
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
            // Reminder casts
            'reminder_enabled' => 'boolean',
            'reminder_param_mapping' => 'array',
            'reminder_timing' => 'array',
            'scheduled_reminder_jobs' => 'array',
            // Auto cleanup casts
            'auto_cleanup_enabled' => 'boolean',
            'auto_cleanup_reference_date' => 'date',
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

        return collect($this->available_slots)->map(function ($slot) {
            // Handle both old format (string) and new format (array with datetime and capacity)
            $datetime = is_array($slot) ? ($slot['datetime'] ?? $slot[0] ?? '') : $slot;
            $capacity = is_array($slot) ? ($slot['capacity'] ?? $slot[1] ?? null) : null;

            return [
                'id' => $datetime,
                'title' => \Carbon\Carbon::parse($datetime)->isoFormat('ddd, D MMM YYYY HH:mm'),
                'capacity' => $capacity,
            ];
        })->toArray();
    }

    /**
     * Get available fields for reminder parameter mapping.
     * These are the reservation fields that can be used as template parameters.
     */
    public static function getAvailableMappingFields(): array
    {
        return [
            ['key' => 'customer_name', 'label' => 'Nama Pelanggan', 'type' => 'string'],
            ['key' => 'customer_phone', 'label' => 'Nomor Telepon', 'type' => 'string'],
            ['key' => 'reservation_date', 'label' => 'Tanggal Reservasi', 'type' => 'date'],
            ['key' => 'reservation_time', 'label' => 'Waktu Reservasi', 'type' => 'time'],
            ['key' => 'guest_count', 'label' => 'Jumlah Tamu', 'type' => 'number'],
            ['key' => 'store_name', 'label' => 'Nama Toko', 'type' => 'string'],
            ['key' => 'store_address', 'label' => 'Alamat Toko', 'type' => 'string'],
            ['key' => 'reservation_notes', 'label' => 'Catatan Reservasi', 'type' => 'string'],
        ];
    }

    /**
     * Check if reminder is enabled and configured properly.
     */
    public function isReminderConfigured(): bool
    {
        return $this->reminder_enabled
            && ! empty($this->reminder_template)
            && ! empty($this->reminder_param_mapping)
            && ! empty($this->reminder_timing);
    }

    /**
     * Get unique dates from available slots (grouped by date, not datetime).
     */
    public function getUniqueDatesWithCapacity(): array
    {
        if (! $this->available_slots) {
            return [];
        }

        $dateGroups = collect($this->available_slots)->groupBy(function ($slot) {
            $datetime = is_array($slot) ? ($slot['datetime'] ?? $slot[0] ?? '') : $slot;

            return \Carbon\Carbon::parse($datetime)->toDateString();
        });

        return $dateGroups->map(function ($slots, $date) {
            // Get capacity from first slot (they should all have same capacity)
            $firstSlot = $slots->first();
            $capacity = is_array($firstSlot) ? ($firstSlot['capacity'] ?? $firstSlot[1] ?? null) : null;

            return [
                'date' => $date,
                'title' => \Carbon\Carbon::parse($date)->isoFormat('ddd, D MMM YYYY'),
                'slot_count' => $slots->count(),
                'capacity' => $capacity,
            ];
        })->values()->toArray();
    }

    /**
     * Get total capacity across all slots.
     */
    public function getTotalCapacity(): int
    {
        if (! $this->available_slots) {
            return 0;
        }

        return collect($this->available_slots)->sum(function ($slot) {
            if (is_array($slot)) {
                return $slot['capacity'] ?? $slot[1] ?? 0;
            }

            return 0;
        });
    }
}
