# API Response Helper - Quick Reference

## Import
```php
use App\Helpers\ApiResponse;
```

## Success Responses

```php
// Simple success (200)
return ApiResponse::success();

// Success with data (200)
return ApiResponse::success(['user' => $user]);

// Success with message (200)
return ApiResponse::success($data, 'messages.success.saved');

// Success with custom status code (201)
return ApiResponse::success($data, 'messages.success.created', 201);
```

## Error Responses

```php
// Generic error (400)
return ApiResponse::error('messages.error.general');

// Error with custom code (422)
return ApiResponse::error('messages.error.invalid_data', 422);

// Error with additional details
return ApiResponse::error('messages.error.operation_failed', 400, $errorDetails);
```

## Validation Errors

```php
$validator = Validator::make($request->all(), $rules);

if ($validator->fails()) {
    return ApiResponse::validationError($validator->errors());
}

// With custom message
return ApiResponse::validationError($errors, 'custom.validation.message');
```

## HTTP Status Shortcuts

```php
// 401 Unauthorized
return ApiResponse::unauthorized();
return ApiResponse::unauthorized('custom.message');

// 403 Forbidden
return ApiResponse::forbidden();
return ApiResponse::forbidden('custom.message');

// 404 Not Found
return ApiResponse::notFound();
return ApiResponse::notFound('custom.message');

// 500 Server Error
return ApiResponse::serverError();
return ApiResponse::serverError('custom.message');
```

## Locale Detection

```php
// Detect locale from request
$locale = ApiResponse::detectLocale($request);

// Set locale from request
ApiResponse::setLocaleFromRequest($request);
```

## Response Format

All responses follow this structure:

```json
{
    "success": true,
    "message": "Translated message",
    "data": { ... },
    "locale": "en"
}
```

## Common Translation Keys

### Success Messages
- `messages.success.saved`
- `messages.success.created`
- `messages.success.updated`
- `messages.success.deleted`

### Error Messages
- `messages.error.general`
- `messages.error.validation`
- `messages.error.unauthorized`
- `messages.error.forbidden`
- `messages.error.not_found`
- `messages.error.server`

### Auth Messages
- `auth.login_success`
- `auth.logout_success`
- `auth.invalid_credentials`

## Client Usage

### JavaScript/Fetch
```javascript
// With Accept-Language header
fetch('/api/endpoint', {
    headers: {
        'Accept-Language': 'id',
        'Content-Type': 'application/json'
    }
});

// With locale parameter
fetch('/api/endpoint?locale=id');
```

### cURL
```bash
# With Accept-Language header
curl -H "Accept-Language: id" https://api.example.com/endpoint

# With locale parameter
curl https://api.example.com/endpoint?locale=id
```

## Locale Priority

1. `locale` parameter in request
2. `Accept-Language` HTTP header
3. Session locale
4. Default locale (en)

## Supported Locales

- `en` - English
- `id` - Indonesian (Bahasa Indonesia)
