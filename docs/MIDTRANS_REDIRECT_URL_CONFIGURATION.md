# Midtrans Redirect URL Configuration Guide

## Overview
This guide explains how to configure redirect URLs for Midtrans subscription checkout flow. These URLs determine where users are redirected after completing, cancelling, or encountering errors during payment.

## Redirect URL Types

### 1. Success URL
Users are redirected here after successful payment completion.

**Purpose**:
- Confirm successful subscription activation
- Display subscription details
- Provide next steps for the user

**Default**: `${APP_URL}/subscription/success`

### 2. Cancel URL
Users are redirected here when they cancel the payment process.

**Purpose**:
- Inform user that payment was cancelled
- Offer option to retry
- Provide alternative payment methods

**Default**: `${APP_URL}/subscription/cancel`

### 3. Error URL
Users are redirected here when an error occurs during payment.

**Purpose**:
- Display error message
- Provide troubleshooting steps
- Offer support contact information

**Default**: `${APP_URL}/subscription/error`

## Environment Configuration

### Environment Variables

Add these variables to your `.env` file:

```env
# Application Base URL
APP_URL=https://your-domain.com

# Midtrans Redirect URLs
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

### Configuration File

The redirect URLs are loaded from `config/subscription.php`:

```php
'urls' => [
    'success' => env('MIDTRANS_SUBSCRIPTION_SUCCESS_URL'),
    'cancel' => env('MIDTRANS_SUBSCRIPTION_CANCEL_URL'),
    'error' => env('MIDTRANS_SUBSCRIPTION_ERROR_URL'),
],
```

## Environment-Specific Configuration

### Development (Local)

```env
APP_URL=http://localhost:8000
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

**Note**: Local URLs won't work with Midtrans. Use ngrok or similar for local testing:

```env
APP_URL=https://abc123.ngrok.io
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

### Staging

```env
APP_URL=https://staging.your-domain.com
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

### Production

```env
APP_URL=https://your-domain.com
MIDTRANS_SUBSCRIPTION_SUCCESS_URL="${APP_URL}/subscription/success"
MIDTRANS_SUBSCRIPTION_CANCEL_URL="${APP_URL}/subscription/cancel"
MIDTRANS_SUBSCRIPTION_ERROR_URL="${APP_URL}/subscription/error"
```

## URL Requirements

### HTTPS Required
- All redirect URLs **must** use HTTPS in production
- HTTP URLs are only acceptable in local development
- Ensure SSL certificate is valid

### Public Accessibility
- URLs must be publicly accessible
- Cannot be behind authentication (for initial redirect)
- Must load within 5 seconds

### Query Parameters
Midtrans may append query parameters to redirect URLs:
- `order_id` - The order/subscription ID
- `status_code` - Transaction status code
- `transaction_status` - Transaction status

**Example**:
```
https://your-domain.com/subscription/success?order_id=sub_123&status_code=200&transaction_status=settlement
```

## Route Configuration

The redirect URLs are handled by routes defined in `routes/web.php`:

```php
Route::middleware(['auth'])->group(function () {
    // Success callback
    Route::get('/subscription/success', [SubscriptionController::class, 'success'])
        ->name('subscription.success');
    
    // Cancel callback
    Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel'])
        ->name('subscription.cancel');
    
    // Error callback
    Route::get('/subscription/error', [SubscriptionController::class, 'error'])
        ->name('subscription.error');
});
```

**Important**: These routes require authentication (`auth` middleware).

## Controller Implementation

### Success Handler

```php
public function success(Request $request): RedirectResponse
{
    $orderId = $request->query('order_id');
    $statusCode = $request->query('status_code');
    $transactionStatus = $request->query('transaction_status');
    
    // Log the success
    Log::info('Subscription payment successful', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'transaction_status' => $transactionStatus,
        'user_id' => auth()->id(),
    ]);
    
    // Redirect to dashboard with success message
    return redirect()->route('dashboard')
        ->with('success', 'Subscription activated successfully!');
}
```

### Cancel Handler

```php
public function cancel(Request $request): RedirectResponse
{
    $orderId = $request->query('order_id');
    
    // Log the cancellation
    Log::info('Subscription payment cancelled', [
        'order_id' => $orderId,
        'user_id' => auth()->id(),
    ]);
    
    // Redirect to pricing page with message
    return redirect()->route('subscription.pricing')
        ->with('info', 'Payment was cancelled. You can try again anytime.');
}
```

### Error Handler

```php
public function error(Request $request): RedirectResponse
{
    $orderId = $request->query('order_id');
    $statusCode = $request->query('status_code');
    
    // Log the error
    Log::error('Subscription payment error', [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'user_id' => auth()->id(),
    ]);
    
    // Redirect to pricing page with error message
    return redirect()->route('subscription.pricing')
        ->with('error', 'An error occurred during payment. Please try again or contact support.');
}
```

## View Implementation

### Success View

Create `resources/views/subscription/success.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="success-message">
        <h1>✅ Subscription Activated!</h1>
        <p>Your subscription has been activated successfully.</p>
        
        <div class="subscription-details">
            <h2>What's Next?</h2>
            <ul>
                <li>Access all premium features</li>
                <li>Manage your subscription in dashboard</li>
                <li>Get priority support</li>
            </ul>
        </div>
        
        <a href="{{ route('dashboard') }}" class="btn btn-primary">
            Go to Dashboard
        </a>
    </div>
</div>
@endsection
```

### Cancel View

Create `resources/views/subscription/cancel.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="cancel-message">
        <h1>Payment Cancelled</h1>
        <p>You cancelled the payment process.</p>
        
        <div class="options">
            <h2>What would you like to do?</h2>
            <a href="{{ route('subscription.pricing') }}" class="btn btn-primary">
                Try Again
            </a>
            <a href="{{ route('dashboard') }}" class="btn btn-secondary">
                Back to Dashboard
            </a>
        </div>
    </div>
</div>
@endsection
```

### Error View

Create `resources/views/subscription/error.blade.php`:

```blade
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="error-message">
        <h1>❌ Payment Error</h1>
        <p>An error occurred during the payment process.</p>
        
        <div class="troubleshooting">
            <h2>Troubleshooting Steps</h2>
            <ul>
                <li>Check your internet connection</li>
                <li>Verify your payment details</li>
                <li>Try a different payment method</li>
                <li>Contact your bank if issue persists</li>
            </ul>
        </div>
        
        <div class="actions">
            <a href="{{ route('subscription.pricing') }}" class="btn btn-primary">
                Try Again
            </a>
            <a href="mailto:support@your-domain.com" class="btn btn-secondary">
                Contact Support
            </a>
        </div>
    </div>
</div>
@endsection
```

## Testing Redirect URLs

### 1. Test URL Accessibility

```bash
# Test success URL
curl -I https://your-domain.com/subscription/success

# Test cancel URL
curl -I https://your-domain.com/subscription/cancel

# Test error URL
curl -I https://your-domain.com/subscription/error
```

Expected response: `HTTP/2 200` or `HTTP/2 302` (redirect to login)

### 2. Test with Query Parameters

```bash
# Test success URL with parameters
curl "https://your-domain.com/subscription/success?order_id=sub_123&status_code=200&transaction_status=settlement"

# Test cancel URL with parameters
curl "https://your-domain.com/subscription/cancel?order_id=sub_123"

# Test error URL with parameters
curl "https://your-domain.com/subscription/error?order_id=sub_123&status_code=500"
```

### 3. Test End-to-End Flow

1. Login to application
2. Navigate to pricing page
3. Click "Subscribe" button
4. Complete payment in Midtrans
5. Verify redirect to success URL
6. Check subscription status in dashboard

### 4. Test Cancel Flow

1. Login to application
2. Navigate to pricing page
3. Click "Subscribe" button
4. Click "Cancel" in Midtrans payment page
5. Verify redirect to cancel URL
6. Verify appropriate message displayed

## Monitoring and Logging

### Log Redirect Events

```php
// In controller methods
Log::info('User redirected to success page', [
    'user_id' => auth()->id(),
    'order_id' => $request->query('order_id'),
    'timestamp' => now(),
]);
```

### Monitor Redirect Patterns

```bash
# View success redirects
grep "redirected to success" storage/logs/laravel.log

# View cancel redirects
grep "redirected to cancel" storage/logs/laravel.log

# View error redirects
grep "redirected to error" storage/logs/laravel.log
```

### Analytics Tracking

Add analytics tracking to redirect pages:

```javascript
// Track successful subscription
gtag('event', 'subscription_success', {
    'event_category': 'subscription',
    'event_label': 'success',
    'value': 99000
});

// Track cancelled subscription
gtag('event', 'subscription_cancel', {
    'event_category': 'subscription',
    'event_label': 'cancel'
});

// Track subscription error
gtag('event', 'subscription_error', {
    'event_category': 'subscription',
    'event_label': 'error'
});
```

## Troubleshooting

### Issue: Redirect URL not working

**Possible Causes**:
1. URL not configured in environment
2. Route not registered
3. Middleware blocking access
4. SSL certificate invalid

**Solutions**:
1. Verify environment variables
2. Check route list: `php artisan route:list | grep subscription`
3. Review middleware configuration
4. Check SSL certificate validity

### Issue: User not authenticated after redirect

**Possible Causes**:
1. Session expired during payment
2. Cookie domain mismatch
3. CSRF token expired

**Solutions**:
1. Increase session lifetime
2. Configure session domain correctly
3. Exclude redirect URLs from CSRF verification

### Issue: Query parameters not received

**Possible Causes**:
1. URL encoding issues
2. Server configuration stripping parameters
3. Redirect chain losing parameters

**Solutions**:
1. Check URL encoding
2. Review server configuration (nginx/apache)
3. Avoid multiple redirects

## Security Considerations

### Validate Query Parameters

```php
public function success(Request $request): RedirectResponse
{
    // Validate order_id format
    $orderId = $request->query('order_id');
    if (!preg_match('/^sub_[a-zA-Z0-9]+$/', $orderId)) {
        Log::warning('Invalid order_id in redirect', ['order_id' => $orderId]);
        return redirect()->route('dashboard');
    }
    
    // Verify order belongs to user
    $subscription = Subscription::where('midtrans_subscription_id', $orderId)
        ->where('user_id', auth()->id())
        ->first();
    
    if (!$subscription) {
        Log::warning('Order not found or unauthorized', [
            'order_id' => $orderId,
            'user_id' => auth()->id(),
        ]);
        return redirect()->route('dashboard');
    }
    
    // Continue with success handling...
}
```

### Prevent Open Redirects

```php
// Never use user-provided URLs for redirects
// Always use named routes or validated URLs
return redirect()->route('dashboard'); // ✅ Safe
return redirect($request->query('redirect_url')); // ❌ Unsafe
```

### Rate Limiting

```php
// In routes/web.php
Route::middleware(['auth', 'throttle:10,1'])->group(function () {
    Route::get('/subscription/success', [SubscriptionController::class, 'success']);
    Route::get('/subscription/cancel', [SubscriptionController::class, 'cancel']);
    Route::get('/subscription/error', [SubscriptionController::class, 'error']);
});
```

## Configuration Checklist

### Environment Setup
- [ ] `APP_URL` configured correctly
- [ ] Success URL configured
- [ ] Cancel URL configured
- [ ] Error URL configured
- [ ] HTTPS enabled (production)

### Route Configuration
- [ ] Routes registered in `routes/web.php`
- [ ] Middleware applied correctly
- [ ] Route names defined
- [ ] Routes accessible

### Controller Implementation
- [ ] Success handler implemented
- [ ] Cancel handler implemented
- [ ] Error handler implemented
- [ ] Logging added
- [ ] Validation added

### View Implementation
- [ ] Success view created
- [ ] Cancel view created
- [ ] Error view created
- [ ] User-friendly messages
- [ ] Clear call-to-actions

### Testing
- [ ] URL accessibility tested
- [ ] Query parameters tested
- [ ] End-to-end flow tested
- [ ] Cancel flow tested
- [ ] Error handling tested

### Security
- [ ] Query parameter validation
- [ ] Authorization checks
- [ ] Rate limiting configured
- [ ] Open redirect prevention

## Quick Reference

| Environment | Base URL | Success URL | Cancel URL | Error URL |
|------------|----------|-------------|------------|-----------|
| Local | http://localhost:8000 | /subscription/success | /subscription/cancel | /subscription/error |
| Staging | https://staging.domain.com | /subscription/success | /subscription/cancel | /subscription/error |
| Production | https://domain.com | /subscription/success | /subscription/cancel | /subscription/error |

## References

- [Midtrans Redirect Documentation](https://docs.midtrans.com/en/snap/integration-guide)
- [Laravel Routing Documentation](https://laravel.com/docs/routing)
- [Application Routes](../routes/web.php)
- [Subscription Controller](../app/Http/Controllers/SubscriptionController.php)
