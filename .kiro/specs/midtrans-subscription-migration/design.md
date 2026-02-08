# Design: Migrasi Subscription dari Polar ke Midtrans

## 1. Architecture Overview

### 1.1 High-Level Architecture

```
┌─────────────────┐
│  Landing Page   │
│   (Blade View)  │
└────────┬────────┘
         │ User clicks "Subscribe"
         ▼
┌─────────────────────────────┐
│ SubscriptionController      │
│ - createCheckout()          │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│ MidtransSubscriptionService │
│ - createSubscription()      │
│ - getSubscription()         │
│ - cancelSubscription()      │
└────────┬────────────────────┘
         │ HTTP Request
         ▼
┌─────────────────────────────┐
│   Midtrans Subscription     │
│         API                 │
└────────┬────────────────────┘
         │ Webhook
         ▼
┌─────────────────────────────┐
│ MidtransWebhookController   │
│ - handleSubscriptionWebhook()│
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────┐
│   SubscriptionService       │
│ - processWebhookEvent()     │
│ - updateSubscriptionStatus()│
└─────────────────────────────┘
```

### 1.2 Component Responsibilities

**MidtransSubscriptionService**
- Create subscription via Midtrans API
- Retrieve subscription details
- Cancel/disable subscription
- Update subscription
- Handle API communication

**SubscriptionService** (Enhanced)
- Business logic for subscription management
- Process webhook events
- Update subscription status
- Calculate trial periods
- Feature access control

**MidtransWebhookController**
- Receive webhook notifications
- Validate webhook signatures
- Route events to appropriate handlers
- Return proper HTTP responses

**SubscriptionController**
- Handle user subscription requests
- Create checkout sessions
- Redirect to payment pages
- Handle success/cancel callbacks

## 2. Database Schema

### 2.1 Subscriptions Table (Enhanced)

```sql
-- Add new columns to existing subscriptions table
ALTER TABLE subscriptions ADD COLUMN midtrans_subscription_id VARCHAR(255) NULL;
ALTER TABLE subscriptions ADD COLUMN midtrans_customer_id VARCHAR(255) NULL;
ALTER TABLE subscriptions ADD COLUMN provider VARCHAR(50) DEFAULT 'polar';
ALTER TABLE subscriptions ADD COLUMN metadata JSON NULL;

-- Add index for faster lookups
CREATE INDEX idx_subscriptions_midtrans_id ON subscriptions(midtrans_subscription_id);
CREATE INDEX idx_subscriptions_provider ON subscriptions(provider);
```

**Columns**:
- `id`: Primary key
- `user_id`: Foreign key to users table
- `polar_subscription_id`: Polar subscription ID (existing, nullable)
- `polar_customer_id`: Polar customer ID (existing, nullable)
- `midtrans_subscription_id`: Midtrans subscription ID (new, nullable)
- `midtrans_customer_id`: Midtrans customer ID (new, nullable)
- `provider`: 'polar' or 'midtrans' (new)
- `plan_name`: Plan identifier (standard, pro)
- `status`: Subscription status (active, cancelled, expired)
- `current_period_start`: Period start date
- `current_period_end`: Period end date
- `cancelled_at`: Cancellation timestamp
- `metadata`: Additional data as JSON (new)
- `created_at`: Creation timestamp
- `updated_at`: Update timestamp

### 2.2 Subscription Plans Configuration

Plans will be stored in config file (`config/subscription.php`):

```php
'plans' => [
    'standard' => [
        'id' => 'standard',
        'name' => 'Standard',
        'price' => 99000,
        'interval' => 'month',
        'interval_count' => 1,
        'currency' => 'IDR',
        'features' => [...]
    ],
    'pro' => [
        'id' => 'pro',
        'name' => 'Pro',
        'price' => 199000,
        'interval' => 'month',
        'interval_count' => 1,
        'currency' => 'IDR',
        'features' => [...]
    ]
]
```

## 3. API Integration

### 3.1 Midtrans Subscription API

**Base URLs**:
- Production: `https://api.midtrans.com/v1`
- Sandbox: `https://api.sandbox.midtrans.com/v1`

**Authentication**: Basic Auth with Server Key

### 3.2 Create Subscription Endpoint

**Request**:
```http
POST /v1/subscriptions
Authorization: Basic base64(server_key:)
Content-Type: application/json

{
  "name": "Standard Monthly Subscription",
  "amount": "99000",
  "currency": "IDR",
  "payment_type": "credit_card",
  "token": "customer_token",
  "schedule": {
    "interval": 1,
    "interval_unit": "month",
    "max_interval": 12,
    "start_time": "2026-01-24 07:00:00 +0700"
  },
  "metadata": {
    "user_id": "123",
    "plan_id": "standard"
  },
  "customer_details": {
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "phone": "+628123456789"
  }
}
```

**Response**:
```json
{
  "id": "sub_123456",
  "name": "Standard Monthly Subscription",
  "amount": "99000",
  "currency": "IDR",
  "status": "active",
  "token": "customer_token",
  "schedule": {...},
  "created_at": "2026-01-24T00:00:00Z"
}
```

### 3.3 Get Subscription Endpoint

**Request**:
```http
GET /v1/subscriptions/{subscription_id}
Authorization: Basic base64(server_key:)
```

**Response**:
```json
{
  "id": "sub_123456",
  "status": "active",
  "amount": "99000",
  "currency": "IDR",
  "schedule": {...},
  "customer_details": {...}
}
```

### 3.4 Disable/Cancel Subscription

**Request**:
```http
POST /v1/subscriptions/{subscription_id}/disable
Authorization: Basic base64(server_key:)
```

**Response**:
```json
{
  "status_message": "Subscription is updated.",
  "status_code": "200"
}
```

## 4. Service Layer Design

### 4.1 MidtransSubscriptionService

**File**: `app/Services/MidtransSubscriptionService.php`

```php
class MidtransSubscriptionService
{
    private string $baseUrl;
    private string $serverKey;
    
    public function __construct()
    {
        $this->serverKey = config('midtrans.server_key');
        $this->baseUrl = config('midtrans.is_production')
            ? 'https://api.midtrans.com/v1'
            : 'https://api.sandbox.midtrans.com/v1';
    }
    
    /**
     * Create a new subscription
     */
    public function createSubscription(
        User $user,
        string $planId,
        string $paymentToken
    ): array;
    
    /**
     * Get subscription details
     */
    public function getSubscription(string $subscriptionId): ?array;
    
    /**
     * Cancel/disable subscription
     */
    public function cancelSubscription(string $subscriptionId): bool;
    
    /**
     * Update subscription
     */
    public function updateSubscription(
        string $subscriptionId,
        array $data
    ): array;
    
    /**
     * Enable subscription
     */
    public function enableSubscription(string $subscriptionId): bool;
}
```

### 4.2 Enhanced SubscriptionService

**File**: `app/Services/SubscriptionService.php` (Enhanced)

```php
class SubscriptionService
{
    // Existing methods...
    
    /**
     * Process Midtrans webhook event
     */
    public function processMidtransWebhook(array $event): void;
    
    /**
     * Create subscription from Midtrans data
     */
    public function createMidtransSubscription(
        User $user,
        array $midtransData
    ): Subscription;
    
    /**
     * Update subscription from Midtrans webhook
     */
    public function updateFromMidtransWebhook(
        string $subscriptionId,
        array $data
    ): void;
}
```

## 5. Controller Design

### 5.1 SubscriptionController

**File**: `app/Http/Controllers/SubscriptionController.php`

```php
class SubscriptionController extends Controller
{
    public function __construct(
        private MidtransSubscriptionService $midtransService,
        private SubscriptionService $subscriptionService
    ) {}
    
    /**
     * Show pricing page
     */
    public function index(): View;
    
    /**
     * Create checkout session
     */
    public function createCheckout(Request $request): RedirectResponse;
    
    /**
     * Handle successful payment
     */
    public function success(Request $request): RedirectResponse;
    
    /**
     * Handle cancelled payment
     */
    public function cancel(Request $request): RedirectResponse;
    
    /**
     * Handle payment error
     */
    public function error(Request $request): RedirectResponse;
    
    /**
     * Show subscription management page
     */
    public function manage(): View;
    
    /**
     * Cancel subscription
     */
    public function cancelSubscription(Request $request): RedirectResponse;
}
```

### 5.2 MidtransWebhookController

**File**: `app/Http/Controllers/Api/MidtransWebhookController.php` (Enhanced)

```php
class MidtransWebhookController extends Controller
{
    /**
     * Handle subscription webhook
     */
    public function handleSubscriptionWebhook(Request $request): JsonResponse
    {
        // 1. Validate signature
        // 2. Parse payload
        // 3. Process event
        // 4. Return response
    }
    
    /**
     * Validate webhook signature
     */
    private function validateSignature(array $payload): bool;
    
    /**
     * Process subscription event
     */
    private function processSubscriptionEvent(array $payload): void;
}
```

## 6. Webhook Integration

### 6.1 Webhook Events

**Payment Notification** (First payment):
```json
{
  "transaction_time": "2026-01-24 00:00:00",
  "transaction_status": "settlement",
  "transaction_id": "txn_123",
  "status_message": "Success",
  "status_code": "200",
  "signature_key": "...",
  "payment_type": "credit_card",
  "order_id": "sub_123456_1",
  "merchant_id": "M123",
  "gross_amount": "99000.00",
  "fraud_status": "accept",
  "currency": "IDR"
}
```

**Recurring Notification** (Subsequent payments):
```json
{
  "transaction_time": "2026-02-24 00:00:00",
  "transaction_status": "settlement",
  "subscription_id": "sub_123456",
  "transaction_id": "txn_124",
  "status_code": "200",
  "order_id": "sub_123456_2",
  "gross_amount": "99000.00"
}
```

### 6.2 Signature Validation

```php
private function validateSignature(array $payload): bool
{
    $serverKey = config('midtrans.server_key');
    $orderId = $payload['order_id'] ?? '';
    $statusCode = $payload['status_code'] ?? '';
    $grossAmount = $payload['gross_amount'] ?? '';
    
    $signatureString = $orderId . $statusCode . $grossAmount . $serverKey;
    $expectedSignature = hash('sha512', $signatureString);
    
    return hash_equals(
        $expectedSignature,
        $payload['signature_key'] ?? ''
    );
}
```

## 7. Frontend Integration

### 7.1 Landing Page Updates

**File**: `resources/views/welcome.blade.php`

Update pricing section to use Midtrans:

```blade
<!-- Pricing Section -->
<section id="pricing">
    @foreach($plans as $plan)
    <div class="plan-card">
        <h3>{{ $plan['name'] }}</h3>
        <p class="price">Rp {{ number_format($plan['price']) }}/bulan</p>
        
        @auth
            <form action="{{ route('subscription.checkout') }}" method="POST">
                @csrf
                <input type="hidden" name="plan_id" value="{{ $plan['id'] }}">
                <button type="submit">Berlangganan</button>
            </form>
        @else
            <a href="{{ route('login') }}">Login untuk Berlangganan</a>
        @endauth
    </div>
    @endforeach
</section>
```

### 7.2 Payment Flow

1. User clicks "Berlangganan" button
2. POST to `/subscription/checkout` with plan_id
3. Backend creates Midtrans subscription
4. Redirect to Midtrans payment page
5. User completes payment
6. Midtrans redirects to success/cancel URL
7. Webhook updates subscription status

## 8. Configuration

### 8.1 Environment Variables

```env
# Midtrans Subscription
MIDTRANS_SERVER_KEY=your_server_key
MIDTRANS_CLIENT_KEY=your_client_key
MIDTRANS_IS_PRODUCTION=false

# Subscription URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"

# Webhook URL (configure in Midtrans dashboard)
# Payment Notification: ${APP_URL}/api/webhooks/midtrans
# Recurring Notification: ${APP_URL}/api/webhooks/midtrans
# Pay Account Notification: ${APP_URL}/api/webhooks/midtrans
```

### 8.2 Config File

**File**: `config/subscription.php` (New)

```php
return [
    'provider' => env('SUBSCRIPTION_PROVIDER', 'midtrans'),
    
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    ],
    
    'urls' => [
        'success' => env('MIDTRANS_SUBSCRIPTION_SUCCESS_URL'),
        'cancel' => env('MIDTRANS_SUBSCRIPTION_CANCEL_URL'),
        'error' => env('MIDTRANS_SUBSCRIPTION_ERROR_URL'),
    ],
    
    'plans' => [
        'standard' => [
            'id' => 'standard',
            'name' => 'Standard',
            'price' => 99000,
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price' => 199000,
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
        ],
    ],
];
```

## 9. Routes

**File**: `routes/web.php`

```php
// Subscription routes
Route::middleware(['auth'])->group(function () {
    Route::get('/pricing', [SubscriptionController::class, 'index'])
        ->name('subscription.pricing');
    Route::post('/subscription/checkout', [SubscriptionController::class, 'createCheckout'])
        ->name('subscription.checkout');
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])
        ->name('subscription.success');
    Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');
    Route::get('/subscription/error', [SubscriptionController::class, 'error'])
        ->name('subscription.error');
    Route::get('/subscription/manage', [SubscriptionController::class, 'manage'])
        ->name('subscription.manage');
    Route::post('/subscription/cancel', [SubscriptionController::class, 'cancelSubscription'])
        ->name('subscription.cancel.post');
});
```

**File**: `routes/api.php`

```php
// Webhook routes (no auth middleware)
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleSubscriptionWebhook'])
    ->name('webhooks.midtrans.subscription');
```

## 10. Error Handling

### 10.1 API Errors

```php
try {
    $subscription = $this->midtransService->createSubscription($user, $planId, $token);
} catch (MidtransApiException $e) {
    Log::error('Midtrans API error', [
        'error' => $e->getMessage(),
        'user_id' => $user->id,
        'plan_id' => $planId,
    ]);
    
    return redirect()->back()->with('error', 'Gagal membuat subscription. Silakan coba lagi.');
}
```

### 10.2 Webhook Errors

```php
try {
    $this->subscriptionService->processMidtransWebhook($payload);
} catch (\Exception $e) {
    Log::error('Webhook processing error', [
        'error' => $e->getMessage(),
        'payload' => $payload,
    ]);
    
    // Still return 200 to prevent retries
    return response()->json(['status' => 'ok'], 200);
}
```

## 11. Testing Strategy

### 11.1 Unit Tests

- `MidtransSubscriptionServiceTest`: Test API integration
- `SubscriptionServiceTest`: Test business logic
- `MidtransWebhookControllerTest`: Test webhook handling

### 11.2 Integration Tests

- End-to-end subscription flow
- Webhook processing
- Payment success/failure scenarios

### 11.3 Manual Testing

- Test with Midtrans sandbox
- Verify webhook delivery
- Test all payment methods
- Test subscription cancellation

## 12. Migration Strategy

### 12.1 Phase 1: Setup (Week 1)
- Create migration for database changes
- Add configuration files
- Setup Midtrans sandbox account

### 12.2 Phase 2: Implementation (Week 2-3)
- Implement MidtransSubscriptionService
- Update SubscriptionService
- Create controllers
- Update views

### 12.3 Phase 3: Testing (Week 4)
- Unit tests
- Integration tests
- Manual testing with sandbox

### 12.4 Phase 4: Deployment (Week 5)
- Deploy to staging
- Configure production Midtrans account
- Setup webhook URLs
- Deploy to production
- Monitor for issues

## 13. Rollback Plan

If issues occur:
1. Revert code changes
2. Keep database changes (backward compatible)
3. Existing Polar subscriptions continue working
4. New subscriptions temporarily disabled

## 14. Monitoring and Logging

### 14.1 Metrics to Monitor
- Subscription creation success rate
- Webhook processing time
- Failed payments
- Subscription cancellations

### 14.2 Logging
- All API calls to Midtrans
- All webhook events
- All errors and exceptions
- Subscription status changes

## 15. Security Considerations

### 15.1 Webhook Security
- Always validate signature
- Use HTTPS for webhook endpoint
- Rate limiting on webhook endpoint
- Log suspicious requests

### 15.2 Credentials Security
- Store credentials in environment variables
- Never commit credentials to git
- Use different keys for sandbox/production
- Rotate keys periodically

## 16. Performance Optimization

### 16.1 Caching
- Cache subscription plans
- Cache user subscription status (with TTL)

### 16.2 Async Processing
- Process webhooks asynchronously using queues
- Send notifications asynchronously

## 17. Backward Compatibility

### 17.1 Polar Integration
- Keep existing PolarService
- Keep existing Polar webhook handler
- Subscription model supports both providers
- Feature access control works for both

### 17.2 Data Migration
- No automatic migration from Polar to Midtrans
- Users with active Polar subscriptions continue using Polar
- New users use Midtrans
- Manual migration tool for support team (optional)

## 18. Future Enhancements

### 18.1 Phase 2 Features
- Annual subscription option
- Proration for plan changes
- Dunning management for failed payments
- Invoice generation
- Email notifications

### 18.2 Phase 3 Features
- Multiple payment methods selection
- Subscription analytics dashboard
- Referral program
- Discount codes/coupons

## 19. Correctness Properties

### Property 1: Subscription Creation Idempotency
**Validates: Requirements 3.2, 3.3**

For any user and plan combination, creating a subscription multiple times with the same parameters should not create duplicate subscriptions.

```php
// Property test
function test_subscription_creation_is_idempotent()
{
    $user = User::factory()->create();
    $planId = 'standard';
    
    // Create subscription twice
    $sub1 = $this->service->createSubscription($user, $planId, 'token1');
    $sub2 = $this->service->createSubscription($user, $planId, 'token1');
    
    // Should return same subscription or handle gracefully
    $this->assertEquals($sub1->id, $sub2->id);
}
```

### Property 2: Webhook Signature Validation
**Validates: Requirements 3.4, NFR-4.1**

All webhook requests must have valid signatures, and invalid signatures must be rejected.

```php
// Property test
function test_webhook_signature_validation_always_correct()
{
    $payload = ['order_id' => 'test', 'status_code' => '200', 'gross_amount' => '99000'];
    
    // Valid signature should pass
    $validSignature = $this->generateValidSignature($payload);
    $this->assertTrue($this->controller->validateSignature($payload, $validSignature));
    
    // Invalid signature should fail
    $invalidSignature = 'invalid_signature';
    $this->assertFalse($this->controller->validateSignature($payload, $invalidSignature));
}
```

### Property 3: Subscription Status Consistency
**Validates: Requirements 3.3, 3.4**

After processing a webhook event, the subscription status in the database must match the status from Midtrans.

```php
// Property test
function test_subscription_status_consistency_after_webhook()
{
    $subscription = Subscription::factory()->create(['status' => 'active']);
    
    // Process webhook with new status
    $webhook = ['subscription_id' => $subscription->midtrans_subscription_id, 'status' => 'cancelled'];
    $this->service->processMidtransWebhook($webhook);
    
    // Database status should match webhook status
    $subscription->refresh();
    $this->assertEquals('cancelled', $subscription->status);
}
```

### Property 4: Trial Period Calculation
**Validates: Requirements 3.6**

Trial days remaining should always be non-negative and decrease by 1 each day.

```php
// Property test
function test_trial_period_calculation_is_correct()
{
    $user = User::factory()->create(['created_at' => now()->subDays(5)]);
    
    $trialDays = $this->service->calculateTrialDaysRemaining($user);
    
    // Should be 14 - 5 = 9 days
    $this->assertEquals(9, $trialDays);
    $this->assertGreaterThanOrEqual(0, $trialDays);
}
```

### Property 5: Feature Access Control
**Validates: Requirements 3.3, NFR-4.1**

Users should only access features allowed by their subscription tier.

```php
// Property test
function test_feature_access_respects_subscription_tier()
{
    $standardUser = User::factory()->create();
    $standardUser->subscription()->create(['plan_name' => 'standard', 'status' => 'active']);
    
    $proUser = User::factory()->create();
    $proUser->subscription()->create(['plan_name' => 'pro', 'status' => 'active']);
    
    // Standard user cannot access pro features
    $this->assertFalse($this->service->canAccessFeature($standardUser, 'pro'));
    
    // Pro user can access standard features
    $this->assertTrue($this->service->canAccessFeature($proUser, 'standard'));
}
```

## 20. Implementation Checklist

- [ ] Database migration
- [ ] MidtransSubscriptionService implementation
- [ ] SubscriptionService enhancements
- [ ] SubscriptionController implementation
- [ ] MidtransWebhookController enhancements
- [ ] Routes configuration
- [ ] Config files
- [ ] View updates (landing page)
- [ ] Unit tests
- [ ] Integration tests
- [ ] Property-based tests
- [ ] Documentation
- [ ] Midtrans sandbox setup
- [ ] Webhook URL configuration
- [ ] Staging deployment
- [ ] Production deployment
- [ ] Monitoring setup
