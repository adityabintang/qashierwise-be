<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

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
     * Get the decrypted API key.
     * This accessor extracts the api_key from the encrypted credentials JSON.
     *
     * @return string|null
     */
    public function getApiKeyAttribute(): ?string
    {
        if (empty($this->credentials_encrypted)) {
            return null;
        }

        try {
            $credentials = json_decode(Crypt::decryptString($this->credentials_encrypted), true);
            return $credentials['api_key'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the decrypted secret key.
     * This accessor extracts the secret_key from the encrypted credentials JSON.
     *
     * @return string|null
     */
    public function getSecretKeyAttribute(): ?string
    {
        if (empty($this->credentials_encrypted)) {
            return null;
        }

        try {
            $credentials = json_decode(Crypt::decryptString($this->credentials_encrypted), true);
            return $credentials['secret_key'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Set the API key by encrypting it into the credentials JSON.
     * This mutator updates the api_key in the encrypted credentials.
     *
     * @param string $value
     * @return void
     */
    public function setApiKeyAttribute(string $value): void
    {
        $credentials = $this->getDecryptedCredentials();
        $credentials['api_key'] = $value;
        $this->attributes['credentials_encrypted'] = Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Set the secret key by encrypting it into the credentials JSON.
     * This mutator updates the secret_key in the encrypted credentials.
     *
     * @param string $value
     * @return void
     */
    public function setSecretKeyAttribute(string $value): void
    {
        $credentials = $this->getDecryptedCredentials();
        $credentials['secret_key'] = $value;
        $this->attributes['credentials_encrypted'] = Crypt::encryptString(json_encode($credentials));
    }

    /**
     * Get all decrypted credentials as an array.
     *
     * @return array
     */
    private function getDecryptedCredentials(): array
    {
        if (empty($this->attributes['credentials_encrypted'])) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->attributes['credentials_encrypted']), true) ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Scope a query to only include active credentials.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
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
