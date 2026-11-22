<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionCheckoutApiResponseTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_response_contains_redirect_url_and_checkout_url(): void
    {
        config([
            'subscription.xendit.api_key' => 'test-key',
            'subscription.plans.pro.durations.1_month.price' => 350000,
            'subscription.plans.pro.durations.1_month.months' => 1,
            'subscription.plans.pro.durations.1_month.price_per_month' => 350000,
            'subscription.plans.pro.durations.1_month.name' => '1 Bulan',
        ]);

        $user = User::factory()->create([
            'is_master_admin' => true,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $xenditServiceMock = Mockery::mock(XenditSubscriptionService::class);
        $xenditServiceMock->shouldReceive('isConfigured')->once()->andReturn(true);
        $xenditServiceMock->shouldReceive('createRecurringPlan')
            ->once()
            ->withArgs(function (User $requestUser, string $planId, string $duration) use ($user) {
                return $requestUser->id === $user->id
                    && $planId === 'pro'
                    && $duration === '1_month';
            })
            ->andReturn([
                'subscription_id' => 'repl_test_123',
                'action_url' => 'https://checkout.example.com/session-123',
                'status' => 'REQUIRES_ACTION',
            ]);

        $this->instance(XenditSubscriptionService::class, $xenditServiceMock);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/subscription/checkout', [
            'plan_id' => 'pro',
            'duration' => '1_month',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.checkout_url', 'https://checkout.example.com/session-123')
            ->assertJsonPath('data.redirect_url', 'https://checkout.example.com/session-123')
            ->assertJsonPath('data.subscription_id', 'repl_test_123')
            ->assertJsonPath('data.status', 'REQUIRES_ACTION');

        $subscription = Subscription::where('user_id', $user->id)->first();

        $this->assertNotNull($subscription);
        $this->assertSame('repl_test_123', $subscription->xendit_subscription_id);
        $this->assertSame('pro', $subscription->plan_name);
        $this->assertSame('pending', $subscription->status);
    }
}
