<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppDataSharingConsent extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_data_sharing_consents';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const SHARING_MODE_ALL = 'all';

    public const SHARING_MODE_SELECTED = 'selected';

    protected $fillable = [
        'user_id',
        'whatsapp_account_id',
        'business_profile_shared',
        'contacts_shared',
        'conversation_history_shared',
        'conversation_months',
        'sharing_mode',
        'selected_contact_ids',
        'status',
        'consent_given_at',
        'consent_updated_at',
        'synced_at',
        'synced_contact_count',
        'synced_message_count',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'business_profile_shared' => 'boolean',
        'contacts_shared' => 'boolean',
        'conversation_history_shared' => 'boolean',
        'selected_contact_ids' => 'array',
        'consent_given_at' => 'datetime',
        'consent_updated_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class, 'whatsapp_account_id');
    }
}
