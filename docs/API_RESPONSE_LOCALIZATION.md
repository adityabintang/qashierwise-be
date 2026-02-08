# API Response Localization Guide

## Overview

The `ApiResponse` helper class provides a standardized way to return localized JSON responses from API endpoints. It automatically handles locale detection from multiple sources and includes locale information in responses.

## Features

- **Automatic Locale Detection**: Detects user's preferred language from multiple sources
- **Translation Support**: All messages are automatically translated using Laravel's localization system
- **Consistent Format**: Standardized response structure across all API endpoints
- **Locale Information**: Includes current locale in all responses
- **Convenience Methods**: Pre-built methods for common response types

## Usage

### Basic Success Response

```php
use App\Helpers\ApiResponse;

// Simple success response
return ApiResponse::success();

// Success with data
return ApiResponse::success([
    'user' => $user,
    'token' => $token
]);

// Success with data and message
return ApiResponse::success($data, 'messages.success.created', 201);
```

**Response Format:**
```json
{
    "success": true,
    "message": "Created successfully",
    "data": { ... },
    "locale": "en"
}
```

### Error Responses

```php
// Generic error
return ApiResponse::error('messages.error.general', 400);

// Error with additional details
return ApiResponse::error('messages.error.operation_failed', 400, [
    'field' => 'Additional error details'
]);
```

**Response Format:**
```json
{
    "success": false,
    "message": "An error occurred. Please try again.",
    "errors": { ... },
    "locale": "en"
}
```

### Validation Errors

```php
$validator = Validator::make($request->all(), $rules);

if ($validator->fails()) {
    return ApiResponse::validationError($validator->errors());
}
```

**Response Format:**
```json
{
    "success": false,
    "message": "Please fix the validation errors",
    "errors": {
        "email": ["The email field is required."]
    },
    "locale": "en"
}
```

### HTTP Status Convenience Methods

```php
// 401 Unauthorized
return ApiResponse::unauthorized();
return ApiResponse::unauthorized('custom.message.key');

// 403 Forbidden
return ApiResponse::forbidden();
return ApiResponse::forbidden('custom.message.key');

// 404 Not Found
return ApiResponse::notFound();
return ApiResponse::notFound('custom.message.key');

// 500 Server Error
return ApiResponse::serverError();
return ApiResponse::serverError('custom.message.key');
```

## Locale Detection

The `ApiResponse` class detects the user's preferred locale in the following priority order:

1. **Explicit `locale` parameter** in the request
2. **Accept-Language header** in the HTTP request
3. **Session locale** (if available)
4. **Default locale** from configuration

### Setting Locale from Request

You can manually set the locale for an API request:

```php
use App\Helpers\ApiResponse;

public function index(Request $request)
{
    // Set locale based on request
    ApiResponse::setLocaleFromRequest($request);
    
    // Your logic here
    return ApiResponse::success($data);
}
```

**Note:** The `LocalizationMiddleware` already handles this automatically for all API routes, so manual setting is usually not necessary.

## Accept-Language Header

Clients can specify their preferred language using the `Accept-Language` header:

```bash
# Request in English
curl -H "Accept-Language: en" https://api.example.com/endpoint

# Request in Indonesian
curl -H "Accept-Language: id" https://api.example.com/endpoint

# Request with quality values
curl -H "Accept-Language: id,en;q=0.9" https://api.example.com/endpoint
```

## Translation Keys

All messages should use translation keys from the language files:

### Common Message Keys

```php
// Success messages
'messages.success.saved'
'messages.success.created'
'messages.success.updated'
'messages.success.deleted'

// Error messages
'messages.error.general'
'messages.error.validation'
'messages.error.unauthorized'
'messages.error.forbidden'
'messages.error.not_found'
'messages.error.server'

// Auth messages
'auth.login_success'
'auth.logout_success'
'auth.invalid_credentials'

// Payment messages
'payments.qris_generated'
'payments.no_active_provider'
'payments.provider_invalid'
```

### Creating New Translation Keys

When adding new API responses, add the translation keys to both language files:

**resources/lang/en/messages.php:**
```php
'success' => [
    'your_action' => 'Your action completed successfully',
],
```

**resources/lang/id/messages.php:**
```php
'success' => [
    'your_action' => 'Tindakan Anda berhasil diselesaikan',
],
```

## Migration Guide

### Before (Old Style)

```php
public function store(Request $request)
{
    $validator = Validator::make($request->all(), $rules);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }
    
    $item = Item::create($request->all());
    
    return response()->json([
        'success' => true,
        'message' => 'Item created successfully',
        'data' => $item
    ], 201);
}
```

### After (New Style with ApiResponse)

```php
use App\Helpers\ApiResponse;

public function store(Request $request)
{
    $validator = Validator::make($request->all(), $rules);
    
    if ($validator->fails()) {
        return ApiResponse::validationError($validator->errors());
    }
    
    $item = Item::create($request->all());
    
    return ApiResponse::success($item, 'messages.success.created', 201);
}
```

## Benefits

1. **Consistency**: All API responses follow the same structure
2. **Localization**: Automatic translation based on user preference
3. **Maintainability**: Centralized response handling
4. **Flexibility**: Easy to extend with new response types
5. **Standards**: Follows REST API best practices
6. **Debugging**: Locale information included in every response

## Testing

### Testing with Different Locales

```php
// Test with English
$response = $this->withHeaders([
    'Accept-Language' => 'en'
])->postJson('/api/endpoint', $data);

$response->assertJson([
    'locale' => 'en',
    'message' => 'Created successfully'
]);

// Test with Indonesian
$response = $this->withHeaders([
    'Accept-Language' => 'id'
])->postJson('/api/endpoint', $data);

$response->assertJson([
    'locale' => 'id',
    'message' => 'Berhasil dibuat'
]);
```

### Testing with Locale Parameter

```php
$response = $this->postJson('/api/endpoint?locale=id', $data);

$response->assertJson([
    'locale' => 'id'
]);
```

## Best Practices

1. **Always use translation keys** instead of hardcoded messages
2. **Use appropriate HTTP status codes** (200, 201, 400, 401, 403, 404, 500)
3. **Include relevant data** in success responses
4. **Provide detailed errors** in validation responses
5. **Keep messages user-friendly** and actionable
6. **Test with multiple locales** to ensure translations work correctly
7. **Document new translation keys** when adding them

## Examples

### Complete CRUD Controller

```php
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ItemController extends Controller
{
    public function index()
    {
        $items = Item::all();
        return ApiResponse::success($items);
    }
    
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }
        
        $item = Item::create($request->all());
        return ApiResponse::success($item, 'messages.success.created', 201);
    }
    
    public function show($id)
    {
        $item = Item::find($id);
        
        if (!$item) {
            return ApiResponse::notFound('messages.error.not_found');
        }
        
        return ApiResponse::success($item);
    }
    
    public function update(Request $request, $id)
    {
        $item = Item::find($id);
        
        if (!$item) {
            return ApiResponse::notFound('messages.error.not_found');
        }
        
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
        ]);
        
        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }
        
        $item->update($request->all());
        return ApiResponse::success($item, 'messages.success.updated');
    }
    
    public function destroy($id)
    {
        $item = Item::find($id);
        
        if (!$item) {
            return ApiResponse::notFound('messages.error.not_found');
        }
        
        $item->delete();
        return ApiResponse::success(null, 'messages.success.deleted');
    }
}
```

## Requirements Validation

This implementation satisfies the following requirements:

- **Requirement 13.1**: API returns validation errors translated based on Accept-Language header
- **Requirement 13.2**: API returns success messages with translations
- **Requirement 13.3**: Locale information included in API responses
- **Requirement 13.4**: API endpoints accept locale parameter for response localization
- **Requirement 13.5**: English is maintained as default for API responses when no locale is specified
