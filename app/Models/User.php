<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the subscription associated with the user.
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get the sub-merchant profile associated with the user.
     */
    public function subMerchant(): HasOne
    {
        return $this->hasOne(SubMerchant::class);
    }

    /**
     * Check if the user is a sub-merchant.
     */
    public function isSubMerchant(): bool
    {
        return $this->subMerchant !== null;
    }

    /**
     * Get the encryption key associated with the user.
     */
    public function encryptionKey(): HasOne
    {
        return $this->hasOne(UserEncryptionKey::class);
    }

    /**
     * Get the payment provider credentials for the user.
     */
    public function paymentProviderCredentials(): HasMany
    {
        return $this->hasMany(PaymentProviderCredential::class);
    }

    /**
     * Get the active payment provider credential for the user.
     */
    public function activeProviderCredential(): HasOne
    {
        return $this->hasOne(PaymentProviderCredential::class)->where('is_active', true);
    }

    /**
     * Get the credential access logs for the user.
     */
    public function credentialAccessLogs(): HasMany
    {
        return $this->hasMany(CredentialAccessLog::class);
    }
}
