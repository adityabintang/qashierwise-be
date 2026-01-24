# Midtrans Subscription Error Handling

## Overview

This document outlines comprehensive error handling strategies for the Midtrans subscription integration, including API errors, webhook failures, payment issues, and recovery procedures.

## Error Categories

### 1. API Errors
- Connection failures
- Authentication errors
- Invalid request parameters
- Rate limiting
- Server errors

### 2. Webhook Errors
- Invalid signatures
- Processing failures
- Duplicate events
- Missing data

### 3. Payment Errors
- Card declined
- Insufficient funds
- Expired cards
- Fraud detection

### 4. Business Logic Errors
- Invalid subscription state
- Duplicate subscriptions
- Plan not found
- User not found

## API Error Handling

### HTTP Status Codes

| Status Code | Meaning | Action |
|------------|---------|--------|
| 200 | Success | Process response |
| 201 | Created | Process response |
| 400 | Bad Request | Log and show user error |
| 401 | Unauthorized | Check credentials |
| 403 | Forbidden | Check permissions |
| 404 | Not Found | Handle gracefully |
| 429 | Too Many Requests | Implement retry with backoff |
| 500 | Server Error | Retry with exponential backoff |
| 502/503 | Service Unavailable | Retry with exponential backoff |

### Error Response Structure

Midtrans API returns errors in this format:

```json
{
  "status_code": "400",
  "status_message": "Invalid request parameters",
  "validation_messages": [
    "amount must be a valid number",
    "customer_details.email is required"
  ]
}
```

### Implementation

```php
// app/Services/MidtransSubscriptionService.php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Exceptions\MidtransApiException;

public function createSubscription(User $user, string $planId, string $paymentToken): array
{
    try {
        $response = Http::withBasicAuth($this->serverKey, '')
            ->timeout(30)
            ->retry(3, 1000, function ($exception, $request) {
                // Retry on connection errors and 5xx errors
                return $exception instanceof \Illuminate\Http\Client\ConnectionException
                    || ($exception instanceof \Illuminate\Http\Client\RequestException 
                        && $exception->response->status() >= 500);
            })
            ->post("{$this->baseUrl}/subscriptions", $this->buildPayload($user, $planId, $paymentToken));
        
        if (!$response->successful()) {
            $this->handleApiError($response, $user, $planId);
        }
        
        return $response->json();
        
    } catch (\Illuminate\Http\Client\ConnectionException $e) {
        Log::error('Midtrans connection failed', [
            'error' => $e->getMessage(),
            'user_id' => $user->id,
            'plan_id' => $planId,
        ]);
        
        throw new MidtransApiException(
            'Unable to connect to payment service. Please try again later.',
            503,
            $e
        );
        
    } catch (\Exception $e) {
        Log::error('Unexpected error creating subscription', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'user_id' => $user->id,
            'plan_id' => $planId,
        ]);
        
        throw new MidtransApiException(
            'An unexpected error occurred. Please try again.',
            500,
            $e
        );
    }
}

private function handleApiError($response, User $user, string $planId): void
{
    $status = $response->status();
    $body = $response->json();
    
    Log::error('Midtrans API error', [
        'status' => $status,
        'response' => $body,
        'user_id' => $user->id,
        'plan_id' => $planId,
    ]);
    
    $message = $body['status_message'] ?? 'Payment service error';
    
    switch ($status) {
        case 400:
            throw new MidtransApiException(
                'Invalid subscription data: ' . $message,
                400
            );
            
        case 401:
            throw new MidtransApiException(
                'Payment service authentication failed. Please contact support.',
                401
            );
            
        case 404:
            throw new MidtransApiException(
                'Subscription not found.',
                404
            );
            
        case 429:
            throw new MidtransApiException(
                'Too many requests. Please try again in a few minutes.',
                429
            );
            
        default:
            throw new MidtransApiException(
                'Payment service error: ' . $message,
                $status
            );
    }
}
```

### Custom Exception Class

```php
// app/Exceptions/MidtransApiException.php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MidtransApiException extends Exception
{
    public function __construct(
        string $message = 'Midtrans API error',
        int $code = 500,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Render the exception as an HTTP response
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
        ], $this->getCode());
    }
    
    /**
     * Report the exception
     */
    public function report(): void
    {
        Log::error('MidtransApiException', [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'trace' => $this->getTraceAsString(),
        ]);
    }
}
```

## Controller Error Handling

### Subscription Controller

```php
// app/Http/Controllers/SubscriptionController.php

public function createCheckout(Request $request)
{
    try {
        $validated = $request->validate([
            'plan_id' => 'required|in:standard,pro',
            'payment_token' => 'required|string',
        ]);
        
        $user = auth()->user();
        
        // Check if user already has active subscription
        if ($user->subscription && $user->subscription->status === 'active') {
            return redirect()
                ->route('subscription.manage')
                ->with('error', 'You already have an active subscription.');
        }
        
        // Create subscription
        $subscription = $this->subscriptionService->createMidtransSubscription(
            $user,
            $validated['plan_id'],
            $validated['payment_token']
        );
        
        return redirect()
            ->route('subscription.success')
            ->with('success', 'Subscription created successfully!');
            
    } catch (MidtransApiException $e) {
        Log::warning('Subscription creation failed', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);
        
        return redirect()
            ->back()
            ->with('error', $e->getMessage());
            
    } catch (\Illuminate\Validation\ValidationException $e) {
        return redirect()
            ->back()
            ->withErrors($e->errors())
            ->withInput();
            
    } catch (\Exception $e) {
        Log::error('Unexpected error in checkout', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return redirect()
            ->route('subscription.error')
            ->with('error', 'An unexpected error occurred. Please try again or contact support.');
    }
}

public function cancelSubscription(Request $request)
{
    try {
        $user = auth()->user();
        $subscription = $user->subscription;
        
        if (!$subscription || $subscription->status !== 'active') {
            return redirect()
                ->route('subscription.manage')
                ->with('error', 'No active subscription to cancel.');
        }
        
        $this->subscriptionService->cancelSubscription($subscription);
        
        return redirect()
            ->route('subscription.manage')
            ->with('success', 'Subscription cancelled successfully. Access will continue until the end of the current period.');
            
    } catch (MidtransApiException $e) {
        return redirect()
            ->back()
            ->with('error', 'Failed to cancel subscription: ' . $e->getMessage());
            
    } catch (\Exception $e) {
        Log::error('Subscription cancellation failed', [
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
        ]);
        
        return redirect()
            ->back()
            ->with('error', 'An error occurred. Please try again or contact support.');
    }
}
```

## Webhook Error Handling

### Signature Validation Errors

```php
// app/Http/Controllers/Api/MidtransWebhookController.php

public function handleSubscriptionWebhook(Request $request): JsonResponse
{
    $payload = $request->all();
    
    // Log all webhook receipts
    Log::channel('subscription')->info('Webhook received', [
        'payload' => $payload,
        'ip' => $request->ip(),
    ]);
    
    // Validate signature
    if (!$this->validateSignature($payload)) {
        Log::warning('Invalid webhook signature', [
            'payload' => $payload,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return response()->json([
            'status' => 'error',
            'message' => 'Invalid signature',
        ], 403);
    }
    
    // Process webhook
    try {
        $this->processWebhook($payload);
        
        return response()->json(['status' => 'ok'], 200);
        
    } catch (\Exception $e) {
        Log::error('Webhook processing failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'payload' => $payload,
        ]);
        
        // Return 200 to prevent Midtrans from retrying
        // We'll handle recovery through reconciliation
        return response()->json(['status' => 'ok'], 200);
    }
}

private function processWebhook(array $payload): void
{
    // Check for duplicate processing
    $transactionId = $payload['transaction_id'] ?? null;
    
    if ($transactionId && Cache::has("webhook_processed:{$transactionId}")) {
        Log::info('Duplicate webhook ignored', [
            'transaction_id' => $transactionId,
        ]);
        return;
    }
    
    // Process in database transaction
    DB::transaction(function () use ($payload) {
        $this->subscriptionService->processMidtransWebhook($payload);
    });
    
    // Mark as processed
    if ($transactionId) {
        Cache::put("webhook_processed:{$transactionId}", true, now()->addDay());
    }
}
```

## Payment Error Handling

### Payment Status Mapping

```php
// app/Services/SubscriptionService.php

private function handlePaymentStatus(Subscription $subscription, string $status): void
{
    switch ($status) {
        case 'capture':
        case 'settlement':
            $this->activateSubscription($subscription);
            break;
            
        case 'pending':
            $subscription->update(['status' => 'pending']);
            Log::info('Payment pending', [
                'subscription_id' => $subscription->id,
            ]);
            break;
            
        case 'deny':
            $this->handlePaymentDenied($subscription);
            break;
            
        case 'cancel':
            $this->handlePaymentCancelled($subscription);
            break;
            
        case 'expire':
            $this->handlePaymentExpired($subscription);
            break;
            
        case 'failure':
            $this->handlePaymentFailed($subscription);
            break;
            
        default:
            Log::warning('Unknown payment status', [
                'subscription_id' => $subscription->id,
                'status' => $status,
            ]);
    }
}

private function handlePaymentDenied(Subscription $subscription): void
{
    $subscription->update([
        'status' => 'cancelled',
        'cancelled_at' => now(),
    ]);
    
    Log::warning('Payment denied', [
        'subscription_id' => $subscription->id,
        'user_id' => $subscription->user_id,
    ]);
    
    // Notify user
    // $subscription->user->notify(new PaymentDeniedNotification());
}

private function handlePaymentFailed(Subscription $subscription): void
{
    $failureCount = $subscription->metadata['failure_count'] ?? 0;
    $failureCount++;
    
    $metadata = $subscription->metadata ?? [];
    $metadata['failure_count'] = $failureCount;
    $metadata['last_failure_at'] = now()->toIso8601String();
    
    $subscription->update(['metadata' => $metadata]);
    
    Log::warning('Payment failed', [
        'subscription_id' => $subscription->id,
        'failure_count' => $failureCount,
    ]);
    
    // Implement dunning management
    if ($failureCount >= 3) {
        $this->suspendSubscription($subscription);
    }
}
```

### Dunning Management

```php
// app/Services/SubscriptionService.php

private function suspendSubscription(Subscription $subscription): void
{
    $subscription->update([
        'status' => 'suspended',
        'suspended_at' => now(),
    ]);
    
    Log::warning('Subscription suspended due to payment failures', [
        'subscription_id' => $subscription->id,
        'user_id' => $subscription->user_id,
    ]);
    
    // Notify user about suspension
    // $subscription->user->notify(new SubscriptionSuspendedNotification());
}
```

## Recovery Strategies

### 1. Automatic Retry with Exponential Backoff

```php
use Illuminate\Support\Facades\Http;

public function createSubscriptionWithRetry(User $user, string $planId, string $token): array
{
    $maxAttempts = 3;
    $attempt = 0;
    $baseDelay = 1000; // 1 second
    
    while ($attempt < $maxAttempts) {
        try {
            return $this->createSubscription($user, $planId, $token);
            
        } catch (MidtransApiException $e) {
            $attempt++;
            
            if ($attempt >= $maxAttempts) {
                throw $e;
            }
            
            // Exponential backoff: 1s, 2s, 4s
            $delay = $baseDelay * pow(2, $attempt - 1);
            usleep($delay * 1000);
            
            Log::info('Retrying subscription creation', [
                'attempt' => $attempt,
                'delay_ms' => $delay,
            ]);
        }
    }
}
```

### 2. Reconciliation Job

```php
// app/Console/Commands/ReconcileSubscriptions.php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\MidtransSubscriptionService;
use Illuminate\Console\Command;

class ReconcileSubscriptions extends Command
{
    protected $signature = 'subscriptions:reconcile';
    protected $description = 'Reconcile subscription status with Midtrans';
    
    public function handle(MidtransSubscriptionService $service)
    {
        $subscriptions = Subscription::where('provider', 'midtrans')
            ->whereIn('status', ['active', 'pending'])
            ->get();
        
        $reconciled = 0;
        $errors = 0;
        
        foreach ($subscriptions as $subscription) {
            try {
                $midtransData = $service->getSubscription(
                    $subscription->midtrans_subscription_id
                );
                
                if (!$midtransData) {
                    $this->warn("Subscription not found in Midtrans: {$subscription->id}");
                    continue;
                }
                
                $midtransStatus = $this->mapMidtransStatus($midtransData['status']);
                
                if ($midtransStatus !== $subscription->status) {
                    $subscription->update(['status' => $midtransStatus]);
                    $reconciled++;
                    
                    $this->info("Reconciled subscription {$subscription->id}: {$subscription->status} -> {$midtransStatus}");
                }
                
            } catch (\Exception $e) {
                $errors++;
                $this->error("Error reconciling subscription {$subscription->id}: {$e->getMessage()}");
            }
        }
        
        $this->info("Reconciliation complete: {$reconciled} updated, {$errors} errors");
        
        return 0;
    }
    
    private function mapMidtransStatus(string $status): string
    {
        return match($status) {
            'active' => 'active',
            'disabled' => 'cancelled',
            'pending' => 'pending',
            default => 'unknown',
        };
    }
}
```

Schedule the reconciliation job:

```php
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    // Run reconciliation every hour
    $schedule->command('subscriptions:reconcile')
        ->hourly()
        ->withoutOverlapping();
}
```

### 3. Manual Recovery Tools

```php
// app/Console/Commands/FixSubscription.php

namespace App\Console\Commands;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class FixSubscription extends Command
{
    protected $signature = 'subscription:fix {subscription_id}';
    protected $description = 'Manually fix a subscription';
    
    public function handle(SubscriptionService $service)
    {
        $subscriptionId = $this->argument('subscription_id');
        $subscription = Subscription::find($subscriptionId);
        
        if (!$subscription) {
            $this->error('Subscription not found');
            return 1;
        }
        
        $this->info("Current status: {$subscription->status}");
        
        $newStatus = $this->choice(
            'Select new status',
            ['active', 'cancelled', 'pending', 'suspended'],
            0
        );
        
        if ($this->confirm("Change status to {$newStatus}?")) {
            $subscription->update(['status' => $newStatus]);
            $this->info('Subscription updated successfully');
        }
        
        return 0;
    }
}
```

## Monitoring and Alerting

### Error Rate Monitoring

```php
// app/Services/ErrorMonitoringService.php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ErrorMonitoringService
{
    private const ERROR_THRESHOLD = 10; // errors per minute
    private const ALERT_COOLDOWN = 300; // 5 minutes
    
    public function recordError(string $type, string $message): void
    {
        $key = "errors:{$type}:" . now()->format('Y-m-d-H-i');
        $count = Cache::increment($key);
        Cache::put($key, $count, now()->addMinutes(2));
        
        if ($count >= self::ERROR_THRESHOLD) {
            $this->sendAlert($type, $count);
        }
    }
    
    private function sendAlert(string $type, int $count): void
    {
        $alertKey = "alert_sent:{$type}";
        
        if (Cache::has($alertKey)) {
            return; // Alert already sent recently
        }
        
        Log::critical("High error rate detected", [
            'type' => $type,
            'count' => $count,
            'threshold' => self::ERROR_THRESHOLD,
        ]);
        
        // Send notification to admin
        // Notification::route('slack', config('services.slack.webhook'))
        //     ->notify(new HighErrorRateAlert($type, $count));
        
        Cache::put($alertKey, true, now()->addSeconds(self::ALERT_COOLDOWN));
    }
}
```

## User-Facing Error Messages

### Error Message Guidelines

1. **Be Clear**: Explain what went wrong
2. **Be Helpful**: Suggest next steps
3. **Be Honest**: Don't hide errors behind vague messages
4. **Be Empathetic**: Acknowledge the inconvenience

### Error Message Examples

```php
// Good error messages
$messages = [
    'payment_declined' => 'Your payment was declined. Please check your card details or try a different payment method.',
    'insufficient_funds' => 'Your card has insufficient funds. Please use a different payment method.',
    'expired_card' => 'Your card has expired. Please update your payment information.',
    'connection_error' => 'We\'re having trouble connecting to our payment service. Please try again in a few minutes.',
    'rate_limit' => 'Too many requests. Please wait a moment and try again.',
    'server_error' => 'Something went wrong on our end. Our team has been notified. Please try again later.',
];

// Bad error messages (avoid these)
$badMessages = [
    'Error 500', // Too technical
    'Something went wrong', // Too vague
    'Invalid request', // Not helpful
    'Contact support', // No context
];
```

## Testing Error Scenarios

### Unit Tests

```php
// tests/Unit/Services/MidtransSubscriptionServiceTest.php

public function test_handles_api_connection_error()
{
    Http::fake([
        '*' => Http::response(null, 0), // Connection error
    ]);
    
    $this->expectException(MidtransApiException::class);
    $this->expectExceptionMessage('Unable to connect to payment service');
    
    $service = new MidtransSubscriptionService();
    $service->createSubscription($this->user, 'standard', 'token');
}

public function test_handles_401_unauthorized()
{
    Http::fake([
        '*' => Http::response(['status_message' => 'Unauthorized'], 401),
    ]);
    
    $this->expectException(MidtransApiException::class);
    $this->expectExceptionCode(401);
    
    $service = new MidtransSubscriptionService();
    $service->createSubscription($this->user, 'standard', 'token');
}
```

## Best Practices

1. **Always log errors** with sufficient context
2. **Use specific exception types** for different error categories
3. **Implement retry logic** for transient failures
4. **Return user-friendly messages** to the frontend
5. **Monitor error rates** and set up alerts
6. **Test error scenarios** thoroughly
7. **Have a rollback plan** for critical failures
8. **Document error codes** and their meanings
9. **Implement circuit breakers** for external services
10. **Use database transactions** for data consistency

## References

- [Laravel Error Handling](https://laravel.com/docs/errors)
- [HTTP Status Codes](https://developer.mozilla.org/en-US/docs/Web/HTTP/Status)
- [Midtrans Error Codes](https://docs.midtrans.com/reference/error-codes)
