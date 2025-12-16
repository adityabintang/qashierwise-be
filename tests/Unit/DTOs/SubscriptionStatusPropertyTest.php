<?php

namespace Tests\Unit\DTOs;

use App\DTOs\SubscriptionStatus;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for SubscriptionStatus DTO
 * 
 * Feature: polar-subscription
 */
class SubscriptionStatusPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: polar-subscription, Property 14: Subscription Serialization Round-Trip
     * Validates: Requirements 7.4
     * 
     * For any valid SubscriptionStatus object, serializing to array and deserializing back
     * SHALL produce an equivalent SubscriptionStatus object.
     */
    #[Test]
    public function subscription_status_serialization_round_trip_preserves_data(): void
    {
        $statuses = ['trial', 'trial_expired', 'active', 'cancelled', 'expired'];
        $planNames = ['free_trial', 'standard', 'pro'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($statuses),
                Generators::elements($planNames),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::choose(0, 14)
                ),
                Generators::bool(), // has period_end
                Generators::bool()  // has cancelled_at
            )
            ->then(function (string $status, string $planName, ?int $trialDaysRemaining, bool $hasPeriodEnd, bool $hasCancelledAt) {
                // Generate dates based on flags
                $periodEnd = $hasPeriodEnd ? Carbon::now()->addDays(rand(1, 365)) : null;
                $cancelledAt = $hasCancelledAt ? Carbon::now()->subDays(rand(1, 30)) : null;

                // Create a SubscriptionStatus instance with generated data
                $subscriptionStatus = new SubscriptionStatus(
                    status: $status,
                    planName: $planName,
                    trialDaysRemaining: $trialDaysRemaining,
                    periodEnd: $periodEnd,
                    cancelledAt: $cancelledAt,
                );

                // Serialize to array
                $array = $subscriptionStatus->toArray();

                // Deserialize from array
                $restoredStatus = SubscriptionStatus::fromArray($array);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($subscriptionStatus->status, $restoredStatus->status, 'status should be preserved');
                $this->assertEquals($subscriptionStatus->planName, $restoredStatus->planName, 'planName should be preserved');
                $this->assertEquals($subscriptionStatus->trialDaysRemaining, $restoredStatus->trialDaysRemaining, 'trialDaysRemaining should be preserved');
                
                // For Carbon dates, compare timestamps to handle timezone differences
                if ($subscriptionStatus->periodEnd !== null) {
                    $this->assertNotNull($restoredStatus->periodEnd, 'periodEnd should not be null when original is not null');
                    $this->assertEquals(
                        $subscriptionStatus->periodEnd->timestamp,
                        $restoredStatus->periodEnd->timestamp,
                        'periodEnd timestamp should be preserved'
                    );
                } else {
                    $this->assertNull($restoredStatus->periodEnd, 'periodEnd should be null when original is null');
                }

                if ($subscriptionStatus->cancelledAt !== null) {
                    $this->assertNotNull($restoredStatus->cancelledAt, 'cancelledAt should not be null when original is not null');
                    $this->assertEquals(
                        $subscriptionStatus->cancelledAt->timestamp,
                        $restoredStatus->cancelledAt->timestamp,
                        'cancelledAt timestamp should be preserved'
                    );
                } else {
                    $this->assertNull($restoredStatus->cancelledAt, 'cancelledAt should be null when original is null');
                }
            });
    }

    /**
     * Feature: polar-subscription, Property 14: Subscription Serialization Round-Trip (JSON variant)
     * Validates: Requirements 7.4
     * 
     * For any valid SubscriptionStatus object, serializing to JSON and deserializing back
     * SHALL produce an equivalent SubscriptionStatus object.
     */
    #[Test]
    public function subscription_status_json_round_trip_preserves_data(): void
    {
        $statuses = ['trial', 'trial_expired', 'active', 'cancelled', 'expired'];
        $planNames = ['free_trial', 'standard', 'pro'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($statuses),
                Generators::elements($planNames),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::choose(0, 14)
                ),
                Generators::bool(),
                Generators::bool()
            )
            ->then(function (string $status, string $planName, ?int $trialDaysRemaining, bool $hasPeriodEnd, bool $hasCancelledAt) {
                $periodEnd = $hasPeriodEnd ? Carbon::now()->addDays(rand(1, 365)) : null;
                $cancelledAt = $hasCancelledAt ? Carbon::now()->subDays(rand(1, 30)) : null;

                $subscriptionStatus = new SubscriptionStatus(
                    status: $status,
                    planName: $planName,
                    trialDaysRemaining: $trialDaysRemaining,
                    periodEnd: $periodEnd,
                    cancelledAt: $cancelledAt,
                );

                // Serialize to JSON
                $json = json_encode($subscriptionStatus->toArray());

                // Deserialize from JSON
                $decoded = json_decode($json, true);
                $restoredStatus = SubscriptionStatus::fromArray($decoded);

                // Property: All fields should be preserved after JSON round-trip
                $this->assertEquals($subscriptionStatus->status, $restoredStatus->status, 'status should be preserved after JSON round-trip');
                $this->assertEquals($subscriptionStatus->planName, $restoredStatus->planName, 'planName should be preserved after JSON round-trip');
                $this->assertEquals($subscriptionStatus->trialDaysRemaining, $restoredStatus->trialDaysRemaining, 'trialDaysRemaining should be preserved after JSON round-trip');

                if ($subscriptionStatus->periodEnd !== null) {
                    $this->assertNotNull($restoredStatus->periodEnd, 'periodEnd should not be null after JSON round-trip');
                    $this->assertEquals(
                        $subscriptionStatus->periodEnd->timestamp,
                        $restoredStatus->periodEnd->timestamp,
                        'periodEnd timestamp should be preserved after JSON round-trip'
                    );
                } else {
                    $this->assertNull($restoredStatus->periodEnd, 'periodEnd should be null after JSON round-trip');
                }

                if ($subscriptionStatus->cancelledAt !== null) {
                    $this->assertNotNull($restoredStatus->cancelledAt, 'cancelledAt should not be null after JSON round-trip');
                    $this->assertEquals(
                        $subscriptionStatus->cancelledAt->timestamp,
                        $restoredStatus->cancelledAt->timestamp,
                        'cancelledAt timestamp should be preserved after JSON round-trip'
                    );
                } else {
                    $this->assertNull($restoredStatus->cancelledAt, 'cancelledAt should be null after JSON round-trip');
                }
            });
    }
}
