<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The super admin email - system-wide administrator.
     * This user has access to all merchant data and system-wide settings.
     */
    public const SUPER_ADMIN_EMAIL = 'admin@qashierwise.com';

    /**
     * The guard name for Spatie Permission.
     *
     * @var string
     */
    protected $guard_name = 'sanctum';

    /**
     * Get the default guard name for the model.
     * Required for Spatie Permission to work with Sanctum.
     */
    public function guardName(): string
    {
        return 'sanctum';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'language_preference',
        'is_master_admin',
        'slug',
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
            'is_master_admin' => 'boolean',
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
     * Get the WhatsApp accounts associated with the user.
     */
    public function whatsappAccounts(): HasMany
    {
        return $this->hasMany(WhatsAppAccount::class);
    }

    /**
     * Get the active WhatsApp account for the user.
     */
    public function activeWhatsAppAccount(): HasOne
    {
        return $this->hasOne(WhatsAppAccount::class)->where('is_active', true);
    }

    /**
     * Get the WhatsApp contacts associated with the user.
     */
    public function whatsappContacts(): HasMany
    {
        return $this->hasMany(WhatsAppContact::class);
    }

    /**
     * Get the WhatsApp messages associated with the user.
     */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    /**
     * Get the credential access logs for the user.
     */
    public function credentialAccessLogs(): HasMany
    {
        return $this->hasMany(CredentialAccessLog::class);
    }

    /**
     * Get the POS users associated with this user.
     */
    public function posUsers(): HasMany
    {
        return $this->hasMany(PosUser::class);
    }

    /**
     * Get the blog posts authored by this user.
     */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }

    /**
     * Determine if the user can access the Filament admin panel.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isSuperAdmin() || $this->isAuthor();
    }

    /**
     * Check if the user is a master admin.
     */
    public function isMasterAdmin(): bool
    {
        return $this->is_master_admin === true;
    }

    /**
     * Check if the user is an author (blog content manager).
     */
    public function isAuthor(): bool
    {
        return $this->hasRole('author');
    }

    /**
     * Check if the user is a super admin (system-wide administrator).
     * Super admin has access to all merchant data and system-wide settings.
     */
    public function isSuperAdmin(): bool
    {
        return $this->email === self::SUPER_ADMIN_EMAIL;
    }

    /**
     * Check if the user is a regular merchant admin (not super admin).
     */
    public function isMerchantAdmin(): bool
    {
        return $this->isMasterAdmin() && ! $this->isSuperAdmin();
    }

    /**
     * Get the master admin for this user.
     * If user is master admin, returns self.
     * If user is sub-account (POS user), returns their master admin via store ownership.
     */
    public function getMasterAdmin(): ?User
    {
        if ($this->isMasterAdmin()) {
            return $this;
        }

        // Sub-account: get master admin via POS user's store
        $posUser = $this->posUsers()->with('store')->first();

        if ($posUser && $posUser->store) {
            return User::find($posUser->store->user_id);
        }

        return null;
    }

    /**
     * Get effective subscription.
     * Sub-accounts inherit their master admin's subscription.
     */
    public function getEffectiveSubscription()
    {
        if ($this->isMasterAdmin()) {
            return $this->subscription;
        }

        // Sub-account: get master admin's subscription
        $masterAdmin = $this->getMasterAdmin();

        return $masterAdmin ? $masterAdmin->subscription : null;
    }

    /**
     * Get effective user ID for data ownership.
     * Sub-accounts use their master admin's ID for data queries.
     */
    public function getEffectiveUserId(): int
    {
        if ($this->isMasterAdmin()) {
            return $this->id;
        }

        $masterAdmin = $this->getMasterAdmin();

        return $masterAdmin ? $masterAdmin->id : $this->id;
    }

    /**
     * Get user permissions via direct database query.
     * This bypasses the getAllPermissions() issue when there's a JSON column named 'permissions'
     * in the roles table that conflicts with Eloquent relationship loading.
     *
     * @return array<int, string> Array of permission names
     */
    public function getPermissionsViaDirectQuery(): array
    {
        // Get role IDs from model_has_roles
        $roleIds = \DB::table('model_has_roles')
            ->where('model_type', 'App\\Models\\User')
            ->where('model_id', $this->id)
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return [];
        }

        // Get permission names via direct database join
        return \DB::table('role_has_permissions')
            ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
            ->whereIn('role_has_permissions.role_id', $roleIds)
            ->where('permissions.guard_name', $this->guardName())
            ->pluck('permissions.name')
            ->unique()
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get role names via direct database query.
     * This provides consistent results without relying on Eloquent relationship loading.
     *
     * @return array<int, string> Array of role names
     */
    public function getRoleNamesViaDirectQuery(): array
    {
        return \DB::table('model_has_roles')
            ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
            ->where('model_has_roles.model_type', 'App\\Models\\User')
            ->where('model_has_roles.model_id', $this->id)
            ->where('roles.guard_name', $this->guardName())
            ->pluck('roles.name')
            ->sort()
            ->values()
            ->toArray();
    }
}
