<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackwardCompatibilityVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected SubscriptionService $subscriptionService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subscriptionService = app(SubscriptionService::class);
    }

    /** @test */
    public function database_schema_supports_both_polar_and_midtrans()
    {
        // Verify all required columns exist
        $this->assertTrue(Schema::hasColumn('subscriptions', 'polar_subscription_id'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'polar_customer_id'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'midtrans_subscription_id'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'midtrans_customer_id'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'provider'));
        $this->assertTrue(Schema::hasColumn('subscriptions', 'metadata'));
    }

    /** @test */
    public function polar_subscriptions_can_be_created_and_retrieved()
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'polar_customer_id' => 'polar_cus_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'provider' => 'polar',
            'polar_subscription_id' => 'polar_sub_123',
        ]);

        $retrieved = Subscription::find($subscription->id);
        $this->assertEquals('polar', $retrieved->provider);
        $this->assertEquals('polar_sub_123', $retrieved->polar_subscription_id);
    }

    /** @test */
    public function midtrans_subscriptions_can_be_created_and_retrieved()
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'midtrans_customer_id' => 'midtrans_cus_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'provider' => 'midtrans',
            'midtrans_subscription_id' => 'midtrans_sub_123',
        ]);

        $retrieved = Subscription::find($subscription->id);
        $this->assertEquals('midtrans', $retrieved->provider);
        $this->assertEquals('midtrans_sub_123', $retrieved->midtrans_subscription_id);
    }

    /** @test */
    public function subscription_service_works_with_polar_subscriptions()
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $status = $this->subscriptionService->getUserSubscriptionStatus($user);

        $this->assertEquals('active', $status['status']);
        $this->assertEquals('standard', $status['plan_name']);
        $this->assertEquals('polar', $status['provider']);
    }

    /** @test */
    public function subscription_service_works_with_midtrans_subscriptions()
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $status = $this->subscriptionService->getUserSubscriptionStatus($user);

        $this->assertEquals('active', $status['status']);
        $this->assertEquals('pro', $status['plan_name']);
        $this->assertEquals('midtrans', $status['provider']);
    }

    /** @test */
    public function feature_access_works_for_polar_users()
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $canAccess = $this->subscriptionService->canAccessFeature($user, 'standard');
        $this->assertTrue($canAccess);
    }

    /** @test */
    public function feature_access_works_for_midtrans_users()
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $canAccess = $this->subscriptionService->canAccessFeature($user, 'pro');
        $this->assertTrue($canAccess);
    }

    /** @test */
    public function user_relationship_works_with_polar_subscriptions()
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertNotNull($user->subscription);
        $this->assertEquals($subscription->id, $user->subscription->id);
        $this->assertEquals('polar', $user->subscription->provider);
    }

    /** @test */
    public function user_relationship_works_with_midtrans_subscriptions()
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertNotNull($user->subscription);
        $this->assertEquals($subscription->id, $user->subscription->id);
        $this->assertEquals('midtrans', $user->subscription->provider);
    }

    /** @test */
    public function both_providers_can_coexist_in_database()
    {
        $polarUser = User::factory()->create();
        $midtransUser = User::factory()->create();

        Subscription::create([
            'user_id' => $polarUser->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Subscription::create([
            'user_id' => $midtransUser->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->assertEquals(1, Subscription::where('provider', 'polar')->count());
        $this->assertEquals(1, Subscription::where('provider', 'midtrans')->count());
        $this->assertEquals(2, Subscription::count());
    }

    /** @test */
    public function subscription_queries_can_filter_by_provider()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        Subscription::create([
            'user_id' => $user1->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        Subscription::create([
            'user_id' => $user2->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $polarSubs = Subscription::where('provider', 'polar')->get();
        $midtransSubs = Subscription::where('provider', 'midtrans')->get();

        $this->assertCount(1, $polarSubs);
        $this->assertCount(1, $midtransSubs);
        $this->assertEquals('polar_sub_123', $polarSubs->first()->polar_subscription_id);
        $this->assertEquals('midtrans_sub_123', $midtransSubs->first()->midtrans_subscription_id);
    }

    /** @test */
    public function metadata_column_works_for_both_providers()
    {
        $user = User::factory()->create();

        $polarSub = Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => ['source' => 'polar', 'legacy' => true],
        ]);

        $this->assertEquals(['source' => 'polar', 'legacy' => true], $polarSub->metadata);

        $midtransSub = Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'midtrans_sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'pro',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => ['source' => 'midtrans', 'payment_method' => 'credit_card'],
        ]);

        $this->assertEquals(['source' => 'midtrans', 'payment_method' => 'credit_card'], $midtransSub->metadata);
    }

    /** @test */
    public function cancelled_polar_subscriptions_remain_accessible()
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'cancelled',
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
            'cancelled_at' => now()->subWeek(),
        ]);

        $status = $this->subscriptionService->getUserSubscriptionStatus($user);

        $this->assertEquals('cancelled', $status['status']);
        $this->assertEquals('polar', $status['provider']);
        $this->assertNotNull($subscription->cancelled_at);
    }

    /** @test */
    public function expired_polar_subscriptions_are_handled_correctly()
    {
        $user = User::factory()->create();

        Subscription::create([
            'user_id' => $user->id,
            'polar_subscription_id' => 'polar_sub_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'expired',
            'current_period_start' => now()->subMonths(2),
            'current_period_end' => now()->subMonth(),
        ]);

        $status = $this->subscriptionService->getUserSubscriptionStatus($user);

        $this->assertEquals('expired', $status['status']);
        $this->assertFalse($this->subscriptionService->canAccessFeature($user, 'standard'));
    }
}
