# OTP Verification API Documentation

## Overview
OTP verification system for email verification and password reset using Resend.

## Features
- Email verification after registration
- Password reset via OTP (future implementation)
- Rate limiting: 3 requests per minute per email
- OTP expiration: 10 minutes
- Max attempts: 5 before invalidation

## Environment Variables
```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS=noreply@qashierwise.com
MAIL_FROM_NAME=Qashierwise
RESEND_API_KEY=re_xxxxxx

# OTP Configuration
OTP_EXPIRATION_MINUTES=10
OTP_LENGTH=6
OTP_RATE_LIMIT_MAX=3
OTP_RATE_LIMIT_MINUTES=1
```

## API Endpoints

### 1. Send OTP
**Endpoint:** `POST /api/send-otp`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "type": "email_verification"
}
```

**Parameters:**
- `email` (required): Email address to send OTP
- `type` (optional): OTP type - `email_verification` or `password_reset` (default: `email_verification`)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "message": "OTP sent successfully",
    "type": "email_verification",
    "expires_in_minutes": 10
  }
}
```

**Error Response (429 - Rate Limited):**
```json
{
  "success": false,
  "error": "Too many OTP requests. Please try again in X seconds."
}
```

### 2. Verify OTP
**Endpoint:** `POST /api/verify-otp`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "code": "123456",
  "type": "email_verification"
}
```

**Parameters:**
- `email` (required): Email address
- `code` (required): 6-digit OTP code
- `type` (required): OTP type - `email_verification` or `password_reset`

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "email_verified_at": "2025-01-25T10:00:00.000000Z",
      "is_email_verified": true,
      "created_at": "2025-01-25T09:00:00.000000Z",
      "updated_at": "2025-01-25T10:00:00.000000Z"
    },
    "message": "Email verified successfully"
  }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "error": "Invalid or expired OTP code."
}
```

### 3. Resend OTP
**Endpoint:** `POST /api/resend-otp`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "email": "user@example.com",
  "type": "email_verification"
}
```

**Parameters:**
- `email` (required): Email address to resend OTP
- `type` (optional): OTP type - `email_verification` or `password_reset` (default: `email_verification`)

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "message": "OTP resent successfully",
    "type": "email_verification",
    "expires_in_minutes": 10
  }
}
```

**Error Response (429 - Rate Limited):**
```json
{
  "success": false,
  "error": "Too many OTP requests. Please try again in X seconds."
}
```

### 4. Register (Updated)
**Endpoint:** `POST /api/register`

**Headers:**
```
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "email_verified_at": null,
      "is_email_verified": false,
      "created_at": "2025-01-25T09:00:00.000000Z",
      "updated_at": "2025-01-25T09:00:00.000000Z"
    },
    "access_token": "1|abcdef...",
    "token_type": "Bearer",
    "expires_at": "2025-02-25T09:00:00.000000Z"
  }
}
```

**Note:** After successful registration, an OTP will be automatically sent to the user's email for verification.

### 5. Get User Profile (Updated)
**Endpoint:** `GET /api/me`

**Headers:**
```
Content-Type: application/json
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "John Doe",
      "email": "john@example.com",
      "email_verified_at": null,
      "is_email_verified": false,
      "created_at": "2025-01-25T09:00:00.000000Z",
      "updated_at": "2025-01-25T09:00:00.000000Z"
    }
  }
}
```

**Note:** The response now includes `is_email_verified` flag. Check this field on the frontend to show/hide verification prompts.

## Authentication Flow

### Registration Flow
1. User registers via `POST /api/register`
2. User is created and access token is returned
3. OTP is automatically sent to user's email
4. User verifies OTP via `POST /api/verify-otp`
5. User's email is marked as verified

### Email Verification Flow
1. Frontend shows verification prompt if `is_email_verified` is false
2. User requests OTP via `POST /api/send-otp` or `POST /api/resend-otp`
3. User receives OTP in email
4. User verifies OTP via `POST /api/verify-otp`
5. User's email is verified

## Frontend Integration

### Check Email Verification Status
```javascript
const response = await fetch('/api/me', {
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
const data = await response.json();

if (data.data.user.is_email_verified === false) {
  // Show verification prompt
  showEmailVerificationPrompt();
}
```

### Send OTP
```javascript
const response = await fetch('/api/send-otp', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'user@example.com',
    type: 'email_verification'
  })
});
```

### Verify OTP
```javascript
const response = await fetch('/api/verify-otp', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    email: 'user@example.com',
    code: '123456',
    type: 'email_verification'
  })
});
```

## Queue Worker Setup

For OTP emails to be sent, the queue worker must be running:

### Development
```bash
php artisan queue:work --tries=3
```

### Production
```bash
php artisan queue:work --tries=3 --daemon
```

### Docker/Kubernetes
```bash
# Start queue worker as a separate process
php artisan queue:work --tries=3 --sleep=3 --timeout=90
```

## Rate Limiting

- **Limit:** 3 requests per minute per email
- **Throttle:** Applied via Laravel's throttle middleware
- **Tracking:** Cached per email and OTP type

## Security Notes

1. **OTP Storage:** OTP codes are stored in database, not in logs
2. **Expiration:** OTPs expire after configured minutes
3. **Attempts:** Max 5 verification attempts before OTP is invalidated
4. **Rate Limiting:** Prevents brute force attacks
5. **Cleanup:** Expired OTPs are automatically cleaned up

## Database Schema

### otp_codes Table
```sql
CREATE TABLE otp_codes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(6) NOT NULL,
    type ENUM('email_verification', 'password_reset') NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    attempts INT UNSIGNED DEFAULT 0,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX (email, type, expires_at),
    INDEX (code)
);
```

### users Table (Updated)
The `email_verified_at` column already exists in the users table (nullable timestamp).

## Testing

### Manual Testing Checklist
- [ ] Register user → OTP sent automatically
- [ ] Verify with correct code → `email_verified_at` updated
- [ ] Verify with wrong code → error
- [ ] Verify expired OTP → error
- [ ] Resend OTP before cooldown → error (429)
- [ ] Resend OTP after cooldown → new code sent
- [ ] Login with unverified email → success, `is_email_verified: false`
- [ ] Login with verified email → success, `is_email_verified: true`

## Error Codes

| Status Code | Description |
|-------------|-------------|
| 200 | Success |
| 201 | Created (registration) |
| 400 | Bad Request (invalid OTP) |
| 422 | Validation Error |
| 429 | Too Many Requests (rate limit exceeded) |
| 401 | Unauthorized |

## Common Issues

### Email Not Sending
1. Check `QUEUE_CONNECTION` in `.env` is set to `database`
2. Ensure queue worker is running: `php artisan queue:work`
3. Check Resend API key is valid
4. Check `MAIL_MAILER` is set to `resend`

### OTP Not Working
1. Verify OTP code is 6 digits
2. Check OTP hasn't expired (10 minutes)
3. Ensure email and type match
4. Check if max attempts (5) exceeded

### Rate Limiting Issues
1. Wait for cooldown period (1 minute)
2. Check `OTP_RATE_LIMIT_MAX` and `OTP_RATE_LIMIT_MINUTES` in config

## Future Enhancements

- Password reset implementation using OTP
- SMS OTP support
- Multi-language email templates
- Email template customization
- OTP logging for audit purposes
