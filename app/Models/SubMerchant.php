<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;

class SubMerchant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'account_holder_name',
        'is_active',
        'verified_at',
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
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns this sub-merchant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the balance for this sub-merchant.
     */
    public function balance(): HasOne
    {
        return $this->hasOne(MerchantBalance::class);
    }

    /**
     * Get the QRIS transactions for this sub-merchant.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(QrisTransaction::class);
    }

    /**
     * Get the withdrawal requests for this sub-merchant.
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /**
     * Encrypt the account number when setting.
     */
    public function setAccountNumberAttribute(string $value): void
    {
        $this->attributes['account_number'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt the account number when getting.
     */
    public function getAccountNumberAttribute(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        
        try {
            return Crypt::decryptString($value);
        } catch (\Exception $e) {
            // Return the raw value if decryption fails (for backward compatibility)
            return $value;
        }
    }

    /**
     * Validate bank account data.
     *
     * @param array<string, mixed> $bankDetails
     * @return array<string, string> Validation errors (empty if valid)
     */
    public static function validateBankAccount(array $bankDetails): array
    {
        $errors = [];

        // Validate bank name
        if (empty($bankDetails['bank_name'])) {
            $errors['bank_name'] = 'Bank name is required';
        } elseif (strlen($bankDetails['bank_name']) > 100) {
            $errors['bank_name'] = 'Bank name must not exceed 100 characters';
        }

        // Validate account number
        if (empty($bankDetails['account_number'])) {
            $errors['account_number'] = 'Account number is required';
        } elseif (!preg_match('/^[0-9]+$/', $bankDetails['account_number'])) {
            $errors['account_number'] = 'Account number must contain only digits';
        } elseif (strlen($bankDetails['account_number']) < 5 || strlen($bankDetails['account_number']) > 50) {
            $errors['account_number'] = 'Account number must be between 5 and 50 digits';
        }

        // Validate account holder name
        if (empty($bankDetails['account_holder_name'])) {
            $errors['account_holder_name'] = 'Account holder name is required';
        } elseif (strlen($bankDetails['account_holder_name']) > 100) {
            $errors['account_holder_name'] = 'Account holder name must not exceed 100 characters';
        }

        return $errors;
    }

    /**
     * Check if bank account data is valid.
     *
     * @param array<string, mixed> $bankDetails
     */
    public static function isValidBankAccount(array $bankDetails): bool
    {
        return empty(self::validateBankAccount($bankDetails));
    }

    /**
     * Get the raw (encrypted) account number for storage purposes.
     */
    public function getRawAccountNumber(): ?string
    {
        return $this->attributes['account_number'] ?? null;
    }

    /**
     * Check if the sub-merchant is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * Check if the sub-merchant can accept payments.
     */
    public function canAcceptPayments(): bool
    {
        return $this->is_active && $this->isVerified();
    }
}
