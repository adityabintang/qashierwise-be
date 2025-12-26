<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CredentialAccessLog extends Model
{
    use HasFactory;

    /**
     * Action constants
     */
    const ACTION_CREATE = 'create';
    const ACTION_READ = 'read';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    const ACTION_DECRYPT = 'decrypt';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'credential_id',
        'action',
        'ip_address',
        'user_agent',
        'success',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'success' => 'boolean',
    ];

    /**
     * Indicates if the model should be timestamped.
     * Only created_at is used for audit logs.
     *
     * @var bool
     */
    const UPDATED_AT = null;

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the credential that was accessed.
     */
    public function credential(): BelongsTo
    {
        return $this->belongsTo(PaymentProviderCredential::class, 'credential_id');
    }

    /**
     * Get all available actions.
     *
     * @return array<string>
     */
    public static function getAvailableActions(): array
    {
        return [
            self::ACTION_CREATE,
            self::ACTION_READ,
            self::ACTION_UPDATE,
            self::ACTION_DELETE,
            self::ACTION_DECRYPT,
        ];
    }

    /**
     * Check if an action is valid.
     *
     * @param string $action
     * @return bool
     */
    public static function isValidAction(string $action): bool
    {
        return in_array($action, self::getAvailableActions());
    }
}
