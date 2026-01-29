<?php

namespace Tests\Unit\Services;

use App\Models\Subscription;
use App\Models\User;
use App\Services\PlanConfig;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for SubscriptionService
 *
 * Feature: polar-subscription
 */
class SubscriptionServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    private SubscriptionService $subscriptionService;

    private PlanConfig $planConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planConfig = new PlanConfig;
        $this->subscriptionService = new SubscriptionService($this->planConfig);
    }

    /**
     * Feature: polar-subscription, Property 8: Default Trial Status for New Users
     * Validates: Requirements 5.2
     *
     * For any user without a subscription record and registered within 14 days,
     * getUserSubscriptionStatus SHALL return status 'trial' with planName 'free_trial'.
     */
    #[Test]
    public function new_user_without_subscription_within_trial_period_gets_trial_status(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(0, 13) // Days since registration (0-13 = within trial)
            )
            ->then(function (int $daysSinceRegistration) {
                // Create a user registered within trial period
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                $status = $this->subscriptionService->getUserSubscriptionStatus($user);

                // Property: Status must be 'trial' for users within trial period
                $this->assertEquals(
                    SubscriptionService::STATUS_TRIAL,
                    $status->status,
                    "User registered {$daysSinceRegistration} days ago should have 'trial' status"
                );

                // Property: Plan name must be 'free_trial'
                $this->assertEquals(
                    PlanConfig::PLAN_FREE_TRIAL,
                    $status->planName,
                    "User on trial should have 'free_trial' plan name"
                );

                // Property: Trial days remaining should be positive
                $this->assertGreaterThan(
                    0,
                    $status->trialDaysRemaining,
                    'User within trial period should have positive trial days remaining'
                );

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 9: Trial Expiration Detection
     * Validates: Requirements 5.3
     *
     * For any user without a subscription record and registered more than 14 days ago,
     * getUserSubscriptionStatus SHALL return status 'trial_expired'.
     */
    #[Test]
    public function user_without_subscription_after_trial_period_gets_trial_expired_status(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(15, 365) // Days since registration (15+ = trial expired)
            )
            ->then(function (int $daysSinceRegistration) {
                // Create a user registered after trial period
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                $status = $this->subscriptionService->getUserSubscriptionStatus($user);

                // Property: Status must be 'trial_expired' for users past trial period
                $this->assertEquals(
                    SubscriptionService::STATUS_TRIAL_EXPIRED,
                    $status->status,
                    "User registered {$daysSinceRegistration} days ago should have 'trial_expired' status"
                );

                // Property: Plan name must be 'free_trial'
                $this->assertEquals(
                    PlanConfig::PLAN_FREE_TRIAL,
                    $status->planName,
                    "User with expired trial should still have 'free_trial' plan name"
                );

                // Property: Trial days remaining should be 0
                $this->assertEquals(
                    0,
                    $status->trialDaysRemaining,
                    'User past trial period should have 0 trial days remaining'
                );

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 12: Trial Days Calculation
     * Validates: Requirements 6.2
     *
     * For any user registration date within the trial period,
     * calculateTrialDaysRemaining SHALL return a value between 0 and 14
     * that equals (14 - days_since_registration).
     */
    #[Test]
    public function trial_days_calculation_returns_correct_remaining_days(): void
    {
        $trialDays = (int) config('polar.trial_days', 14);

        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(0, $trialDays - 1) // Days since registration within trial
            )
            ->then(function (int $daysSinceRegistration) use ($trialDays) {
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                $daysRemaining = $this->subscriptionService->calculateTrialDaysRemaining($user);

                // Property: Days remaining should equal (trial_days - days_since_registration)
                $expectedDays = $trialDays - $daysSinceRegistration;
                $this->assertEquals(
                    $expectedDays,
                    $daysRemaining,
                    "User registered {$daysSinceRegistration} days ago should have {$expectedDays} days remaining"
                );

                // Property: Days remaining should be between 0 and trial_days
                $this->assertGreaterThanOrEqual(0, $daysRemaining);
                $this->assertLessThanOrEqual($trialDays, $daysRemaining);

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 12: Trial Days Calculation (Expired)
     * Validates: Requirements 6.2
     *
     * For any user registration date past the trial period,
     * calculateTrialDaysRemaining SHALL return 0.
     */
    #[Test]
    public function trial_days_calculation_returns_zero_for_expired_trial(): void
    {
        $trialDays = (int) config('polar.trial_days', 14);

        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose($trialDays, 365) // Days since registration past trial
            )
            ->then(function (int $daysSinceRegistration) {
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                $daysRemaining = $this->subscriptionService->calculateTrialDaysRemaining($user);

                // Property: Days remaining should be 0 for expired trial
                $this->assertEquals(
                    0,
                    $daysRemaining,
                    "User registered {$daysSinceRegistration} days ago should have 0 days remaining"
                );

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 10: Active Subscription Status
     * Validates: Requirements 5.4
     *
     * For any user with an active subscription, getUserSubscriptionStatus
     * SHALL return the correct plan name and a non-null period end date.
     */
    #[Test]
    public function user_with_active_subscription_gets_active_status_with_plan_and_period_end(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::choose(1, 30) // Days until period end
            )
            ->then(function (string $planName, int $daysUntilPeriodEnd) {
                $user = User::factory()->create();

                // Create an active subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'polar_subscription_id' => 'sub_'.uniqid(),
                    'polar_customer_id' => 'cus_'.uniqid(),
                    'plan_name' => $planName,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                ]);

                // Reload user to get fresh relationship
                $user->refresh();

                $status = $this->subscriptionService->getUserSubscriptionStatus($user);

                // Property: Status must be 'active'
                $this->assertEquals(
                    SubscriptionService::STATUS_ACTIVE,
                    $status->status,
                    "User with active subscription should have 'active' status"
                );

                // Property: Plan name must match subscription plan
                $this->assertEquals(
                    $planName,
                    $status->planName,
                    'Status plan name should match subscription plan name'
                );

                // Property: Period end must not be null
                $this->assertNotNull(
                    $status->periodEnd,
                    'Active subscription should have a non-null period end date'
                );

                // Property: Trial days remaining should be null for active subscriptions
                $this->assertNull(
                    $status->trialDaysRemaining,
                    'Active subscription should have null trial days remaining'
                );

                // Cleanup
                $subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 11: Cancelled But Active Subscription
     * Validates: Requirements 5.5
     *
     * For any subscription that is cancelled but where current date is before period_end,
     * the subscription SHALL be considered active until the period end date.
     */
    #[Test]
    public function cancelled_subscription_within_period_remains_accessible(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::choose(1, 30) // Days until period end
            )
            ->then(function (string $planName, int $daysUntilPeriodEnd) {
                $user = User::factory()->create();

                // Create a cancelled but not expired subscription
                $cancelledAt = Carbon::now()->subDays(2);
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'polar_subscription_id' => 'sub_'.uniqid(),
                    'polar_customer_id' => 'cus_'.uniqid(),
                    'plan_name' => $planName,
                    'status' => 'active', // Status remains active until period end
                    'current_period_start' => Carbon::now()->subDays(10),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                    'cancelled_at' => $cancelledAt,
                ]);

                // Reload user to get fresh relationship
                $user->refresh();

                $status = $this->subscriptionService->getUserSubscriptionStatus($user);

                // Property: Status must be 'cancelled' (but still accessible)
                $this->assertEquals(
                    SubscriptionService::STATUS_CANCELLED,
                    $status->status,
                    "Cancelled subscription within period should have 'cancelled' status"
                );

                // Property: Plan name must match subscription plan
                $this->assertEquals(
                    $planName,
                    $status->planName,
                    'Status plan name should match subscription plan name'
                );

                // Property: Period end must not be null
                $this->assertNotNull(
                    $status->periodEnd,
                    'Cancelled subscription should have a non-null period end date'
                );

                // Property: Cancelled at must not be null
                $this->assertNotNull(
                    $status->cancelledAt,
                    'Cancelled subscription should have a non-null cancelled_at date'
                );

                // Property: User should still have access to their tier features
                $tier = $planName === PlanConfig::PLAN_PRO
                    ? PlanConfig::TIER_PRO
                    : PlanConfig::TIER_STANDARD;

                $canAccess = $this->subscriptionService->canAccessFeature($user, $tier);
                $this->assertTrue(
                    $canAccess,
                    "User with cancelled but active subscription should still access {$tier} features"
                );

                // Cleanup
                $subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 5: Subscription Created Event Processing
     * Validates: Requirements 4.2
     *
     * For any valid subscription.created webhook event, processing SHALL result
     * in a subscription record with status 'active' and the correct plan name.
     */
    #[Test]
    public function subscription_created_event_creates_active_subscription_with_correct_plan(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::choose(1, 999999), // Random subscription ID suffix
                Generators::choose(1, 999999)  // Random customer ID suffix
            )
            ->then(function (string $planName, int $subscriptionIdSuffix, int $customerIdSuffix) {
                $user = User::factory()->create();

                $subscriptionId = 'sub_'.$subscriptionIdSuffix.'_'.uniqid();
                $customerId = 'cus_'.$customerIdSuffix.'_'.uniqid();

                // Build webhook event - use metadata for plan_id since product_id may not be configured
                $event = [
                    'type' => 'subscription.created',
                    'data' => [
                        'id' => $subscriptionId,
                        'customer_id' => $customerId,
                        'status' => 'active',
                        'current_period_start' => Carbon::now()->toIso8601String(),
                        'current_period_end' => Carbon::now()->addMonth()->toIso8601String(),
                        'metadata' => [
                            'user_id' => (string) $user->id,
                            'plan_id' => $planName,
                        ],
                    ],
                ];

                // Process the webhook event
                $this->subscriptionService->processWebhookEvent($event);

                // Reload user to get fresh relationship
                $user->refresh();

                // Property: Subscription must exist
                $this->assertNotNull(
                    $user->subscription,
                    'Subscription should be created after subscription.created event'
                );

                // Property: Status must be 'active'
                $this->assertEquals(
                    'active',
                    $user->subscription->status,
                    "Subscription status should be 'active' after subscription.created event"
                );

                // Property: Plan name must match
                $this->assertEquals(
                    $planName,
                    $user->subscription->plan_name,
                    'Subscription plan name should match the event plan'
                );

                // Property: Polar subscription ID must match
                $this->assertEquals(
                    $subscriptionId,
                    $user->subscription->polar_subscription_id,
                    'Polar subscription ID should match the event data'
                );

                // Property: Polar customer ID must match
                $this->assertEquals(
                    $customerId,
                    $user->subscription->polar_customer_id,
                    'Polar customer ID should match the event data'
                );

                // Cleanup
                $user->subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 6: Subscription Updated Event Processing
     * Validates: Requirements 4.3
     *
     * For any valid subscription.updated webhook event, processing SHALL update
     * the subscription record to reflect the new status and plan from the event.
     */
    #[Test]
    public function subscription_updated_event_updates_subscription_status_and_plan(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];
        $validStatuses = ['active', 'past_due', 'unpaid'];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::elements($validPlanNames),
                Generators::elements($validStatuses),
                Generators::choose(1, 999999) // Random ID suffix
            )
            ->then(function (string $initialPlan, string $updatedPlan, string $updatedStatus, int $idSuffix) {
                $user = User::factory()->create();

                $subscriptionId = 'sub_'.$idSuffix.'_'.uniqid();
                $customerId = 'cus_'.$idSuffix.'_'.uniqid();

                // Create initial subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'polar_subscription_id' => $subscriptionId,
                    'polar_customer_id' => $customerId,
                    'plan_name' => $initialPlan,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(15),
                    'current_period_end' => Carbon::now()->addDays(15),
                ]);

                // Build webhook event for update - use metadata for plan_id
                $event = [
                    'type' => 'subscription.updated',
                    'data' => [
                        'id' => $subscriptionId,
                        'customer_id' => $customerId,
                        'status' => $updatedStatus,
                        'current_period_start' => Carbon::now()->toIso8601String(),
                        'current_period_end' => Carbon::now()->addMonth()->toIso8601String(),
                        'metadata' => [
                            'user_id' => (string) $user->id,
                            'plan_id' => $updatedPlan,
                        ],
                    ],
                ];

                // Process the webhook event
                $this->subscriptionService->processWebhookEvent($event);

                // Reload subscription
                $subscription->refresh();

                // Property: Status must be updated
                $this->assertEquals(
                    $updatedStatus,
                    $subscription->status,
                    "Subscription status should be updated to '{$updatedStatus}'"
                );

                // Property: Plan name must be updated
                $this->assertEquals(
                    $updatedPlan,
                    $subscription->plan_name,
                    "Subscription plan should be updated to '{$updatedPlan}'"
                );

                // Cleanup
                $subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 7: Subscription Cancelled Event Processing
     * Validates: Requirements 4.4
     *
     * For any valid subscription.cancelled webhook event, processing SHALL mark
     * the subscription as cancelled and record a non-null cancellation timestamp.
     */
    #[Test]
    public function subscription_cancelled_event_marks_subscription_cancelled_with_timestamp(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::choose(1, 30) // Days until period end
            )
            ->then(function (string $planName, int $daysUntilPeriodEnd) {
                $user = User::factory()->create();

                $subscriptionId = 'sub_'.uniqid();
                $customerId = 'cus_'.uniqid();

                // Create active subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'polar_subscription_id' => $subscriptionId,
                    'polar_customer_id' => $customerId,
                    'plan_name' => $planName,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                ]);

                // Verify subscription is not cancelled initially
                $this->assertNull($subscription->cancelled_at);

                $cancelledAt = Carbon::now();

                // Build webhook event for cancellation
                $event = [
                    'type' => 'subscription.cancelled',
                    'data' => [
                        'id' => $subscriptionId,
                        'customer_id' => $customerId,
                        'canceled_at' => $cancelledAt->toIso8601String(),
                    ],
                ];

                // Process the webhook event
                $this->subscriptionService->processWebhookEvent($event);

                // Reload subscription
                $subscription->refresh();

                // Property: Subscription must be marked as cancelled
                $this->assertTrue(
                    $subscription->isCancelled(),
                    'Subscription should be marked as cancelled after subscription.cancelled event'
                );

                // Property: Cancelled_at must not be null
                $this->assertNotNull(
                    $subscription->cancelled_at,
                    'Subscription cancelled_at should not be null after cancellation'
                );

                // Property: Cancelled_at should be close to the event timestamp
                $this->assertTrue(
                    $subscription->cancelled_at->diffInSeconds($cancelledAt) < 5,
                    'Subscription cancelled_at should match the event timestamp'
                );

                // Cleanup
                $subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 15: Standard Tier Feature Access
     * Validates: Requirements 9.1
     *
     * For any user with an active Standard or Pro subscription,
     * canAccessFeature('standard') SHALL return true.
     */
    #[Test]
    public function user_with_standard_or_pro_subscription_can_access_standard_features(): void
    {
        $validPlanNames = [PlanConfig::PLAN_STANDARD, PlanConfig::PLAN_PRO];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::elements($validPlanNames),
                Generators::choose(1, 30) // Days until period end
            )
            ->then(function (string $planName, int $daysUntilPeriodEnd) {
                $user = User::factory()->create();

                // Create an active subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'polar_subscription_id' => 'sub_'.uniqid(),
                    'polar_customer_id' => 'cus_'.uniqid(),
                    'plan_name' => $planName,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                ]);

                // Reload user to get fresh relationship
                $user->refresh();

                // Property: User with Standard or Pro subscription can access standard features
                $canAccessStandard = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_STANDARD);
                $this->assertTrue(
                    $canAccessStandard,
                    "User with {$planName} subscription should be able to access standard tier features"
                );

                // Property: User with Standard or Pro subscription can also access basic features
                $canAccessBasic = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_BASIC);
                $this->assertTrue(
                    $canAccessBasic,
                    "User with {$planName} subscription should be able to access basic tier features"
                );

                // Cleanup
                $subscription->delete();
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 16: Pro Tier Feature Access
     * Validates: Requirements 9.2
     *
     * For any user with an active Pro subscription, canAccessFeature('pro') SHALL return true,
     * and for users without Pro subscription, it SHALL return false.
     */
    #[Test]
    public function only_user_with_pro_subscription_can_access_pro_features(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(1, 30) // Days until period end
            )
            ->then(function (int $daysUntilPeriodEnd) {
                // Test Pro user CAN access pro features
                $proUser = User::factory()->create();
                $proSubscription = Subscription::create([
                    'user_id' => $proUser->id,
                    'polar_subscription_id' => 'sub_pro_'.uniqid(),
                    'polar_customer_id' => 'cus_pro_'.uniqid(),
                    'plan_name' => PlanConfig::PLAN_PRO,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                ]);
                $proUser->refresh();

                $proCanAccessPro = $this->subscriptionService->canAccessFeature($proUser, PlanConfig::TIER_PRO);
                $this->assertTrue(
                    $proCanAccessPro,
                    'User with Pro subscription should be able to access pro tier features'
                );

                // Test Standard user CANNOT access pro features
                $standardUser = User::factory()->create();
                $standardSubscription = Subscription::create([
                    'user_id' => $standardUser->id,
                    'polar_subscription_id' => 'sub_std_'.uniqid(),
                    'polar_customer_id' => 'cus_std_'.uniqid(),
                    'plan_name' => PlanConfig::PLAN_STANDARD,
                    'status' => 'active',
                    'current_period_start' => Carbon::now()->subDays(5),
                    'current_period_end' => Carbon::now()->addDays($daysUntilPeriodEnd),
                ]);
                $standardUser->refresh();

                $standardCanAccessPro = $this->subscriptionService->canAccessFeature($standardUser, PlanConfig::TIER_PRO);
                $this->assertFalse(
                    $standardCanAccessPro,
                    'User with Standard subscription should NOT be able to access pro tier features'
                );

                // Cleanup
                $proSubscription->delete();
                $proUser->delete();
                $standardSubscription->delete();
                $standardUser->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 17: Trial User Feature Access Restriction
     * Validates: Requirements 9.4
     *
     * For any user on free trial, canAccessFeature SHALL return true only for 'basic' tier
     * features and false for 'standard' and 'pro' tier features.
     */
    #[Test]
    public function trial_user_can_only_access_basic_features(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(0, 13) // Days since registration (within trial period)
            )
            ->then(function (int $daysSinceRegistration) {
                // Create a trial user (no subscription, within trial period)
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                // Property: Trial user CAN access basic features
                $canAccessBasic = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_BASIC);
                $this->assertTrue(
                    $canAccessBasic,
                    'Trial user should be able to access basic tier features'
                );

                // Property: Trial user CANNOT access standard features
                $canAccessStandard = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_STANDARD);
                $this->assertFalse(
                    $canAccessStandard,
                    'Trial user should NOT be able to access standard tier features'
                );

                // Property: Trial user CANNOT access pro features
                $canAccessPro = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_PRO);
                $this->assertFalse(
                    $canAccessPro,
                    'Trial user should NOT be able to access pro tier features'
                );

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Feature: polar-subscription, Property 17: Trial User Feature Access Restriction (Expired Trial)
     * Validates: Requirements 9.4
     *
     * For any user with expired trial, canAccessFeature SHALL return true only for 'basic' tier
     * features and false for 'standard' and 'pro' tier features.
     */
    #[Test]
    public function expired_trial_user_can_only_access_basic_features(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                Generators::choose(15, 365) // Days since registration (past trial period)
            )
            ->then(function (int $daysSinceRegistration) {
                // Create an expired trial user (no subscription, past trial period)
                $user = User::factory()->create([
                    'created_at' => Carbon::now()->subDays($daysSinceRegistration),
                ]);

                // Property: Expired trial user CAN access basic features
                $canAccessBasic = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_BASIC);
                $this->assertTrue(
                    $canAccessBasic,
                    'Expired trial user should be able to access basic tier features'
                );

                // Property: Expired trial user CANNOT access standard features
                $canAccessStandard = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_STANDARD);
                $this->assertFalse(
                    $canAccessStandard,
                    'Expired trial user should NOT be able to access standard tier features'
                );

                // Property: Expired trial user CANNOT access pro features
                $canAccessPro = $this->subscriptionService->canAccessFeature($user, PlanConfig::TIER_PRO);
                $this->assertFalse(
                    $canAccessPro,
                    'Expired trial user should NOT be able to access pro tier features'
                );

                // Cleanup
                $user->delete();
            });
    }

    /**
     * Helper method to get Polar product ID for a plan name.
     */
    private function getProductIdForPlan(string $planName): string
    {
        return match ($planName) {
            PlanConfig::PLAN_STANDARD => config('polar.products.standard', 'prod_standard_test'),
            PlanConfig::PLAN_PRO => config('polar.products.pro', 'prod_pro_test'),
            default => 'prod_unknown',
        };
    }
}
