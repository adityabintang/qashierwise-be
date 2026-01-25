<?php

namespace App\Services;

use App\DTOs\PlanDetails;

/**
 * Service for managing subscription plan configurations.
 *
 * Provides plan details including features and pricing from Midtrans subscription config.
 */
class PlanConfig
{
    /**
     * Valid plan identifiers.
     */
    public const PLAN_FREE_TRIAL = 'free_trial';
    public const PLAN_PRO = 'pro';

    /**
     * Valid tier identifiers.
     */
    public const TIER_BASIC = 'basic';
    public const TIER_PRO = 'pro';

    /**
     * Get plan details by plan identifier.
     *
     * @param string $planId The plan identifier (free_trial, pro)
     * @return PlanDetails|null Returns null if plan doesn't exist
     */
    public function getPlan(string $planId): ?PlanDetails
    {
        $planConfig = config("subscription.plans.{$planId}");

        if ($planConfig === null) {
            return null;
        }

        return new PlanDetails(
            id: $planConfig['id'],
            name: $planConfig['name'],
            priceMonthly: $planConfig['price_monthly'],
            polarProductId: '',
            features: $planConfig['features'],
            tier: $planConfig['tier'],
        );
    }

    /**
     * Get all available plans.
     *
     * @return array<string, PlanDetails> Array of PlanDetails indexed by plan ID
     */
    public function getAllPlans(): array
    {
        $plans = [];
        $planIds = [self::PLAN_FREE_TRIAL, self::PLAN_PRO];

        foreach ($planIds as $planId) {
            $plan = $this->getPlan($planId);
            if ($plan !== null) {
                $plans[$planId] = $plan;
            }
        }

        return $plans;
    }

    /**
     * Get features available for a specific tier.
     *
     * @param string $tier The tier identifier (basic, pro)
     * @return array<string> List of features for tier
     */
    public function getFeaturesByTier(string $tier): array
    {
        $planId = $this->getPlanIdByTier($tier);

        if ($planId === null) {
            return [];
        }

        $plan = $this->getPlan($planId);

        return $plan?->features ?? [];
    }

    /**
     * Get plan ID that corresponds to a tier.
     *
     * @param string $tier The tier identifier
     * @return string|null The plan ID or null if tier is invalid
     */
    private function getPlanIdByTier(string $tier): ?string
    {
        return match ($tier) {
            self::TIER_BASIC => self::PLAN_FREE_TRIAL,
            self::TIER_PRO => self::PLAN_PRO,
            default => null,
        };
    }

    /**
     * Check if a plan identifier is valid.
     *
     * @param string $planId The plan identifier to check
     * @return bool True if plan exists
     */
    public function isValidPlan(string $planId): bool
    {
        return in_array($planId, [
            self::PLAN_FREE_TRIAL,
            self::PLAN_PRO,
        ], true);
    }

    /**
     * Check if a tier identifier is valid.
     *
     * @param string $tier The tier identifier to check
     * @return bool True if tier is valid
     */
    public function isValidTier(string $tier): bool
    {
        return in_array($tier, [
            self::TIER_BASIC,
            self::TIER_PRO,
        ], true);
    }
}
