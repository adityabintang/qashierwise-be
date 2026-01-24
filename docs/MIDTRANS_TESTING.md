# Midtrans Subscription Testing Guide

## Overview

This document provides comprehensive testing procedures for the Midtrans subscription integration, including unit tests, integration tests, property-based tests, and manual testing procedures.

## Testing Strategy

### Testing Pyramid

```
                    ┌─────────────┐
                    │   Manual    │  (10%)
                    │   Testing   │
                    └─────────────┘
                  ┌─────────────────┐
                  │   Integration   │  (30%)
                  │     Tests       │
                  └─────────────────┘
              ┌───────────────────────┐
              │     Unit Tests        │  (60%)
              └───────────────────────┘
```

### Test Coverage Goals

- **Unit Tests**: 80%+ code coverage
- **Integration Tests**: All critical user flows
- **Property-Based Tests**: Core business logic
- **Manual Tests**: End-to-end scenarios in sandbox

## Unit Testing

### Test Environment Setup

**File**: `phpunit.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php">
    <testsuites>
        <testsuite name="Unit">
            <directory suffix="Test.php">./tests/Unit</directory>
        </testsuite>
        <testsuite name="Feature">
            <directory suffix="Test.php">./tests/Feature</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="MIDTRANS_SERVER_KEY" value="test_server_key"/>
        <env name="MIDTRANS_CLIENT_KEY" value="test_client_key"/>
        <env name="MIDTRANS_IS_PRODUCTION" value="false"/>
    </php>
</phpunit>
```

### Running Tests

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Unit/Services/MidtransSubscriptionServiceTest.php

# Run specific test method
php artisan test --filter test_creates_subscription_successfully
```


### Unit Test Examples

#### MidtransSubscriptionService Tests

**File**: `tests/Unit/Services/MidtransSubscriptionServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Services\MidtransSubscriptionService;
use App\Exceptions\MidtransApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MidtransSubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private MidtransSubscriptionService $service;
    private User $user;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MidtransSubscriptionService();
        $this->user = User::factory()->create();
    }
    
    public function test_creates_subscription_successfully()
    {
        Http::fake([
            '*/subscriptions' => Http::response([
                'id' => 'sub_123',
                'status' => 'active',
                'amount' => '99000',
            ], 201),
        ]);
        
        $result = $this->service->createSubscription(
            $this->user,
            'standard',
            'test_token'
        );
        
        $this->assertEquals('sub_123', $result['id']);
        $this->assertEquals('active', $result['status']);
    }
    
    public function test_handles_api_error_gracefully()
    {
        Http::fake([
            '*/subscriptions' => Http::response([
                'status_code' => '400',
                'status_message' => 'Invalid request',
            ], 400),
        ]);
        
        $this->expectException(MidtransApiException::class);
        
        $this->service->createSubscription(
            $this->user,
            'standard',
            'test_token'
        );
    }
    
    public function test_retries_on_connection_error()
    {
        Http::fake([
            '*/subscriptions' => Http::sequence()
                ->push(null, 0) // Connection error
                ->push(['id' => 'sub_123'], 201), // Success on retry
        ]);
        
        $result = $this->service->createSubscription(
            $this->user,
            'standard',
            'test_token'
        );
        
        $this->assertEquals('sub_123', $result['id']);
    }
    
    public function test_gets_subscription_details()
    {
        Http::fake([
            '*/subscriptions/sub_123' => Http::response([
                'id' => 'sub_123',
                'status' => 'active',
            ], 200),
        ]);
        
        $result = $this->service->getSubscription('sub_123');
        
        $this->assertNotNull($result);
        $this->assertEquals('sub_123', $result['id']);
    }
    
    public function test_cancels_subscription()
    {
        Http::fake([
            '*/subscriptions/sub_123/disable' => Http::response([
                'status_message' => 'Subscription is updated',
            ], 200),
        ]);
        
        $result = $this->service->cancelSubscription('sub_123');
        
        $this->assertTrue($result);
    }
}
```

#### SubscriptionService Tests

**File**: `tests/Unit/Services/SubscriptionServiceTest.php`

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use App\Services\MidtransSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;
    
    private SubscriptionService $service;
    private $midtransService;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        $this->midtransService = Mockery::mock(MidtransSubscriptionService::class);
        $this->service = new SubscriptionService($this->midtransService);
    }
    
    public function test_creates_midtrans_subscription()
    {
        $user = User::factory()->create();
        
        $this->midtransService
            ->shouldReceive('createSubscription')
            ->once()
            ->andReturn([
                'id' => 'sub_123',
                'status' => 'active',
            ]);
        
        $subscription = $this->service->createMidtransSubscription(
            $user,
            'standard',
            'test_token'
        );
        
        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertEquals('sub_123', $subscription->midtrans_subscription_id);
        $this->assertEquals('midtrans', $subscription->provider);
    }
    
    public function test_processes_payment_webhook()
    {
        $subscription = Subscription::factory()->create([
            'midtrans_subscription_id' => 'sub_123',
            'status' => 'pending',
        ]);
        
        $payload = [
            'order_id' => 'sub_123_1',
            'transaction_status' => 'settlement',
            'subscription_id' => 'sub_123',
        ];
        
        $this->service->processMidtransWebhook($payload);
        
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
    }
    
    public function test_handles_payment_failure()
    {
        $subscription = Subscription::factory()->create([
            'midtrans_subscription_id' => 'sub_123',
            'status' => 'active',
        ]);
        
        $payload = [
            'order_id' => 'sub_123_2',
            'transaction_status' => 'failure',
            'subscription_id' => 'sub_123',
        ];
        
        $this->service->processMidtransWebhook($payload);
        
        $subscription->refresh();
        $this->assertArrayHasKey('failure_count', $subscription->metadata);
    }
    
    public function test_user_can_access_feature_with_active_subscription()
    {
        $user = User::factory()->create();
        $user->subscription()->create([
            'plan_name' => 'pro',
            'status' => 'active',
            'provider' => 'midtrans',
        ]);
        
        $canAccess = $this->service->canAccessFeature($user, 'api_access');
        
        $this->assertTrue($canAccess);
    }
    
    public function test_user_cannot_access_feature_without_subscription()
    {
        $user = User::factory()->create();
        
        $canAccess = $this->service->canAccessFeature($user, 'api_access');
        
        $this->assertFalse($canAccess);
    }
}
```


## Integration Testing

### Integration Test Examples

#### Subscription Flow Test

**File**: `tests/Feature/MidtransSubscriptionFlowTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;

class MidtransSubscriptionFlowTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_complete_subscription_creation_flow()
    {
        // 1. Create user and login
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // 2. Mock Midtrans API
        Http::fake([
            '*/subscriptions' => Http::response([
                'id' => 'sub_123',
                'status' => 'active',
                'redirect_url' => 'https://midtrans.com/payment',
            ], 201),
        ]);
        
        // 3. Submit subscription request
        $response = $this->post('/subscription/checkout', [
            'plan_id' => 'standard',
            'payment_token' => 'test_token',
        ]);
        
        // 4. Verify redirect
        $response->assertRedirect();
        
        // 5. Verify subscription created in database
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'standard',
        ]);
    }
    
    public function test_webhook_activates_subscription()
    {
        // 1. Create pending subscription
        $user = User::factory()->create();
        $subscription = $user->subscription()->create([
            'midtrans_subscription_id' => 'sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'standard',
            'status' => 'pending',
        ]);
        
        // 2. Send webhook
        $payload = [
            'order_id' => 'sub_123_1',
            'transaction_status' => 'settlement',
            'status_code' => '200',
            'gross_amount' => '99000.00',
        ];
        
        // Calculate signature
        $signature = hash('sha512', 
            $payload['order_id'] . 
            $payload['status_code'] . 
            $payload['gross_amount'] . 
            config('midtrans.server_key')
        );
        $payload['signature_key'] = $signature;
        
        // 3. Post webhook
        $response = $this->postJson('/api/webhooks/midtrans', $payload);
        
        // 4. Verify response
        $response->assertStatus(200);
        
        // 5. Verify subscription activated
        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
    }
    
    public function test_user_can_cancel_subscription()
    {
        // 1. Create active subscription
        $user = User::factory()->create();
        $subscription = $user->subscription()->create([
            'midtrans_subscription_id' => 'sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'standard',
            'status' => 'active',
        ]);
        
        $this->actingAs($user);
        
        // 2. Mock Midtrans API
        Http::fake([
            '*/subscriptions/sub_123/disable' => Http::response([
                'status_message' => 'Subscription is updated',
            ], 200),
        ]);
        
        // 3. Cancel subscription
        $response = $this->post('/subscription/cancel');
        
        // 4. Verify redirect with success message
        $response->assertRedirect('/subscription/manage');
        $response->assertSessionHas('success');
        
        // 5. Verify subscription cancelled
        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
    }
}
```

#### Backward Compatibility Test

**File**: `tests/Feature/BackwardCompatibilityTest.php`

```php
<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BackwardCompatibilityTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_polar_subscriptions_still_work()
    {
        // Create user with Polar subscription
        $user = User::factory()->create();
        $subscription = $user->subscription()->create([
            'polar_subscription_id' => 'polar_123',
            'polar_customer_id' => 'cus_123',
            'provider' => 'polar',
            'plan_name' => 'standard',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);
        
        $this->actingAs($user);
        
        // Verify user can access features
        $this->assertTrue($user->hasActiveSubscription());
        
        // Verify subscription shows in manage page
        $response = $this->get('/subscription/manage');
        $response->assertStatus(200);
        $response->assertSee('standard');
        $response->assertSee('active');
    }
    
    public function test_system_handles_both_providers()
    {
        // Create users with different providers
        $polarUser = User::factory()->create();
        $polarUser->subscription()->create([
            'provider' => 'polar',
            'status' => 'active',
        ]);
        
        $midtransUser = User::factory()->create();
        $midtransUser->subscription()->create([
            'provider' => 'midtrans',
            'status' => 'active',
        ]);
        
        // Both should have active subscriptions
        $this->assertTrue($polarUser->hasActiveSubscription());
        $this->assertTrue($midtransUser->hasActiveSubscription());
    }
}
```


## Property-Based Testing

### Property Test Examples

Property-based tests verify that certain properties hold true for all possible inputs.

**File**: `tests/Unit/Properties/SubscriptionPropertiesTest.php`

```php
<?php

namespace Tests\Unit\Properties;

use Tests\TestCase;
use App\Models\User;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubscriptionPropertiesTest extends TestCase
{
    use RefreshDatabase;
    
    /**
     * Property: Subscription creation is idempotent
     * 
     * For any user and plan, creating a subscription multiple times
     * should not create duplicate subscriptions.
     */
    public function test_subscription_creation_is_idempotent()
    {
        $user = User::factory()->create();
        
        // Create subscription twice
        $sub1 = Subscription::create([
            'user_id' => $user->id,
            'midtrans_subscription_id' => 'sub_123',
            'provider' => 'midtrans',
            'plan_name' => 'standard',
            'status' => 'active',
        ]);
        
        // Attempt to create duplicate
        $existingCount = Subscription::where('user_id', $user->id)->count();
        
        // Should only have one subscription
        $this->assertEquals(1, $existingCount);
    }
    
    /**
     * Property: Webhook signature validation is always correct
     * 
     * Valid signatures should always pass, invalid should always fail.
     */
    public function test_webhook_signature_validation_is_correct()
    {
        $payload = [
            'order_id' => 'sub_123_1',
            'status_code' => '200',
            'gross_amount' => '99000.00',
        ];
        
        $serverKey = config('midtrans.server_key');
        
        // Generate valid signature
        $validSignature = hash('sha512',
            $payload['order_id'] .
            $payload['status_code'] .
            $payload['gross_amount'] .
            $serverKey
        );
        
        $payload['signature_key'] = $validSignature;
        
        // Valid signature should pass
        $response = $this->postJson('/api/webhooks/midtrans', $payload);
        $this->assertEquals(200, $response->status());
        
        // Invalid signature should fail
        $payload['signature_key'] = 'invalid_signature';
        $response = $this->postJson('/api/webhooks/midtrans', $payload);
        $this->assertEquals(403, $response->status());
    }
    
    /**
     * Property: Subscription status consistency
     * 
     * After processing a webhook, database status must match webhook status.
     */
    public function test_subscription_status_consistency()
    {
        $subscription = Subscription::factory()->create([
            'midtrans_subscription_id' => 'sub_123',
            'status' => 'pending',
        ]);
        
        $statuses = ['settlement', 'pending', 'failure', 'cancel'];
        
        foreach ($statuses as $webhookStatus) {
            $payload = [
                'order_id' => 'sub_123_1',
                'transaction_status' => $webhookStatus,
                'status_code' => '200',
                'gross_amount' => '99000.00',
            ];
            
            $signature = hash('sha512',
                $payload['order_id'] .
                $payload['status_code'] .
                $payload['gross_amount'] .
                config('midtrans.server_key')
            );
            $payload['signature_key'] = $signature;
            
            $this->postJson('/api/webhooks/midtrans', $payload);
            
            $subscription->refresh();
            
            // Verify status mapping is consistent
            $expectedStatus = match($webhookStatus) {
                'settlement' => 'active',
                'pending' => 'pending',
                'failure', 'cancel' => 'cancelled',
            };
            
            $this->assertEquals($expectedStatus, $subscription->status);
        }
    }
    
    /**
     * Property: Trial period calculation is always non-negative
     * 
     * Trial days remaining should never be negative.
     */
    public function test_trial_period_calculation_is_non_negative()
    {
        $service = app(SubscriptionService::class);
        
        // Test various creation dates
        $daysAgo = [0, 5, 10, 14, 20, 30];
        
        foreach ($daysAgo as $days) {
            $user = User::factory()->create([
                'created_at' => now()->subDays($days),
            ]);
            
            $trialDays = $service->calculateTrialDaysRemaining($user);
            
            // Should always be >= 0
            $this->assertGreaterThanOrEqual(0, $trialDays);
            
            // Should be correct calculation
            $expected = max(0, 14 - $days);
            $this->assertEquals($expected, $trialDays);
        }
    }
    
    /**
     * Property: Feature access respects subscription tier
     * 
     * Users can only access features in their plan or lower.
     */
    public function test_feature_access_respects_tier()
    {
        $service = app(SubscriptionService::class);
        
        // Standard user
        $standardUser = User::factory()->create();
        $standardUser->subscription()->create([
            'plan_name' => 'standard',
            'status' => 'active',
            'provider' => 'midtrans',
        ]);
        
        // Pro user
        $proUser = User::factory()->create();
        $proUser->subscription()->create([
            'plan_name' => 'pro',
            'status' => 'active',
            'provider' => 'midtrans',
        ]);
        
        // Standard features accessible to both
        $this->assertTrue($service->canAccessFeature($standardUser, 'ai_agent'));
        $this->assertTrue($service->canAccessFeature($proUser, 'ai_agent'));
        
        // Pro features only accessible to pro
        $this->assertFalse($service->canAccessFeature($standardUser, 'api_access'));
        $this->assertTrue($service->canAccessFeature($proUser, 'api_access'));
    }
}
```


## Manual Testing

### Sandbox Testing Setup

1. **Create Midtrans Sandbox Account**:
   - Visit https://dashboard.sandbox.midtrans.com/
   - Sign up for a free account
   - Navigate to Settings → Access Keys
   - Copy Server Key and Client Key

2. **Configure Application**:
   ```env
   MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
   MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
   MIDTRANS_IS_PRODUCTION=false
   ```

3. **Setup Webhook URL**:
   - Use ngrok for local testing: `ngrok http 8000`
   - Configure webhook URL in Midtrans dashboard:
     - Payment Notification: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans`
     - Recurring Notification: `https://your-ngrok-url.ngrok.io/api/webhooks/midtrans`

### Test Scenarios

#### Scenario 1: Successful Subscription Creation

**Steps**:
1. Navigate to landing page
2. Click "Subscribe" on Standard plan
3. Fill in payment details:
   - Card: 4811 1111 1111 1114
   - CVV: 123
   - Expiry: 12/25
4. Complete payment
5. Verify redirect to success page
6. Check database for subscription record
7. Verify webhook received and processed

**Expected Results**:
- ✅ Subscription created in database
- ✅ Status: active
- ✅ Period dates set correctly
- ✅ Webhook logged
- ✅ User can access premium features

#### Scenario 2: Failed Payment

**Steps**:
1. Navigate to landing page
2. Click "Subscribe" on Pro plan
3. Fill in payment details:
   - Card: 4911 1111 1111 1113 (test failure card)
   - CVV: 123
   - Expiry: 12/25
4. Attempt payment

**Expected Results**:
- ✅ Payment fails
- ✅ User redirected to error page
- ✅ Subscription status: cancelled or not created
- ✅ Error logged
- ✅ User cannot access premium features

#### Scenario 3: Subscription Cancellation

**Steps**:
1. Create active subscription (use Scenario 1)
2. Navigate to /subscription/manage
3. Click "Cancel Subscription"
4. Confirm cancellation
5. Verify status update

**Expected Results**:
- ✅ Subscription status: cancelled
- ✅ cancelled_at timestamp set
- ✅ User still has access until period end
- ✅ Success message displayed
- ✅ Midtrans API called successfully

#### Scenario 4: Recurring Payment

**Steps**:
1. Create subscription with short interval (for testing)
2. Wait for recurring payment date
3. Monitor webhook endpoint
4. Verify subscription renewal

**Expected Results**:
- ✅ Recurring webhook received
- ✅ Subscription period extended
- ✅ Payment logged
- ✅ User maintains access

#### Scenario 5: Webhook Signature Validation

**Steps**:
1. Send webhook with valid signature
2. Send webhook with invalid signature
3. Check logs and responses

**Expected Results**:
- ✅ Valid signature: 200 OK, processed
- ✅ Invalid signature: 403 Forbidden, rejected
- ✅ Both logged appropriately

#### Scenario 6: Backward Compatibility

**Steps**:
1. Create user with Polar subscription
2. Verify they can still access features
3. Create new user with Midtrans subscription
4. Verify both work simultaneously

**Expected Results**:
- ✅ Polar subscriptions still functional
- ✅ Midtrans subscriptions work
- ✅ No conflicts between providers
- ✅ Feature access works for both

### Test Cards

Midtrans provides test cards for different scenarios:

| Card Number | Scenario | Expected Result |
|------------|----------|-----------------|
| 4811 1111 1111 1114 | Success | Payment successful |
| 4911 1111 1111 1113 | Failure | Payment failed |
| 4011 1111 1111 1112 | Challenge | 3DS authentication required |
| 4611 1111 1111 1119 | Pending | Payment pending |

### Manual Testing Checklist

#### Pre-Deployment Testing

- [ ] Subscription creation (Standard plan)
- [ ] Subscription creation (Pro plan)
- [ ] Payment success flow
- [ ] Payment failure flow
- [ ] Payment cancellation flow
- [ ] Webhook signature validation
- [ ] Webhook idempotency
- [ ] Subscription cancellation
- [ ] Feature access control
- [ ] Trial period calculation
- [ ] Backward compatibility with Polar
- [ ] Error handling and messages
- [ ] Responsive design (mobile/tablet)
- [ ] Browser compatibility (Chrome, Firefox, Safari)

#### Post-Deployment Testing

- [ ] Production webhook delivery
- [ ] Real payment processing
- [ ] Recurring payment (wait 1 month)
- [ ] Monitoring and alerting
- [ ] Log aggregation
- [ ] Performance under load
- [ ] Database backup and recovery


## Load Testing

### Load Testing Setup

Use Apache Bench or Artillery for load testing:

```bash
# Install Artillery
npm install -g artillery

# Create load test config
cat > load-test.yml << EOF
config:
  target: 'https://your-domain.com'
  phases:
    - duration: 60
      arrivalRate: 10
      name: "Warm up"
    - duration: 120
      arrivalRate: 50
      name: "Sustained load"
    - duration: 60
      arrivalRate: 100
      name: "Peak load"
scenarios:
  - name: "Subscription creation"
    flow:
      - post:
          url: "/subscription/checkout"
          json:
            plan_id: "standard"
            payment_token: "test_token"
  - name: "Webhook processing"
    flow:
      - post:
          url: "/api/webhooks/midtrans"
          json:
            order_id: "sub_test_1"
            transaction_status: "settlement"
            status_code: "200"
            gross_amount: "99000.00"
            signature_key: "valid_signature"
EOF

# Run load test
artillery run load-test.yml
```

### Performance Benchmarks

Target performance metrics:

| Metric | Target | Acceptable | Critical |
|--------|--------|------------|----------|
| Subscription creation | < 2s | < 3s | > 5s |
| Webhook processing | < 1s | < 2s | > 3s |
| Page load time | < 1s | < 2s | > 3s |
| API response time (p95) | < 500ms | < 1s | > 2s |
| Database query time | < 100ms | < 200ms | > 500ms |

## Continuous Integration

### GitHub Actions Workflow

**File**: `.github/workflows/tests.yml`

```yaml
name: Tests

on:
  push:
    branches: [ main, develop ]
  pull_request:
    branches: [ main, develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: password
          MYSQL_DATABASE: test_db
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
      
      redis:
        image: redis:7
        ports:
          - 6379:6379
        options: --health-cmd="redis-cli ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, xml, ctype, json, mysql, redis
          coverage: xdebug
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Copy .env
        run: cp .env.example .env
      
      - name: Generate key
        run: php artisan key:generate
      
      - name: Run migrations
        run: php artisan migrate --force
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: test_db
          DB_USERNAME: root
          DB_PASSWORD: password
      
      - name: Run tests
        run: php artisan test --coverage --min=80
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: test_db
          DB_USERNAME: root
          DB_PASSWORD: password
          REDIS_HOST: 127.0.0.1
          REDIS_PORT: 6379
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

## Test Data Management

### Test Factories

**File**: `database/factories/SubscriptionFactory.php`

```php
<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;
    
    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'midtrans_subscription_id' => 'sub_' . $this->faker->uuid(),
            'midtrans_customer_id' => 'cus_' . $this->faker->uuid(),
            'provider' => 'midtrans',
            'plan_name' => $this->faker->randomElement(['standard', 'pro']),
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'metadata' => [],
        ];
    }
    
    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
    
    public function cancelled()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
    
    public function polar()
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'polar',
            'polar_subscription_id' => 'polar_' . $this->faker->uuid(),
            'polar_customer_id' => 'cus_' . $this->faker->uuid(),
            'midtrans_subscription_id' => null,
            'midtrans_customer_id' => null,
        ]);
    }
}
```

### Test Seeders

**File**: `database/seeders/TestSubscriptionSeeder.php`

```php
<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Subscription;
use Illuminate\Database\Seeder;

class TestSubscriptionSeeder extends Seeder
{
    public function run()
    {
        // Create users with different subscription states
        
        // Active Midtrans subscription
        $user1 = User::factory()->create(['email' => 'active@test.com']);
        Subscription::factory()->create([
            'user_id' => $user1->id,
            'status' => 'active',
        ]);
        
        // Cancelled subscription
        $user2 = User::factory()->create(['email' => 'cancelled@test.com']);
        Subscription::factory()->cancelled()->create([
            'user_id' => $user2->id,
        ]);
        
        // Polar subscription (backward compatibility)
        $user3 = User::factory()->create(['email' => 'polar@test.com']);
        Subscription::factory()->polar()->create([
            'user_id' => $user3->id,
        ]);
        
        // Trial user (no subscription)
        User::factory()->create(['email' => 'trial@test.com']);
    }
}
```

## Debugging Tests

### Enable Verbose Output

```bash
# Run tests with verbose output
php artisan test --verbose

# Run specific test with debugging
php artisan test --filter test_creates_subscription --stop-on-failure
```

### Database Inspection

```php
// In test, dump database state
public function test_something()
{
    // ... test code ...
    
    // Dump all subscriptions
    dd(Subscription::all()->toArray());
    
    // Dump specific subscription
    dd($subscription->toArray());
}
```

### HTTP Request Inspection

```php
// Inspect HTTP requests in tests
Http::fake();

// ... make requests ...

// Dump all requests
Http::assertSentCount(2);
Http::recorded(function ($request, $response) {
    dump($request->url());
    dump($request->body());
    dump($response->status());
});
```

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Use Factories**: Generate test data with factories
3. **Mock External APIs**: Don't call real APIs in tests
4. **Test Edge Cases**: Test boundary conditions
5. **Meaningful Assertions**: Assert specific values, not just existence
6. **Clean Up**: Use RefreshDatabase trait
7. **Fast Tests**: Keep unit tests fast (< 100ms each)
8. **Descriptive Names**: Use clear test method names
9. **One Assertion Per Test**: Focus on one thing
10. **Test Failures**: Test both success and failure paths

## References

- [Laravel Testing Documentation](https://laravel.com/docs/testing)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [Midtrans Testing Guide](https://docs.midtrans.com/en/technical-reference/sandbox-test)
- [Property-Based Testing](https://hypothesis.works/articles/what-is-property-based-testing/)
