<?php

namespace App\Observers;

use App\Models\AiAgent;
use App\Models\ReservationConfig;
use Illuminate\Support\Facades\Cache;

/**
 * Twin of SubMerchantObserver. Invalidates the agent's reservation-config
 * cache so the "Reservasi" button can be wired up immediately after the
 * merchant creates or activates a ReservationConfig.
 */
class ReservationConfigObserver
{
    public function saved(ReservationConfig $config): void
    {
        $this->forgetForUser($config->user_id);
    }

    public function deleted(ReservationConfig $config): void
    {
        $this->forgetForUser($config->user_id);
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
                Cache::forget("ai_agent:{$agentId}:has_reservation_config");
            });
    }
}
