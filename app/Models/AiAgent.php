<?php

namespace App\Models;

use App\Casts\AiAgentSettings as AiAgentSettingsCast;
use App\Enums\UserIntent;
use App\Services\AiAgentPromptBuilder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;

class AiAgent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'whatsapp_account_id',
        'default_store_id',
        'catalog_id',
        'catalog_enabled',
        'bot_name',
        'system_prompt',
        'business_info',
        'order_enabled',
        'qris_enabled',
        'reservation_enabled',
        'delivery_enabled',
        'default_ongkir',
        'is_active',
        'settings',
        'use_optimized_prompt',
        'enable_prompt_caching',
        'use_toon_format',
        'product_sample_limit',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_info' => 'array',
            'catalog_enabled' => 'boolean',
            'order_enabled' => 'boolean',
            'qris_enabled' => 'boolean',
            'reservation_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'default_ongkir' => 'decimal:2',
            'is_active' => 'boolean',
            'settings' => AiAgentSettingsCast::class,
            'use_optimized_prompt' => 'boolean',
            'enable_prompt_caching' => 'boolean',
            'use_toon_format' => 'boolean',
            'product_sample_limit' => 'integer',
        ];
    }

    /**
     * Get the WhatsApp account that owns the AI agent.
     */
    public function whatsappAccount(): BelongsTo
    {
        return $this->belongsTo(WhatsAppAccount::class);
    }

    /**
     * Get the default store for orders.
     */
    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    /**
     * Get the conversations for this AI agent.
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(AiAgentConversation::class);
    }

    /**
     * Check if order feature is enabled.
     */
    public function isOrderEnabled(): bool
    {
        return $this->order_enabled && $this->default_store_id !== null;
    }

    /**
     * Check if QRIS feature is properly enabled.
     * Requires qris_enabled flag and active SubMerchant.
     */
    public function isQrisEnabled(): bool
    {
        return $this->qris_enabled
            && $this->hasActiveSubMerchant();
    }

    /**
     * Check if reservation feature is enabled AND configured.
     * Mirrors isQrisEnabled(): the toggle alone isn't enough — the merchant
     * must have an active ReservationConfig (analogous to QRIS needing a
     * SubMerchant). Without it the "Reservasi" button is suppressed in chat.
     */
    public function isReservationEnabled(): bool
    {
        return $this->reservation_enabled
            && $this->hasReservationConfig();
    }

    /**
     * Whether the merchant has an active reservation configuration. Cached
     * for 5 minutes — invalidated by ReservationConfigObserver on save/delete.
     */
    public function hasReservationConfig(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "ai_agent:{$this->id}:has_reservation_config",
            300,
            function (): bool {
                $user = $this->getUser();
                if (! $user) {
                    return false;
                }

                return ReservationConfig::where('user_id', $user->id)
                    ->where('is_active', true)
                    ->exists();
            }
        );
    }

    public function isDeliveryEnabled(): bool
    {
        return $this->delivery_enabled;
    }

    public function hasCatalog(): bool
    {
        return ! empty($this->catalog_id);
    }

    /**
     * Whether the platform admin has locked the catalog feature globally
     * (env META_CATALOG=false → config('catalog.meta_enabled')=false). Used
     * by the dashboard to render the toggle in read-only locked state.
     */
    public function isCatalogPlatformLocked(): bool
    {
        return ! (bool) config('catalog.meta_enabled', true);
    }

    /**
     * Whether the Meta Catalog flow should drive product selection.
     * Three things must align:
     *   1. Platform flag enabled (META_CATALOG=true)
     *   2. Merchant toggle on   (catalog_enabled=true)
     *   3. Catalog picked       (catalog_id filled)
     */
    public function isCatalogActive(): bool
    {
        return ! $this->isCatalogPlatformLocked()
            && (bool) $this->catalog_enabled
            && $this->hasCatalog();
    }

    /**
     * Why isCatalogActive() returned false, in priority order. Returns null
     * when catalog mode is fully active. Drives the dashboard banner copy
     * so merchants always know which knob to flip next.
     */
    public function getCatalogUnavailableReason(): ?string
    {
        if ($this->isCatalogPlatformLocked()) {
            return 'platform_locked';
        }
        if (! $this->catalog_enabled) {
            return 'toggle_off';
        }
        if (! $this->hasCatalog()) {
            return 'no_catalog_id';
        }

        return null;
    }

    /**
     * Get reservation form URL for this AI Agent.
     */
    public function getReservationFormUrl(): ?string
    {
        $user = $this->getUser();
        if (! $user) {
            return null;
        }

        $merchantIdentifier = $user->slug ?? (string) $user->id;

        return route('reservation.form', ['merchantName' => $merchantIdentifier]);
    }

    /**
     * Get the user associated with this AI Agent.
     */
    public function getUser(): ?User
    {
        $account = $this->whatsappAccount()
            ->withoutGlobalScope('userAccounts')
            ->first();

        if (! $account) {
            Log::warning('getUser: whatsappAccount not found (without global scope)', [
                'ai_agent_id' => $this->id,
                'whatsapp_account_id' => $this->whatsapp_account_id,
            ]);

            return null;
        }

        return $account->user;
    }

    /**
     * Get the user's active SubMerchant.
     */
    public function getSubMerchant(): ?SubMerchant
    {
        $user = $this->getUser();
        if (! $user) {
            Log::warning('getSubMerchant: no user found for AI agent', [
                'ai_agent_id' => $this->id,
                'whatsapp_account_id' => $this->whatsapp_account_id,
            ]);

            return null;
        }

        $subMerchant = SubMerchant::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if (! $subMerchant) {
            Log::warning('getSubMerchant: no active SubMerchant found', [
                'ai_agent_id' => $this->id,
                'user_id' => $user->id,
                'total_submerchants' => SubMerchant::where('user_id', $user->id)->count(),
                'inactive_submerchants' => SubMerchant::where('user_id', $user->id)->where('is_active', false)->count(),
            ]);
        }

        return $subMerchant;
    }

    /**
     * Check if user has an active SubMerchant. Cached for 5 minutes —
     * invalidated by SubMerchantObserver on save/delete.
     */
    public function hasActiveSubMerchant(): bool
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "ai_agent:{$this->id}:has_active_submerchant",
            300,
            fn (): bool => $this->getSubMerchant() !== null,
        );
    }

    /**
     * Check if user has active payment provider credentials.
     */
    public function hasActivePaymentProvider(): bool
    {
        $user = $this->getUser();
        if (! $user) {
            return false;
        }

        return PaymentProviderCredential::where('user_id', $user->id)
            ->where('is_active', true)
            ->where('connection_status', 'valid')
            ->exists();
    }

    /**
     * Validate QRIS configuration and return error messages if invalid.
     */
    public function validateQrisConfiguration(): array
    {
        $errors = [];

        if (! $this->hasActiveSubMerchant()) {
            $errors[] = 'Sub-merchant belum dikonfigurasi atau tidak aktif. Silakan daftarkan sub-merchant terlebih dahulu.';
        }

        return $errors;
    }

    /**
     * Build the system prompt with business info and product context.
     *
     * @param  string|null  $userMessage  For intent detection (optional)
     */
    public function buildSystemPrompt(int $userId, ?string $userMessage = null): string
    {
        // Always use optimized prompt builder (TOON format supported)
        $intent = $userMessage ? UserIntent::detect($userMessage) : null;
        $builder = new AiAgentPromptBuilder($this, $userId, $intent);

        return $builder->build();
    }

    /**
     * Aggregate feature status with reasons. Service code should branch on the
     * `reason` field instead of bare booleans so logging stays informative when
     * a feature silently degrades (e.g. submerchant deleted while QRIS toggle
     * was ON). Returns one entry per top-level feature.
     */
    public function getFeatureStatus(): array
    {
        return [
            'order' => $this->buildOrderStatus(),
            'qris' => $this->buildQrisStatus(),
            'reservation' => $this->buildReservationStatus(),
            'delivery' => $this->buildDeliveryStatus(),
            'catalog' => $this->buildCatalogStatus(),
        ];
    }

    protected function buildOrderStatus(): array
    {
        if (! $this->order_enabled) {
            return ['enabled' => false, 'reason' => 'toggle_off'];
        }
        if ($this->default_store_id === null) {
            return ['enabled' => false, 'reason' => 'missing_store'];
        }

        return ['enabled' => true, 'reason' => 'ok'];
    }

    protected function buildQrisStatus(): array
    {
        if (! $this->qris_enabled) {
            return ['enabled' => false, 'reason' => 'toggle_off'];
        }
        if (! $this->hasActiveSubMerchant()) {
            return ['enabled' => false, 'reason' => 'missing_submerchant'];
        }

        return ['enabled' => true, 'reason' => 'ok'];
    }

    protected function buildReservationStatus(): array
    {
        if (! $this->reservation_enabled) {
            return ['enabled' => false, 'reason' => 'toggle_off'];
        }
        if (! $this->hasReservationConfig()) {
            return ['enabled' => false, 'reason' => 'missing_reservation_config'];
        }

        return ['enabled' => true, 'reason' => 'ok'];
    }

    protected function buildDeliveryStatus(): array
    {
        return [
            'enabled' => (bool) $this->delivery_enabled,
            'reason' => $this->delivery_enabled ? 'ok' : 'toggle_off',
        ];
    }

    protected function buildCatalogStatus(): array
    {
        $reason = $this->getCatalogUnavailableReason();

        return [
            'enabled' => $reason === null,
            'reason' => $reason ?? 'ok',
        ];
    }
}
