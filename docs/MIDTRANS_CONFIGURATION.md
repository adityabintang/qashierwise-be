# Midtrans Subscription Configuration Guide

## Overview

This document provides comprehensive information about configuring the Midtrans subscription integration, including environment variables, configuration files, and deployment settings.

## Environment Variables

### Required Variables

Add these variables to your `.env` file:

```env
# Midtrans API Credentials
MIDTRANS_SERVER_KEY=your_server_key_here
MIDTRANS_CLIENT_KEY=your_client_key_here
MIDTRANS_IS_PRODUCTION=false

# Subscription Callback URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"

# Optional: Subscription Provider (default: midtrans)
SUBSCRIPTION_PROVIDER=midtrans
```

### Environment-Specific Configuration

#### Development/Local

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
APP_URL=http://localhost:8000
```

#### Staging

```env
MIDTRANS_SERVER_KEY=SB-Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=SB-Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=false
APP_URL=https://staging.yourdomain.com
```

#### Production

```env
MIDTRANS_SERVER_KEY=Mid-server-xxxxxxxxxxxxx
MIDTRANS_CLIENT_KEY=Mid-client-xxxxxxxxxxxxx
MIDTRANS_IS_PRODUCTION=true
APP_URL=https://yourdomain.com
```

### Getting Midtrans Credentials

1. **Sandbox Credentials**:
   - Sign up at [Midtrans Sandbox](https://dashboard.sandbox.midtrans.com/)
   - Navigate to Settings → Access Keys
   - Copy Server Key and Client Key

2. **Production Credentials**:
   - Sign up at [Midtrans Production](https://dashboard.midtrans.com/)
   - Complete business verification
   - Navigate to Settings → Access Keys
   - Copy Server Key and Client Key

## Configuration Files

### 1. Subscription Configuration

**File**: `config/subscription.php`

```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Subscription Provider
    |--------------------------------------------------------------------------
    |
    | The default subscription provider to use. Options: 'polar', 'midtrans'
    |
    */
    'provider' => env('SUBSCRIPTION_PROVIDER', 'midtrans'),

    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Midtrans subscription integration
    |
    */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
        
        // API Base URLs (automatically selected based on is_production)
        'base_url' => env('MIDTRANS_IS_PRODUCTION', false)
            ? 'https://api.midtrans.com/v1'
            : 'https://api.sandbox.midtrans.com/v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | Callback URLs
    |--------------------------------------------------------------------------
    |
    | URLs where users are redirected after subscription actions
    |
    */
    'urls' => [
        'success' => env('MIDTRANS_SUBSCRIPTION_SUCCESS_URL', config('app.url') . '/subscription/success'),
        'cancel' => env('MIDTRANS_SUBSCRIPTION_CANCEL_URL', config('app.url') . '/subscription/cancel'),
        'error' => env('MIDTRANS_SUBSCRIPTION_ERROR_URL', config('app.url') . '/subscription/error'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans
    |--------------------------------------------------------------------------
    |
    | Available subscription plans with pricing and features
    |
    */
    'plans' => [
        'standard' => [
            'id' => 'standard',
            'name' => 'Standard',
            'price' => 99000, // IDR
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
            'features' => [
                'unlimited_messages' => true,
                'ai_agent' => true,
                'basic_analytics' => true,
                'email_support' => true,
                'max_contacts' => 1000,
                'max_templates' => 10,
            ],
            'description' => 'Perfect for small businesses',
        ],
        'pro' => [
            'id' => 'pro',
            'name' => 'Pro',
            'price' => 199000, // IDR
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'IDR',
            'features' => [
                'unlimited_messages' => true,
                'ai_agent' => true,
                'advanced_analytics' => true,
                'priority_support' => true,
                'max_contacts' => 10000,
                'max_templates' => 50,
                'custom_branding' => true,
                'api_access' => true,
            ],
            'description' => 'For growing businesses',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Trial Configuration
    |--------------------------------------------------------------------------
    |
    | Free trial settings for new users
    |
    */
    'trial' => [
        'enabled' => true,
        'days' => 14,
    ],

    /*
    |--------------------------------------------------------------------------
    | Grace Period
    |--------------------------------------------------------------------------
    |
    | Days to keep subscription active after payment failure
    |
    */
    'grace_period_days' => 3,
];
```

### 2. Midtrans Configuration (Legacy)

**File**: `config/midtrans.php`

This file may already exist for QRIS integration. Ensure it doesn't conflict:

```php
<?php

return [
    'server_key' => env('MIDTRANS_SERVER_KEY'),
    'client_key' => env('MIDTRANS_CLIENT_KEY'),
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => true,
    'is_3ds' => true,
];
```

## Webhook Configuration

### Midtrans Dashboard Setup

1. **Login to Midtrans Dashboard**:
   - Sandbox: https://dashboard.sandbox.midtrans.com/
   - Production: https://dashboard.midtrans.com/

2. **Navigate to Settings → Configuration**

3. **Set Webhook URLs**:
   ```
   Payment Notification URL: https://yourdomain.com/api/webhooks/midtrans
   Recurring Notification URL: https://yourdomain.com/api/webhooks/midtrans
   Pay Account Notification URL: https://yourdomain.com/api/webhooks/midtrans
   ```

4. **Enable Webhook Events**:
   - ✅ Payment Success
   - ✅ Payment Pending
   - ✅ Payment Failed
   - ✅ Subscription Created
   - ✅ Subscription Updated
   - ✅ Subscription Cancelled

### Application Webhook Configuration

**File**: `routes/api.php`

```php
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleSubscriptionWebhook'])
    ->middleware('throttle:60,1')
    ->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.midtrans.subscription');
```

**CSRF Exclusion** (if needed):

**File**: `app/Http/Middleware/VerifyCsrfToken.php`

```php
protected $except = [
    'api/webhooks/midtrans',
];
```

## Database Configuration

### Migration

The subscription migration adds Midtrans-specific columns:

```php
Schema::table('subscriptions', function (Blueprint $table) {
    $table->string('midtrans_subscription_id')->nullable()->after('polar_customer_id');
    $table->string('midtrans_customer_id')->nullable()->after('midtrans_subscription_id');
    $table->string('provider')->default('polar')->after('midtrans_customer_id');
    $table->json('metadata')->nullable()->after('provider');
    
    $table->index('midtrans_subscription_id');
    $table->index('provider');
});
```

Run migration:
```bash
php artisan migrate
```

## Plan Configuration

### Adding New Plans

To add a new plan, update `config/subscription.php`:

```php
'plans' => [
    // Existing plans...
    
    'enterprise' => [
        'id' => 'enterprise',
        'name' => 'Enterprise',
        'price' => 499000,
        'interval' => 'month',
        'interval_count' => 1,
        'currency' => 'IDR',
        'features' => [
            'unlimited_messages' => true,
            'ai_agent' => true,
            'enterprise_analytics' => true,
            'dedicated_support' => true,
            'max_contacts' => -1, // unlimited
            'max_templates' => -1, // unlimited
            'custom_branding' => true,
            'api_access' => true,
            'white_label' => true,
            'sla_guarantee' => true,
        ],
        'description' => 'For large enterprises',
    ],
],
```

### Modifying Plan Prices

**Important**: Changing prices only affects new subscriptions. Existing subscriptions maintain their original price.

```php
'plans' => [
    'standard' => [
        'price' => 129000, // Updated from 99000
        // ... other settings
    ],
],
```

### Feature Flags

Control feature access based on plan:

```php
// Check if user can access a feature
if (config('subscription.plans.' . $user->subscription->plan_name . '.features.api_access')) {
    // Allow API access
}

// Or use the service method
if ($subscriptionService->canAccessFeature($user, 'api_access')) {
    // Allow API access
}
```

## Service Provider Configuration

### Registering Services

**File**: `app/Providers/AppServiceProvider.php`

```php
public function register()
{
    $this->app->singleton(MidtransSubscriptionService::class, function ($app) {
        return new MidtransSubscriptionService();
    });
    
    $this->app->singleton(SubscriptionService::class, function ($app) {
        return new SubscriptionService(
            $app->make(MidtransSubscriptionService::class)
        );
    });
}
```

## Caching Configuration

### Cache Subscription Data

To improve performance, cache subscription status:

```php
// Cache user subscription for 1 hour
$subscription = Cache::remember(
    "user.{$userId}.subscription",
    3600,
    fn() => $user->subscription
);
```

### Clear Cache on Updates

```php
// In SubscriptionService
public function updateSubscriptionStatus($subscription, $status)
{
    $subscription->update(['status' => $status]);
    
    // Clear cache
    Cache::forget("user.{$subscription->user_id}.subscription");
}
```

## Queue Configuration

### Queue Webhook Processing

For better performance, process webhooks asynchronously:

**File**: `config/queue.php`

```php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 90,
        'block_for' => null,
    ],
],
```

**Create Job**:

```php
// app/Jobs/ProcessSubscriptionWebhook.php
class ProcessSubscriptionWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public function __construct(public array $payload) {}
    
    public function handle(SubscriptionService $service)
    {
        $service->processMidtransWebhook($this->payload);
    }
}
```

**Dispatch Job**:

```php
// In MidtransWebhookController
ProcessSubscriptionWebhook::dispatch($payload);
```

## Logging Configuration

### Configure Subscription Logging

**File**: `config/logging.php`

```php
'channels' => [
    'subscription' => [
        'driver' => 'daily',
        'path' => storage_path('logs/subscription.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
    ],
],
```

**Usage**:

```php
Log::channel('subscription')->info('Subscription created', [
    'user_id' => $user->id,
    'plan' => $planId,
]);
```

## Security Configuration

### Rate Limiting

**File**: `app/Http/Kernel.php`

```php
protected $middlewareGroups = [
    'api' => [
        'throttle:api',
        // ...
    ],
];

protected $routeMiddleware = [
    'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
];
```

**Apply to Webhook**:

```php
Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handleSubscriptionWebhook'])
    ->middleware('throttle:60,1'); // 60 requests per minute
```

### CORS Configuration

If using API from frontend:

**File**: `config/cors.php`

```php
'paths' => ['api/*', 'subscription/*'],
'allowed_methods' => ['*'],
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
'allowed_headers' => ['*'],
'exposed_headers' => [],
'max_age' => 0,
'supports_credentials' => true,
```

## Testing Configuration

### Test Environment

**File**: `.env.testing`

```env
APP_ENV=testing
APP_DEBUG=true

MIDTRANS_SERVER_KEY=SB-Mid-server-test
MIDTRANS_CLIENT_KEY=SB-Mid-client-test
MIDTRANS_IS_PRODUCTION=false

DB_CONNECTION=sqlite
DB_DATABASE=:memory:
```

### PHPUnit Configuration

**File**: `phpunit.xml`

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="MIDTRANS_SERVER_KEY" value="SB-Mid-server-test"/>
    <env name="MIDTRANS_CLIENT_KEY" value="SB-Mid-client-test"/>
    <env name="MIDTRANS_IS_PRODUCTION" value="false"/>
</php>
```

## Deployment Checklist

### Pre-Deployment

- [ ] Set production Midtrans credentials
- [ ] Update `MIDTRANS_IS_PRODUCTION=true`
- [ ] Configure webhook URLs in Midtrans dashboard
- [ ] Set correct `APP_URL`
- [ ] Enable HTTPS
- [ ] Configure rate limiting
- [ ] Set up monitoring/logging
- [ ] Test webhook delivery
- [ ] Backup database

### Post-Deployment

- [ ] Verify webhook endpoint is accessible
- [ ] Test subscription creation
- [ ] Monitor logs for errors
- [ ] Verify payment processing
- [ ] Test cancellation flow
- [ ] Check backward compatibility with Polar

## Troubleshooting

### Common Issues

**Issue**: Webhook signature validation fails

**Solution**: 
- Verify `MIDTRANS_SERVER_KEY` is correct
- Check signature calculation logic
- Ensure no extra whitespace in environment variables

**Issue**: Subscription creation fails

**Solution**:
- Check API credentials are valid
- Verify `MIDTRANS_IS_PRODUCTION` matches your account type
- Review API error response in logs

**Issue**: Webhooks not received

**Solution**:
- Verify webhook URL is publicly accessible
- Check firewall/security group settings
- Test with ngrok for local development
- Review Midtrans dashboard webhook logs

## Configuration Validation

### Validate Configuration

Create an artisan command to validate configuration:

```php
// app/Console/Commands/ValidateMidtransConfig.php
public function handle()
{
    $this->info('Validating Midtrans configuration...');
    
    // Check required env variables
    $required = ['MIDTRANS_SERVER_KEY', 'MIDTRANS_CLIENT_KEY'];
    foreach ($required as $var) {
        if (!env($var)) {
            $this->error("Missing: {$var}");
            return 1;
        }
    }
    
    // Test API connection
    try {
        $service = app(MidtransSubscriptionService::class);
        // Make a test API call
        $this->info('✓ API connection successful');
    } catch (\Exception $e) {
        $this->error('✗ API connection failed: ' . $e->getMessage());
        return 1;
    }
    
    $this->info('✓ Configuration valid');
    return 0;
}
```

Run validation:
```bash
php artisan midtrans:validate-config
```

## References

- [Laravel Configuration Documentation](https://laravel.com/docs/configuration)
- [Midtrans Dashboard](https://dashboard.midtrans.com/)
- [Midtrans API Documentation](https://docs.midtrans.com/)
