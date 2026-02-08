# Email OTP Verification Setup Guide

## Overview
Sistem verifikasi email menggunakan OTP (One-Time Password) yang dikirim via Resend email service setelah user melakukan registrasi.

## Changes Made

### 1. Email Configuration
- **Package Installed**: `resend/resend-laravel` v1.1.0
- **Mail Mailer**: Configured to use `resend` (set in `.env`)
- **Direct Send**: Removed `ShouldQueue` from `SendOtpNotification` untuk pengiriman langsung tanpa queue

### 2. Files Modified

#### Controllers
- `app/Notifications/SendOtpNotification.php`
  - Removed `implements ShouldQueue` untuk mengirim email langsung
  - Email sekarang dikirim synchronous tanpa memerlukan queue worker

#### Routes
- `routes/web.php`
  - Added: `GET /verify-email` route untuk halaman verifikasi OTP

#### Views
- Created: `resources/views/auth/verify-email.blade.php`
  - UI untuk input OTP dengan 6 digit
  - Auto-format hanya menerima angka
  - Resend OTP dengan countdown timer (60 detik)
  - Success/error feedback messages

- Modified: `resources/views/auth/register.blade.php`
  - Redirect ke `/verify-email` setelah registrasi berhasil (sebelumnya langsung ke dashboard)
  - Simpan email dan status registrasi ke localStorage

#### Language Files
- `resources/lang/en/auth.php` - Added English translations
- `resources/lang/id/auth.php` - Added Indonesian translations

New translation keys:
- `verify_email_subtitle`
- `verify_email_button`
- `enter_verification_code`
- `verification_code_sent`
- `verification_code_resent`
- `email_verified_success`
- `verification_failed`
- `resend_failed`
- `didnt_receive_code`
- `resend_code`
- `resend_in`
- `sending`
- `verifying`
- `back_to_login`

### 3. User Flow

```
1. User registers at /register
   ↓
2. Account created, OTP generated and sent to email
   ↓
3. User redirected to /verify-email
   ↓
4. User enters 6-digit OTP from email
   ↓
5. OTP verified via API
   ↓
6. User redirected to /dashboard
```

### 4. API Endpoints Used

- `POST /api/verify-otp` - Verify the OTP code
  ```json
  {
    "email": "user@example.com",
    "otp": "123456",
    "type": "email_verification"
  }
  ```

- `POST /api/resend-otp` - Resend OTP code
  ```json
  {
    "email": "user@example.com",
    "type": "email_verification"
  }
  ```

## Environment Variables Required

```env
# Mail Configuration
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="noreply@qashierwise.com"
MAIL_FROM_NAME="QashierWise"

# Resend API
RESEND_API_KEY=re_your_api_key_here
RESEND_WEBHOOK_SECRET=whsec_your_webhook_secret_here
```

## Features

### Email OTP Verification Page
- ✅ Clean, centered UI with QashierWise branding
- ✅ 6-digit OTP input dengan validasi hanya angka
- ✅ Real-time validation (disable submit jika < 6 digit)
- ✅ Success/Error message feedback
- ✅ Auto-clear OTP pada error
- ✅ Resend OTP functionality dengan 60s countdown
- ✅ Rate limiting pada resend (handled by API)
- ✅ Responsive design (mobile & desktop)
- ✅ Touch-friendly untuk mobile
- ✅ Bilingual support (EN/ID)

### Security Features
- OTP expires after 10 minutes (configurable)
- Rate limiting: Max 3 OTP requests per minute
- OTP is single-use only
- Secure storage in database (hashed)

## Testing

### Manual Test
1. Go to `/register`
2. Fill in registration form
3. Submit - should redirect to `/verify-email`
4. Check email inbox for OTP
5. Enter OTP in verification page
6. Should redirect to `/dashboard` on success

### Email Template
The email uses `resources/views/emails/otp.blade.php` markdown template with:
- App branding
- Clear OTP display
- Expiration time
- Security notice

## Troubleshooting

### Email not received
- Check `.env` has correct `RESEND_API_KEY`
- Check `MAIL_MAILER=resend` is set
- Check spam folder
- Verify Resend dashboard for delivery status
- Check Laravel logs at `storage/logs/laravel.log`

### OTP verification fails
- Ensure OTP hasn't expired (10 minutes)
- Check OTP was typed correctly (6 digits only)
- Try resending OTP
- Check database `otps` table for record

### Email sends but not delivered
- Check Resend dashboard for bounce/complaints
- Verify sender email domain is verified in Resend
- Check webhook configuration for delivery status

## Next Steps

### Optional Enhancements
1. ✅ Add email verification badge in dashboard
2. ✅ Prevent unverified users from accessing certain features
3. ✅ Add "Resend verification email" option in profile
4. ✅ Track email delivery status via Resend webhooks
5. ✅ Add rate limiting UI feedback

## Related Documentation
- [OTP Implementation Summary](./OTP_IMPLEMENTATION_SUMMARY.md)
- [OTP Verification API](./OTP_VERIFICATION_API.md)
- [Resend Webhook Configuration](./RESEND_WEBHOOK_CONFIGURATION.md)
- [Resend API Reference](https://resend.com/docs/api-reference/emails/send-email)
