<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentProviderCredential extends Model
{
    use HasFactory;

    /**
     * Provider constants
     */
    const PROVIDER_DOKU = 'doku';
    const PROVIDER_XENDIT = 'xendit';
    const PROVIDER_MIDTRANS = 'midtrans';
    const PROVIDER_DUITKU = 'duitku';

    /**
     * Connection status constants
     */
    const STATUS_PENDING = 'pending';
    const STATUS_VALID = 'valid';
    const STATUS_INVALID = 'invalid';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'credentials_encrypted',
        'is_active',
        'connection_status',
        'validation_error',
        'last_validated_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'last_validated_at' => 'datetime',
    ];

    /**
     * Get the user that owns the credential.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the access logs for this credential.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(CredentialAccessLog::class, 'credential_id');
    }

    /**
     * Check if the credential is valid.
     *
     * @return bool
     */
    public function isValid(): bool
    {
        return $this->connection_status === self::STATUS_VALID;
    }

    /**
     * Check if the credential needs revalidation.
     * Credentials need revalidation if they haven't been validated in the last 24 hours.
     *
     * @return bool
     */
    public function needsRevalidation(): bool
    {
        if ($this->connection_status === self::STATUS_PENDING) {
            return true;
        }

        if ($this->last_validated_at === null) {
            return true;
        }

        // Revalidate if last validation was more than 24 hours ago
        return $this->last_validated_at->diffInHours(now()) > 24;
    }

    /**
     * Get all available providers.
     *
     * @return array<string>
     */
    public static function getAvailableProviders(): array
    {
        return [
            self::PROVIDER_DOKU,
            self::PROVIDER_XENDIT,
            self::PROVIDER_MIDTRANS,
            self::PROVIDER_DUITKU,
        ];
    }

    /**
     * Check if a provider is valid.
     *
     * @param string $provider
     * @return bool
     */
    public static function isValidProvider(string $provider): bool
    {
        return in_array($provider, self::getAvailableProviders());
    }
}
