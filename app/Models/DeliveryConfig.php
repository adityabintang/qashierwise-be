<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-merchant delivery configuration (one row per user).
 */
class DeliveryConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'is_active',
        'default_ongkir',
        'proof_required',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'proof_required' => 'boolean',
            'default_ongkir' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fetch (or lazily create) the config row for a given merchant user.
     */
    public static function forUser(int $userId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId],
            ['is_active' => true, 'default_ongkir' => 0, 'proof_required' => true]
        );
    }
}
