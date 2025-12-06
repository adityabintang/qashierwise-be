# Design Document: Polar.sh Subscription Integration

## Overview

This design document outlines the technical architecture for integrating Polar.sh subscription management into QashierWise. The integration enables subscription-based billing for three tiers (Free Trial, Standard, Pro) with real-time status detection in the dashboard. The implementation follows Test-Driven Development (TDD) principles using PHPUnit and Eris for property-based testing.

## Architecture

```mermaid
graph TB
    subgraph "Frontend Layer"
        WP[Welcome Page<br/>Pricing Section]
        DB[Dashboard<br/>Subscription Status]
    end
    
    subgraph "API Layer"
        SC[SubscriptionController]
        WC[WebhookController]
        API[/api/subscription/*]
    end
    
    subgraph "Service Layer"
        SS[SubscriptionService]
        PS[PolarService]
        PC[PlanConfig]
    end
    
    subgraph "Data Layer"
        UM[User Model]
        SM[Subscription Model]
        MIG[Migrations]
    end
    
    subgraph "External"
        POLAR[Polar.sh API]
    end
    
    WP -->|Checkout Request| SC
    DB -->|Status Request| SC
    SC --> SS
    SS --> PS
    SS --> PC
    PS -->|API Calls| POLAR
    POLAR -->|Webhooks| WC
    WC --> SS
    SS --> SM
    SM --> UM
```

## Components and Interfaces

### 1. PolarService (app/Services/PolarService.php)

Handles all communication with Polar.sh API.

```php
interface PolarServiceInterface
{
    public function createCheckoutSession(User $user, string $planId): CheckoutSession;
    public function getCustomerPortalUrl(string $customerId): string;
    public function validateWebhookSignature(string $payload, string $signature): bool;
    public function getSubscription(string $subscriptionId): ?PolarSubscription;
}
```

### 2. SubscriptionService (app/Services/SubscriptionService.php)

Core business logic for subscription management.

```php
interface SubscriptionServiceInterface
{
    public function getUserSubscriptionStatus(User $user): SubscriptionStatus;
    public function processWebhookEvent(array $event): void;
    public function createOrUpdateSubscription(User $user, array $polarData): Subscription;
    public function cancelSubscription(Subscription $subscription, Carbon $cancelledAt): Subscription;
    public function calculateTrialDaysRemaining(User $user): int;
    public function canAccessFeature(User $user, string $featureTier): bool;
}
```

### 3. PlanConfig (app/Services/PlanConfig.php)

Configuration mapping for subscription plans.

```php
interface PlanConfigInterface
{
    public function getPlan(string $planId): ?PlanDetails;
    public function getAllPlans(): array;
    public function getPolarProductId(string $planId): ?string;
    public function getFeaturesByTier(string $tier): array;
}
```

### 4. SubscriptionController (app/Http/Controllers/Api/SubscriptionController.php)

API endpoints for subscription operations.

```php
class SubscriptionController
{
    public function status(Request $request): JsonResponse;
    public function createCheckout(CreateCheckoutRequest $request): JsonResponse;
    public function getPortalUrl(Request $request): JsonResponse;
}
```

### 5. WebhookController (app/Http/Controllers/Api/PolarWebhookController.php)

Handles incoming Polar.sh webhooks.

```php
class PolarWebhookController
{
    public function handle(Request $request): Response;
}
```

## Data Models

### Subscription Model

```php
// app/Models/Subscription.php
class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'polar_subscription_id',
        'polar_customer_id',
        'plan_name',
        'status',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function user(): BelongsTo;
    public function isActive(): bool;
    public function isCancelled(): bool;
    public function isExpired(): bool;
}
```

### SubscriptionStatus DTO

```php
// app/DTOs/SubscriptionStatus.php
class SubscriptionStatus
{
    public function __construct(
        public readonly string $status,      // 'trial', 'trial_expired', 'active', 'cancelled', 'expired'
        public readonly string $planName,    // 'free_trial', 'standard', 'pro'
        public readonly ?int $trialDaysRemaining,
        public readonly ?Carbon $periodEnd,
        public readonly ?Carbon $cancelledAt,
    ) {}

    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

### PlanDetails DTO

```php
// app/DTOs/PlanDetails.php
class PlanDetails
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly int $priceMonthly,
        public readonly string $polarProductId,
        public readonly array $features,
        public readonly string $tier,  // 'basic', 'standard', 'pro'
    ) {}

    public function toArray(): array;
    public static function fromArray(array $data): self;
}
```

### CheckoutSession DTO

```php
// app/DTOs/CheckoutSession.php
class CheckoutSession
{
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly string $planId,
        public readonly string $userEmail,
        public readonly string $successUrl,
        public readonly string $cancelUrl,
    ) {}
}
```

### Database Migration

```php
// database/migrations/xxxx_create_subscriptions_table.php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->onDelete('cascade');
    $table->string('polar_subscription_id')->unique();
    $table->string('polar_customer_id')->index();
    $table->string('plan_name');  // 'standard', 'pro'
    $table->string('status');     // 'active', 'cancelled', 'expired'
    $table->timestamp('current_period_start');
    $table->timestamp('current_period_end');
    $table->timestamp('cancelled_at')->nullable();
    $table->timestamps();
    
    $table->index(['user_id', 'status']);
});
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the prework analysis, the following correctness properties have been identified:

### Property 1: Plan Configuration Mapping Consistency
*For any* valid plan identifier (free_trial, standard, pro), the PlanConfig SHALL return a non-null PlanDetails object containing a valid Polar.sh product ID.
**Validates: Requirements 2.1, 2.2**

### Property 2: Plan Details Completeness
*For any* valid plan identifier, the returned PlanDetails SHALL contain all required fields: id, name, priceMonthly, polarProductId, features array, and tier.
**Validates: Requirements 2.2**

### Property 3: Checkout Session Data Integrity
*For any* checkout session creation request with a valid user and plan, the resulting CheckoutSession SHALL contain the user's email and valid success/cancel callback URLs.
**Validates: Requirements 3.4**

### Property 4: Webhook Signature Validation Correctness
*For any* webhook payload and signature pair, the signature validation SHALL return true only when the signature is cryptographically valid for the given payload and secret.
**Validates: Requirements 4.1**

### Property 5: Subscription Created Event Processing
*For any* valid subscription.created webhook event, processing SHALL result in a subscription record with status 'active' and the correct plan name.
**Validates: Requirements 4.2**

### Property 6: Subscription Updated Event Processing
*For any* valid subscription.updated webhook event, processing SHALL update the subscription record to reflect the new status and plan from the event.
**Validates: Requirements 4.3**

### Property 7: Subscription Cancelled Event Processing
*For any* valid subscription.cancelled webhook event, processing SHALL mark the subscription as cancelled and record a non-null cancellation timestamp.
**Validates: Requirements 4.4**

### Property 8: Default Trial Status for New Users
*For any* user without a subscription record and registered within 14 days, getUserSubscriptionStatus SHALL return status 'trial' with planName 'free_trial'.
**Validates: Requirements 5.2**

### Property 9: Trial Expiration Detection
*For any* user without a subscription record and registered more than 14 days ago, getUserSubscriptionStatus SHALL return status 'trial_expired'.
**Validates: Requirements 5.3**

### Property 10: Active Subscription Status
*For any* user with an active subscription, getUserSubscriptionStatus SHALL return the correct plan name and a non-null period end date.
**Validates: Requirements 5.4**

### Property 11: Cancelled But Active Subscription
*For any* subscription that is cancelled but where current date is before period_end, the subscription SHALL be considered active until the period end date.
**Validates: Requirements 5.5**

### Property 12: Trial Days Calculation
*For any* user registration date within the trial period, calculateTrialDaysRemaining SHALL return a value between 0 and 14 that equals (14 - days_since_registration).
**Validates: Requirements 6.2**

### Property 13: Subscription Persistence Completeness
*For any* subscription data persisted to the database, the record SHALL contain all required fields: polar_subscription_id, polar_customer_id, plan_name, status, current_period_start, current_period_end.
**Validates: Requirements 7.1, 7.3**

### Property 14: Subscription Serialization Round-Trip
*For any* SubscriptionStatus object, serializing to JSON and deserializing back SHALL produce an equivalent object.
**Validates: Requirements 7.4**

### Property 15: Standard Tier Feature Access
*For any* user with an active Standard or Pro subscription, canAccessFeature('standard') SHALL return true.
**Validates: Requirements 9.1**

### Property 16: Pro Tier Feature Access
*For any* user with an active Pro subscription, canAccessFeature('pro') SHALL return true, and for users without Pro subscription, it SHALL return false.
**Validates: Requirements 9.2**

### Property 17: Trial User Feature Access Restriction
*For any* user on free trial, canAccessFeature SHALL return true only for 'basic' tier features and false for 'standard' and 'pro' tier features.
**Validates: Requirements 9.4**

## Error Handling

### API Error Responses

```php
// Standard error response format
{
    "success": false,
    "error": {
        "code": "SUBSCRIPTION_ERROR",
        "message": "Human readable message"
    }
}
```

### Error Codes

| Code | Description |
|------|-------------|
| `POLAR_CONFIG_MISSING` | Polar.sh API credentials not configured |
| `CHECKOUT_FAILED` | Failed to create checkout session |
| `INVALID_PLAN` | Requested plan does not exist |
| `WEBHOOK_INVALID_SIGNATURE` | Webhook signature validation failed |
| `SUBSCRIPTION_NOT_FOUND` | User has no subscription record |

### Exception Handling Strategy

1. **PolarConfigurationException**: Thrown when Polar.sh credentials are missing
2. **CheckoutCreationException**: Thrown when checkout session creation fails
3. **WebhookValidationException**: Thrown when webhook signature is invalid
4. **SubscriptionNotFoundException**: Thrown when subscription lookup fails

All exceptions are caught at the controller level and converted to appropriate HTTP responses.

## Testing Strategy

### Dual Testing Approach

The implementation uses both unit tests and property-based tests:

1. **Unit Tests (PHPUnit)**: Verify specific examples, edge cases, and error conditions
2. **Property-Based Tests (Eris)**: Verify universal properties that should hold across all inputs

### Property-Based Testing Framework

The project uses **Eris** (giorgiosironi/eris) for property-based testing, which is already installed in the project.

### Test Configuration

Each property-based test will:
- Run a minimum of 100 iterations
- Be tagged with the property number and requirements reference
- Use smart generators that constrain to valid input spaces

### Test File Structure

```
tests/
├── Unit/
│   ├── Services/
│   │   ├── PlanConfigTest.php
│   │   ├── SubscriptionServiceTest.php
│   │   └── PolarServiceTest.php
│   ├── Models/
│   │   └── SubscriptionTest.php
│   └── DTOs/
│       ├── SubscriptionStatusTest.php
│       └── PlanDetailsTest.php
├── Feature/
│   ├── SubscriptionControllerTest.php
│   └── PolarWebhookControllerTest.php
└── Property/
    ├── PlanConfigPropertyTest.php
    ├── SubscriptionServicePropertyTest.php
    ├── SubscriptionStatusPropertyTest.php
    └── FeatureAccessPropertyTest.php
```

### Example Property Test Structure

```php
// tests/Property/SubscriptionServicePropertyTest.php
class SubscriptionServicePropertyTest extends TestCase
{
    use Eris\TestTrait;

    /**
     * Feature: polar-subscription, Property 8: Default Trial Status for New Users
     * Validates: Requirements 5.2
     */
    public function testNewUserWithoutSubscriptionGetsTrial(): void
    {
        $this->forAll(
            Generator\date('2024-01-01', 'now')
        )
        ->withMaxSize(100)
        ->then(function ($registrationDate) {
            // Test implementation
        });
    }
}
```

## Configuration

### Environment Variables

```env
POLAR_API_TOKEN=polar_oat_xxxxx
POLAR_WEBHOOK_SECRET=whsec_xxxxx
POLAR_PRODUCT_STANDARD=prod_xxxxx
POLAR_PRODUCT_PRO=prod_xxxxx
POLAR_SUCCESS_URL=https://qashierwise.com/dashboard?subscription=success
POLAR_CANCEL_URL=https://qashierwise.com/pricing?subscription=cancelled
```

### Config File (config/polar.php)

```php
return [
    'api_token' => env('POLAR_API_TOKEN'),
    'webhook_secret' => env('POLAR_WEBHOOK_SECRET'),
    'products' => [
        'standard' => env('POLAR_PRODUCT_STANDARD'),
        'pro' => env('POLAR_PRODUCT_PRO'),
    ],
    'urls' => [
        'success' => env('POLAR_SUCCESS_URL'),
        'cancel' => env('POLAR_CANCEL_URL'),
    ],
    'trial_days' => 14,
];
```
