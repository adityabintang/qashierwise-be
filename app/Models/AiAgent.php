<?php

namespace App\Models;

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
            'order_enabled' => 'boolean',
            'qris_enabled' => 'boolean',
            'reservation_enabled' => 'boolean',
            'delivery_enabled' => 'boolean',
            'default_ongkir' => 'decimal:2',
            'is_active' => 'boolean',
            'settings' => 'array',
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
     * Check if reservation feature is enabled.
     */
    public function isReservationEnabled(): bool
    {
        return $this->reservation_enabled;
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
     * Check if user has an active SubMerchant.
     */
    public function hasActiveSubMerchant(): bool
    {
        return $this->getSubMerchant() !== null;
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
}
