<?php

namespace Tests\Unit\DTOs;

use App\DTOs\PlanDetails;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for PlanDetails DTO
 * 
 * Feature: polar-subscription
 */
class PlanDetailsPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: polar-subscription, Property 14: Subscription Serialization Round-Trip (adapted for PlanDetails)
     * Validates: Requirements 7.4
     * 
     * For any valid PlanDetails object, serializing to array and deserializing back
     * SHALL produce an equivalent PlanDetails object.
     */
    #[Test]
    public function plan_details_serialization_round_trip_preserves_data(): void
    {
        $tiers = ['basic', 'standard', 'pro'];
        $planIds = ['free_trial', 'standard', 'pro'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($planIds),
                Generators::suchThat(
                    fn($s) => strlen($s) > 0 && strlen($s) <= 100,
                    Generators::string()
                ),
                Generators::choose(0, 100000000), // price in cents
                Generators::suchThat(
                    fn($s) => strlen($s) > 0 && strlen($s) <= 50,
                    Generators::string()
                ),
                Generators::elements($tiers)
            )
            ->then(function (string $id, string $name, int $priceMonthly, string $polarProductId, string $tier) {
                // Generate a random features array
                $features = ['Feature 1', 'Feature 2', 'Feature 3'];

                // Create a PlanDetails instance with generated data
                $planDetails = new PlanDetails(
                    id: $id,
                    name: $name,
                    priceMonthly: $priceMonthly,
                    polarProductId: $polarProductId,
                    features: $features,
                    tier: $tier,
                );

                // Serialize to array
                $array = $planDetails->toArray();

                // Deserialize from array
                $restoredPlanDetails = PlanDetails::fromArray($array);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($planDetails->id, $restoredPlanDetails->id, 'id should be preserved');
                $this->assertEquals($planDetails->name, $restoredPlanDetails->name, 'name should be preserved');
                $this->assertEquals($planDetails->priceMonthly, $restoredPlanDetails->priceMonthly, 'priceMonthly should be preserved');
                $this->assertEquals($planDetails->polarProductId, $restoredPlanDetails->polarProductId, 'polarProductId should be preserved');
                $this->assertEquals($planDetails->features, $restoredPlanDetails->features, 'features should be preserved');
                $this->assertEquals($planDetails->tier, $restoredPlanDetails->tier, 'tier should be preserved');
            });
    }

    /**
     * Feature: polar-subscription, Property 14: Subscription Serialization Round-Trip (JSON variant)
     * Validates: Requirements 7.4
     * 
     * For any valid PlanDetails object, serializing to JSON and deserializing back
     * SHALL produce an equivalent PlanDetails object.
     */
    #[Test]
    public function plan_details_json_round_trip_preserves_data(): void
    {
        $tiers = ['basic', 'standard', 'pro'];
        $planIds = ['free_trial', 'standard', 'pro'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($planIds),
                Generators::suchThat(
                    fn($s) => strlen($s) > 0 && strlen($s) <= 100,
                    Generators::string()
                ),
                Generators::choose(0, 100000000),
                Generators::suchThat(
                    fn($s) => strlen($s) > 0 && strlen($s) <= 50,
                    Generators::string()
                ),
                Generators::elements($tiers)
            )
            ->then(function (string $id, string $name, int $priceMonthly, string $polarProductId, string $tier) {
                $features = ['Feature A', 'Feature B'];

                $planDetails = new PlanDetails(
                    id: $id,
                    name: $name,
                    priceMonthly: $priceMonthly,
                    polarProductId: $polarProductId,
                    features: $features,
                    tier: $tier,
                );

                // Serialize to JSON
                $json = json_encode($planDetails->toArray());

                // Deserialize from JSON
                $decoded = json_decode($json, true);
                $restoredPlanDetails = PlanDetails::fromArray($decoded);

                // Property: All fields should be preserved after JSON round-trip
                $this->assertEquals($planDetails->id, $restoredPlanDetails->id, 'id should be preserved after JSON round-trip');
                $this->assertEquals($planDetails->name, $restoredPlanDetails->name, 'name should be preserved after JSON round-trip');
                $this->assertEquals($planDetails->priceMonthly, $restoredPlanDetails->priceMonthly, 'priceMonthly should be preserved after JSON round-trip');
                $this->assertEquals($planDetails->polarProductId, $restoredPlanDetails->polarProductId, 'polarProductId should be preserved after JSON round-trip');
                $this->assertEquals($planDetails->features, $restoredPlanDetails->features, 'features should be preserved after JSON round-trip');
                $this->assertEquals($planDetails->tier, $restoredPlanDetails->tier, 'tier should be preserved after JSON round-trip');
            });
    }
}
