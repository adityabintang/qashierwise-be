<?php

namespace App\DTOs;

use Carbon\Carbon;

/**
 * Data Transfer Object for user subscription status.
 * 
 * Contains the current state of a user's subscription including
 * status, plan information, and relevant dates.
 */
class SubscriptionStatus
{
    /**
     * Create a new SubscriptionStatus instance.
     *
     * @param string $status Current subscription status ('trial', 'trial_expired', 'active', 'cancelled', 'expired')
     * @param string $planName Current plan name ('free_trial', 'standard', 'pro')
     * @param int|null $trialDaysRemaining Days remaining in trial period (null if not on trial)
     * @param Carbon|null $periodEnd End date of current billing period
     * @param Carbon|null $cancelledAt Date when subscription was cancelled
     */
    public function __construct(
        public readonly string $status,
        public readonly string $planName,
        public readonly ?int $trialDaysRemaining,
        public readonly ?Carbon $periodEnd,
        public readonly ?Carbon $cancelledAt,
    ) {}

    /**
     * Convert the DTO to an array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'plan_name' => $this->planName,
            'trial_days_remaining' => $this->trialDaysRemaining,
            'period_end' => $this->periodEnd?->toIso8601String(),
            'cancelled_at' => $this->cancelledAt?->toIso8601String(),
        ];
    }

    /**
     * Create a SubscriptionStatus instance from an array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'],
            planName: $data['plan_name'],
            trialDaysRemaining: $data['trial_days_remaining'],
            periodEnd: isset($data['period_end']) ? Carbon::parse($data['period_end']) : null,
            cancelledAt: isset($data['cancelled_at']) ? Carbon::parse($data['cancelled_at']) : null,
        );
    }
}
