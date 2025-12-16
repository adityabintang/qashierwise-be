<?php

namespace Tests\Unit\Models;

use App\Models\Subscription;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for Subscription model methods
 * 
 * Feature: polar-subscription
 * Validates: Requirements 5.4, 5.5
 */
class SubscriptionTest extends TestCase
{
    /**
     * Create a Subscription instance with attributes set directly to avoid database connection.
     * Uses reflection to set attributes directly on the model's attributes array.
     */
    private function createSubscription(array $attributes): Subscription
    {
        $subscription = new Subscription();
        
        // Use reflection to set attributes directly without triggering casts
        $reflection = new ReflectionClass($subscription);
        $attributesProperty = $reflection->getProperty('attributes');
        $attributesProperty->setAccessible(true);
        
        $attrs = [
            'status' => $attributes['status'] ?? 'active',
            'current_period_start' => $attributes['current_period_start'] ?? Carbon::now(),
            'current_period_end' => $attributes['current_period_end'] ?? Carbon::now()->addDays(30),
            'cancelled_at' => $attributes['cancelled_at'] ?? null,
        ];
        
        $attributesProperty->setValue($subscription, $attrs);
        
        return $subscription;
    }

    /**
     * Test isActive() returns true for active subscription with future period end.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_active_returns_true_for_active_subscription_with_future_period_end(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertTrue($subscription->isActive());
    }

    /**
     * Test isActive() returns false for active subscription with past period end.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_active_returns_false_for_active_subscription_with_past_period_end(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(45),
            'current_period_end' => Carbon::now()->subDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertFalse($subscription->isActive());
    }

    /**
     * Test isActive() returns false for cancelled status even with future period end.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_active_returns_false_for_cancelled_status(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'cancelled',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => Carbon::now(),
        ]);

        $this->assertFalse($subscription->isActive());
    }

    /**
     * Test isActive() returns false for expired status.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_active_returns_false_for_expired_status(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'expired',
            'current_period_start' => Carbon::now()->subDays(45),
            'current_period_end' => Carbon::now()->subDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertFalse($subscription->isActive());
    }

    /**
     * Test isCancelled() returns true when cancelled_at is set.
     * Validates: Requirements 5.5
     */
    #[Test]
    public function is_cancelled_returns_true_when_cancelled_at_is_set(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => Carbon::now(),
        ]);

        $this->assertTrue($subscription->isCancelled());
    }

    /**
     * Test isCancelled() returns false when cancelled_at is null.
     * Validates: Requirements 5.5
     */
    #[Test]
    public function is_cancelled_returns_false_when_cancelled_at_is_null(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertFalse($subscription->isCancelled());
    }

    /**
     * Test cancelled subscription remains active until period end (Requirements 5.5).
     * A subscription that is cancelled but has future period_end should still be active.
     * Validates: Requirements 5.5
     */
    #[Test]
    public function cancelled_subscription_remains_active_until_period_end(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => Carbon::now()->subDays(5),
        ]);

        // Should be cancelled
        $this->assertTrue($subscription->isCancelled());
        // But still active until period end
        $this->assertTrue($subscription->isActive());
        // And not expired yet
        $this->assertFalse($subscription->isExpired());
    }

    /**
     * Test isExpired() returns true when period end is in the past.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_expired_returns_true_when_period_end_is_past(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(45),
            'current_period_end' => Carbon::now()->subDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertTrue($subscription->isExpired());
    }

    /**
     * Test isExpired() returns false when period end is in the future.
     * Validates: Requirements 5.4
     */
    #[Test]
    public function is_expired_returns_false_when_period_end_is_future(): void
    {
        $subscription = $this->createSubscription([
            'status' => 'active',
            'current_period_start' => Carbon::now()->subDays(15),
            'current_period_end' => Carbon::now()->addDays(15),
            'cancelled_at' => null,
        ]);

        $this->assertFalse($subscription->isExpired());
    }
}
