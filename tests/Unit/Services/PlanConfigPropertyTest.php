<?php

namespace Tests\Unit\Services;

use App\DTOs\PlanDetails;
use App\Services\PlanConfig;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for PlanConfig service
 * 
 * Feature: polar-subscription
 */
class PlanConfigPropertyTest extends TestCase
{
    use TestTrait;

    private PlanConfig $planConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planConfig = new PlanConfig();
    }

    /**
     * Feature: polar-subscription, Property 1: Plan Configuration Mapping Consistency
     * Validates: Requirements 2.1, 2.2
     * 
     * For any valid plan identifier (free_trial, standard, pro), the PlanConfig
     * SHALL return a non-null PlanDetails object containing a valid Polar.sh product ID.
     */
    #[Test]
    public function valid_plan_identifiers_return_non_null_plan_details_with_product_id(): void
    {
        $validPlanIds = [
            PlanConfig::PLAN_FREE_TRIAL,
            PlanConfig::PLAN_STANDARD,
            PlanConfig::PLAN_PRO,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanIds)
            )
            ->then(function (string $planId) {
                $planDetails = $this->planConfig->getPlan($planId);

                // Property: Valid plan identifiers must return non-null PlanDetails
                $this->assertNotNull(
                    $planDetails,
                    "getPlan('{$planId}') should return a non-null PlanDetails object"
                );

                // Property: PlanDetails must be an instance of PlanDetails
                $this->assertInstanceOf(
                    PlanDetails::class,
                    $planDetails,
                    "getPlan('{$planId}') should return a PlanDetails instance"
                );

                // Property: The returned plan ID must match the requested plan ID
                $this->assertEquals(
                    $planId,
                    $planDetails->id,
                    "Returned plan ID should match requested plan ID"
                );

                // Property: Polar product ID must be a string (can be empty for free_trial)
                $this->assertIsString(
                    $planDetails->polarProductId,
                    "polarProductId should be a string"
                );

                // Property: getPolarProductId should return consistent value
                $polarProductId = $this->planConfig->getPolarProductId($planId);
                $this->assertEquals(
                    $polarProductId ?? '',
                    $planDetails->polarProductId,
                    "getPolarProductId should return the same value as in PlanDetails"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 1: Plan Configuration Mapping Consistency (Invalid Plans)
     * Validates: Requirements 2.3
     * 
     * For any invalid plan identifier, the PlanConfig SHALL return null without throwing an exception.
     */
    #[Test]
    public function invalid_plan_identifiers_return_null(): void
    {
        $invalidPlanIds = [
            'invalid',
            'unknown',
            'premium',
            'enterprise',
            '',
            'FREE_TRIAL',
            'STANDARD',
            'PRO',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($invalidPlanIds)
            )
            ->then(function (string $planId) {
                $planDetails = $this->planConfig->getPlan($planId);

                // Property: Invalid plan identifiers must return null
                $this->assertNull(
                    $planDetails,
                    "getPlan('{$planId}') should return null for invalid plan identifier"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 2: Plan Details Completeness
     * Validates: Requirements 2.2
     * 
     * For any valid plan identifier, the returned PlanDetails SHALL contain all required fields:
     * id, name, priceMonthly, polarProductId, features array, and tier.
     */
    #[Test]
    public function plan_details_contain_all_required_fields(): void
    {
        $validPlanIds = [
            PlanConfig::PLAN_FREE_TRIAL,
            PlanConfig::PLAN_STANDARD,
            PlanConfig::PLAN_PRO,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanIds)
            )
            ->then(function (string $planId) {
                $planDetails = $this->planConfig->getPlan($planId);

                // Precondition: Plan must exist
                $this->assertNotNull($planDetails, "Plan '{$planId}' must exist");

                // Property: id must be a non-empty string
                $this->assertIsString($planDetails->id, 'id must be a string');
                $this->assertNotEmpty($planDetails->id, 'id must not be empty');

                // Property: name must be a non-empty string
                $this->assertIsString($planDetails->name, 'name must be a string');
                $this->assertNotEmpty($planDetails->name, 'name must not be empty');

                // Property: priceMonthly must be a non-negative integer
                $this->assertIsInt($planDetails->priceMonthly, 'priceMonthly must be an integer');
                $this->assertGreaterThanOrEqual(0, $planDetails->priceMonthly, 'priceMonthly must be non-negative');

                // Property: polarProductId must be a string
                $this->assertIsString($planDetails->polarProductId, 'polarProductId must be a string');

                // Property: features must be an array
                $this->assertIsArray($planDetails->features, 'features must be an array');

                // Property: tier must be a valid tier string
                $validTiers = [PlanConfig::TIER_BASIC, PlanConfig::TIER_STANDARD, PlanConfig::TIER_PRO];
                $this->assertContains(
                    $planDetails->tier,
                    $validTiers,
                    "tier must be one of: " . implode(', ', $validTiers)
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 2: Plan Details Completeness (Features Non-Empty)
     * Validates: Requirements 2.2
     * 
     * For any valid plan identifier, the features array SHALL contain at least one feature.
     */
    #[Test]
    public function plan_details_features_are_non_empty(): void
    {
        $validPlanIds = [
            PlanConfig::PLAN_FREE_TRIAL,
            PlanConfig::PLAN_STANDARD,
            PlanConfig::PLAN_PRO,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanIds)
            )
            ->then(function (string $planId) {
                $planDetails = $this->planConfig->getPlan($planId);

                // Precondition: Plan must exist
                $this->assertNotNull($planDetails, "Plan '{$planId}' must exist");

                // Property: features array must not be empty
                $this->assertNotEmpty(
                    $planDetails->features,
                    "Plan '{$planId}' must have at least one feature"
                );

                // Property: each feature must be a non-empty string
                foreach ($planDetails->features as $index => $feature) {
                    $this->assertIsString(
                        $feature,
                        "Feature at index {$index} must be a string"
                    );
                    $this->assertNotEmpty(
                        $feature,
                        "Feature at index {$index} must not be empty"
                    );
                }
            });
    }

    /**
     * Feature: polar-subscription, Property 1: Plan Configuration Mapping Consistency (getAllPlans)
     * Validates: Requirements 2.1, 2.2
     * 
     * getAllPlans SHALL return all valid plans with consistent data.
     */
    #[Test]
    public function get_all_plans_returns_all_valid_plans(): void
    {
        $allPlans = $this->planConfig->getAllPlans();

        // Property: getAllPlans must return exactly 3 plans
        $this->assertCount(3, $allPlans, 'getAllPlans should return exactly 3 plans');

        // Property: getAllPlans must include all valid plan IDs
        $expectedPlanIds = [
            PlanConfig::PLAN_FREE_TRIAL,
            PlanConfig::PLAN_STANDARD,
            PlanConfig::PLAN_PRO,
        ];

        foreach ($expectedPlanIds as $planId) {
            $this->assertArrayHasKey(
                $planId,
                $allPlans,
                "getAllPlans should include plan '{$planId}'"
            );

            // Property: Each plan in getAllPlans must match getPlan result
            $planFromGetAll = $allPlans[$planId];
            $planFromGetPlan = $this->planConfig->getPlan($planId);

            $this->assertEquals(
                $planFromGetPlan->toArray(),
                $planFromGetAll->toArray(),
                "Plan '{$planId}' from getAllPlans should match getPlan result"
            );
        }
    }

    /**
     * Feature: polar-subscription, Property 1: Plan Configuration Mapping Consistency (getFeaturesByTier)
     * Validates: Requirements 2.1, 2.2
     * 
     * getFeaturesByTier SHALL return features consistent with the corresponding plan.
     */
    #[Test]
    public function get_features_by_tier_returns_consistent_features(): void
    {
        $validTiers = [
            PlanConfig::TIER_BASIC,
            PlanConfig::TIER_STANDARD,
            PlanConfig::TIER_PRO,
        ];

        $tierToPlanMap = [
            PlanConfig::TIER_BASIC => PlanConfig::PLAN_FREE_TRIAL,
            PlanConfig::TIER_STANDARD => PlanConfig::PLAN_STANDARD,
            PlanConfig::TIER_PRO => PlanConfig::PLAN_PRO,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validTiers)
            )
            ->then(function (string $tier) use ($tierToPlanMap) {
                $features = $this->planConfig->getFeaturesByTier($tier);
                $planId = $tierToPlanMap[$tier];
                $planDetails = $this->planConfig->getPlan($planId);

                // Property: Features from getFeaturesByTier must match plan features
                $this->assertEquals(
                    $planDetails->features,
                    $features,
                    "Features for tier '{$tier}' should match plan '{$planId}' features"
                );
            });
    }

    /**
     * Feature: polar-subscription, Property 1: Plan Configuration Mapping Consistency (Invalid Tier)
     * Validates: Requirements 2.3
     * 
     * getFeaturesByTier SHALL return empty array for invalid tiers.
     */
    #[Test]
    public function get_features_by_invalid_tier_returns_empty_array(): void
    {
        $invalidTiers = [
            'invalid',
            'unknown',
            'premium',
            '',
            'BASIC',
            'STANDARD',
            'PRO',
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($invalidTiers)
            )
            ->then(function (string $tier) {
                $features = $this->planConfig->getFeaturesByTier($tier);

                // Property: Invalid tiers must return empty array
                $this->assertIsArray($features, 'getFeaturesByTier should return an array');
                $this->assertEmpty(
                    $features,
                    "getFeaturesByTier('{$tier}') should return empty array for invalid tier"
                );
            });
    }
}
