<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'bot_name',
        'system_prompt',
        'business_info',
        'order_enabled',
        'qris_enabled',
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
     * Requires qris_enabled flag, active SubMerchant, and active payment provider.
     */
    public function isQrisEnabled(): bool
    {
        return $this->qris_enabled
            && $this->hasActiveSubMerchant()
            && $this->hasActivePaymentProvider();
    }

    /**
     * Get the user associated with this AI Agent.
     */
    public function getUser(): ?User
    {
        return $this->whatsappAccount?->user;
    }

    /**
     * Get the user's active SubMerchant.
     */
    public function getSubMerchant(): ?SubMerchant
    {
        $user = $this->getUser();
        if (! $user) {
            return null;
        }

        return SubMerchant::where('user_id', $user->id)
            ->where('is_active', true)
            ->first();
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

        if (! $this->hasActivePaymentProvider()) {
            $errors[] = 'Payment provider belum dikonfigurasi atau tidak valid. Silakan konfigurasi provider di menu Provider Settings.';
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
        $intent = $userMessage ? \App\Enums\UserIntent::detect($userMessage) : null;
        $builder = new \App\Services\AiAgentPromptBuilder($this, $userId, $intent);

        return $builder->build();
    }
}
