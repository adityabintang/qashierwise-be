<?php

namespace App\Observers;

use App\Models\AiAgent;
use App\Models\SubMerchant;
use Illuminate\Support\Facades\Cache;

/**
 * Invalidates AiAgent's cached `hasActiveSubMerchant` whenever a sub-merchant
 * is added, deleted, or toggled. Without this the agent could keep replying
 * "QRIS unavailable" for up to 5 minutes after the merchant fixes their
 * sub-merchant config.
 */
class SubMerchantObserver
{
    public function saved(SubMerchant $subMerchant): void
    {
        $this->forgetForUser($subMerchant->user_id);
    }

    public function deleted(SubMerchant $subMerchant): void
    {
        $this->forgetForUser($subMerchant->user_id);
    }

    protected function forgetForUser(?int $userId): void
    {
        if (! $userId) {
            return;
        }

        AiAgent::whereHas('whatsappAccount', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })
            ->pluck('id')
            ->each(function ($agentId): void {
                Cache::forget("ai_agent:{$agentId}:has_active_submerchant");
            });
    }
}
