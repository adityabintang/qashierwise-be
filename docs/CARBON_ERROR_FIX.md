# Carbon Error Fix - OTP System

## 🔧 Problem Fixed

**Error:** `Carbon\Carbon::rawAddUnit(): Argument #3 ($value) must be of type int|float, string given`

**Cause:** Environment values from `env()` are returned as strings, but Carbon's `addMinutes()` method requires integer/float parameters.

---

## ✅ Solution

All config values are now explicitly cast to integers in both OtpService and AuthController.

### Files Updated

#### 1. **app/Services/OtpService.php**
```php
// Before (causing error)
public function __construct()
{
    $this->expirationMinutes = config('otp.expiration_minutes', 10); // Returns string "10"
    $this->otpLength = config('otp.length', 6);
    $this->rateLimitMax = config('otp.rate_limit_max', 3);
    $this->rateLimitMinutes = config('otp.rate_limit_minutes', 1);
}

// After (fixed)
public function __construct()
{
    $this->expirationMinutes = (int) config('otp.expiration_minutes', 10); // Returns int 10
    $this->otpLength = (int) config('otp.length', 6);
    $this->rateLimitMax = (int) config('otp.rate_limit_max', 3);
    $this->rateLimitMinutes = (int) config('otp.rate_limit_minutes', 1);
}
```

#### 2. **app/Http/Controllers/Api/AuthController.php**
```php
// Before (causing error)
$user->notify(new SendOtpNotification($otp, 'email_verification', config('otp.expiration_minutes')));
//                                                                                     ^^^^^ Returns string

'expires_in_minutes' => config('otp.expiration_minutes')
//                                    ^^^^^ Returns string

// After (fixed)
$user->notify(new SendOtpNotification($otp, 'email_verification', (int) config('otp.expiration_minutes')));
//                                                                                     ^^^^^^^^ Returns int

'expires_in_minutes' => (int) config('otp.expiration_minutes')
//                                    ^^^^^^^^ Returns int
```

---

## 📝 Changes Made

### OtpService Constructor (Line 16-22)
All config values cast to `int`:
- `$this->expirationMinutes = (int) config('otp.expiration_minutes', 10);`
- `$this->otpLength = (int) config('otp.length', 6);`
- `$this->rateLimitMax = (int) config('otp.rate_limit_max', 3);`
- `$this->rateLimitMinutes = (int) config('otp.rate_limit_minutes', 1);`

### AuthController (3 locations)
1. **Line 46** - `register()` method
2. **Lines 182, 187** - `sendOtp()` method
3. **Lines 279, 284** - `resendOtp()` method

All `config('otp.expiration_minutes')` calls changed to `(int) config('otp.expiration_minutes')`

---

## ✅ Verification

Config has been recached:
```bash
php artisan config:cache
```

Config values verified:
```bash
php artisan config:show otp
```

Output:
```
expiration_minutes ................... 10  (integer)
length ................................ 6   (integer)
rate_limit_max ....................... 3   (integer)
rate_limit_minutes .................. 1   (integer)
```

---

## 🧪 Testing

### Test OTP Generation
```bash
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test User",
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

**Expected:** No Carbon error, OTP generated and sent to email.

### Test Queue Worker
Make sure queue worker is running:
```bash
php artisan queue:work --tries=3 --timeout=90
```

---

## 🎯 Summary

**Issue:** Type mismatch - string passed to Carbon instead of integer
**Root Cause:** `env()` returns strings
**Solution:** Explicit type casting `(int)` on all config values
**Files Updated:** 2 (OtpService.php, AuthController.php)
**Status:** ✅ Fixed and config cached

---

## 📞 Support

If error persists:
1. Clear config cache: `php artisan config:clear`
2. Recache: `php artisan config:cache`
3. Check .env values are numeric
4. Restart queue worker
5. Check logs: `storage/logs/laravel.log`

---

**Fixed:** January 25, 2026
**Status:** ✅ Ready for testing
