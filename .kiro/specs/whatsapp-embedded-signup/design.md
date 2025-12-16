# Design Document: WhatsApp Embedded Signup v4

## Overview

This design document outlines the architecture and implementation approach for integrating WhatsApp Embedded Signup v4 into the QashierWise Laravel application. The integration enables multi-tenant WhatsApp messaging by allowing each user to connect their own WhatsApp Business account through Meta's OAuth-based Embedded Signup flow.

The solution replaces the current single-account configuration (hardcoded `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`) with a dynamic, per-user credential system stored in the database.

## Architecture

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Frontend (React/Vue)                            │
│  ┌─────────────────┐    ┌──────────────────┐    ┌─────────────────────────┐ │
│  │ Connect Button  │───▶│  Facebook SDK    │───▶│  Embedded Signup Dialog │ │
│  └─────────────────┘    └──────────────────┘    └─────────────────────────┘ │
│           │                                                │                 │
│           │                                                │ code            │
│           ▼                                                ▼                 │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │                    Callback Handler (JavaScript)                        ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ POST /api/whatsapp/embedded-signup/callback
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                              Backend (Laravel)                               │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │              EmbeddedSignupController                                   ││
│  │  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────┐ ││
│  │  │ exchangeToken() │  │ getWABAInfo()   │  │ storeCredentials()      │ ││
│  │  └─────────────────┘  └─────────────────┘  └─────────────────────────┘ ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                      │                                       │
│                                      ▼                                       │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │              EmbeddedSignupService                                      ││
│  │  - Exchange code for access token                                       ││
│  │  - Fetch WABA and Phone Number details                                  ││
│  │  - Generate System User Access Token                                    ││
│  └─────────────────────────────────────────────────────────────────────────┘│
│                                      │                                       │
│                                      ▼                                       │
│  ┌─────────────────────────────────────────────────────────────────────────┐│
│  │              WhatsAppAccount Model (Updated)                            ││
│  │  - user_id, phone_number_id, waba_id, access_token (encrypted)         ││
│  │  - display_name, verified_name, quality_rating, coexistence_enabled    ││
│  └─────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         Meta Graph API                                       │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐  │
│  │ OAuth Token     │  │ WABA Management │  │ WhatsApp Cloud API          │  │
│  │ Exchange        │  │ API             │  │ (Messaging)                 │  │
│  └─────────────────┘  └─────────────────┘  └─────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────────────┘
```

## Components and Interfaces

### 1. EmbeddedSignupController

New controller to handle Embedded Signup flow.

```php
class EmbeddedSignupController extends Controller
{
    // POST /api/whatsapp/embedded-signup/callback
    public function handleCallback(Request $request): JsonResponse
    
    // GET /api/whatsapp/embedded-signup/config
    public function getConfig(): JsonResponse
    
    // DELETE /api/whatsapp/account
    public function disconnect(): JsonResponse
    
    // GET /api/whatsapp/account
    public function getAccountStatus(): JsonResponse
}
```

### 2. EmbeddedSignupService

Service class encapsulating all Embedded Signup business logic.

```php
class EmbeddedSignupService
{
    // Exchange authorization code for access token
    public function exchangeCodeForToken(string $code): array
    
    // Get WABA details from access token
    public function getWABADetails(string $accessToken): array
    
    // Get phone number details
    public function getPhoneNumberDetails(string $accessToken, string $wabaId): array
    
    // Store or update WhatsApp account credentials
    public function storeCredentials(int $userId, array $credentials): WhatsAppAccount
    
    // Validate access token
    public function validateToken(string $accessToken): bool
}
```

### 3. WhatsAppAccountService (Updated)

Update existing WhatsApp functionality to use per-user credentials.

```php
class WhatsAppAccountService
{
    // Get WhatsApp client configured for specific user
    public function getClientForUser(int $userId): WhatsAppCloudApi
    
    // Get user's active WhatsApp account
    public function getActiveAccount(int $userId): ?WhatsAppAccount
    
    // Check if user has connected WhatsApp account
    public function hasConnectedAccount(int $userId): bool
}
```

### 4. Frontend Integration

JavaScript module for Facebook SDK integration.

```javascript
// whatsapp-embedded-signup.js
class WhatsAppEmbeddedSignup {
    constructor(config) {
        this.appId = config.appId;
        this.configId = config.configId;
        this.apiVersion = config.apiVersion;
    }
    
    initFacebookSDK(): Promise<void>
    launchSignup(): Promise<SignupResult>
    handleCallback(response): Promise<void>
}
```

## Data Models

### WhatsAppAccount Model (Updated Schema)

Add new fields to support Embedded Signup:

```php
// Migration: add_embedded_signup_fields_to_whatsapp_accounts
Schema::table('whatsapp_accounts', function (Blueprint $table) {
    $table->string('waba_id')->nullable()->after('business_account_id');
    $table->boolean('coexistence_enabled')->default(false)->after('is_active');
    $table->timestamp('token_expires_at')->nullable()->after('access_token');
    $table->string('connection_method')->default('manual')->after('coexistence_enabled');
    // connection_method: 'manual' | 'embedded_signup'
});
```

Updated Model:

```php
class WhatsAppAccount extends Model
{
    protected $fillable = [
        'user_id',
        'phone_number_id',
        'business_account_id',
        'waba_id',
        'access_token',
        'is_active',
        'coexistence_enabled',
        'connection_method',
        'token_expires_at',
        'webhook_config',
        'display_name',
        'quality_rating',
        'verified_name',
        // ... existing fields
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'coexistence_enabled' => 'boolean',
        'webhook_config' => 'array',
        'websites' => 'array',
        'token_expires_at' => 'datetime',
        'access_token' => 'encrypted', // Laravel encryption
    ];
}
```

### Configuration Model

```php
// config/whatsapp.php (updated)
return [
    'api_version' => env('WHATSAPP_API_VERSION', 'v22.0'),
    
    // Embedded Signup Configuration
    'embedded_signup' => [
        'app_id' => env('WHATSAPP_APP_ID'),
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'config_id' => env('WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'redirect_uri' => env('WHATSAPP_EMBEDDED_SIGNUP_REDIRECT_URI'),
        'es_version' => 'v4', // For coexistence support
    ],
    
    // Legacy single-account config (deprecated, for backward compatibility)
    'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
    'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    
    // ... rest of config
];
```



## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

Based on the acceptance criteria analysis, the following correctness properties must be validated:

### Property 1: Credential Storage Completeness
*For any* valid credentials response from Meta API containing phone_number_id, waba_id, access_token, display_name, verified_name, and quality_rating, storing these credentials SHALL result in a WhatsAppAccount record containing all these fields with correct values.
**Validates: Requirements 2.1, 2.4**

### Property 2: Access Token Encryption
*For any* access token string stored in the database, the raw database value SHALL NOT equal the original plaintext token (encryption is applied).
**Validates: Requirements 2.2**

### Property 3: Upsert Prevents Duplicates
*For any* user who already has a WhatsAppAccount record, storing new credentials SHALL result in exactly one WhatsAppAccount record for that user (update, not insert).
**Validates: Requirements 2.3**

### Property 4: Dynamic Credential Usage
*For any* authenticated user with a connected WhatsApp account, creating a WhatsApp client SHALL use the user's stored phone_number_id and access_token, not the global configuration values.
**Validates: Requirements 3.1, 3.3**

### Property 5: Missing Account Error
*For any* authenticated user without a connected WhatsApp account, attempting to send a message SHALL return an error with a specific error code indicating account connection is required.
**Validates: Requirements 3.2**

### Property 6: Invalid Token Error
*For any* WhatsApp API call with an expired or invalid access token, the system SHALL return an error indicating re-authentication is required.
**Validates: Requirements 3.4**

### Property 7: Account Status Completeness
*For any* user with a connected WhatsApp account, retrieving account status SHALL return phone_number, display_name, verified_name, quality_rating, is_active, and coexistence_enabled fields.
**Validates: Requirements 4.1, 4.3**

### Property 8: Disconnect Deactivates Account
*For any* connected WhatsApp account, calling disconnect SHALL set is_active to false and the account SHALL no longer be used for messaging.
**Validates: Requirements 4.2**

### Property 9: Webhook Routing Correctness
*For any* incoming webhook payload containing a phone_number_id, the system SHALL correctly identify the user whose WhatsAppAccount has that phone_number_id.
**Validates: Requirements 5.1**

### Property 10: Unknown Phone Number Handling
*For any* webhook payload with a phone_number_id that does not match any stored WhatsAppAccount, the system SHALL skip processing and not create any message records.
**Validates: Requirements 5.2**

### Property 11: Message Isolation
*For any* two distinct users with connected WhatsApp accounts, messages belonging to user A SHALL never be visible to user B when querying messages.
**Validates: Requirements 5.4**

### Property 12: Missing Config Disables Feature
*For any* application startup where WHATSAPP_APP_ID, WHATSAPP_APP_SECRET, or WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID is missing, the Embedded Signup feature SHALL be disabled.
**Validates: Requirements 6.2**

### Property 13: Coexistence Flag Persistence
*For any* WhatsApp account connected via Embedded Signup v4, the coexistence_enabled flag SHALL be stored as true and SHALL be included in account status responses.
**Validates: Requirements 8.2, 8.3**

## Error Handling

### API Error Responses

| Error Code | HTTP Status | Description | User Action |
|------------|-------------|-------------|-------------|
| `WHATSAPP_NOT_CONNECTED` | 400 | User has no connected WhatsApp account | Connect WhatsApp account |
| `WHATSAPP_TOKEN_EXPIRED` | 401 | Access token has expired | Re-authenticate via Embedded Signup |
| `WHATSAPP_TOKEN_INVALID` | 401 | Access token is invalid | Re-authenticate via Embedded Signup |
| `EMBEDDED_SIGNUP_DISABLED` | 503 | Embedded Signup feature is disabled | Contact administrator |
| `CODE_EXCHANGE_FAILED` | 400 | Failed to exchange code for token | Retry signup flow |
| `WABA_FETCH_FAILED` | 500 | Failed to fetch WABA details | Retry signup flow |

### Exception Handling Strategy

```php
// Custom exceptions
class WhatsAppNotConnectedException extends Exception {}
class WhatsAppTokenExpiredException extends Exception {}
class WhatsAppTokenInvalidException extends Exception {}
class EmbeddedSignupDisabledException extends Exception {}

// Global exception handler additions
class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($e instanceof WhatsAppNotConnectedException) {
            return response()->json([
                'success' => false,
                'error_code' => 'WHATSAPP_NOT_CONNECTED',
                'message' => 'Please connect your WhatsApp Business account first.',
            ], 400);
        }
        // ... other handlers
    }
}
```

## Testing Strategy

### Dual Testing Approach

This feature requires both unit tests and property-based tests to ensure correctness:

#### Unit Tests
- Test specific examples of the Embedded Signup flow
- Test edge cases like empty responses, malformed data
- Test integration points with Meta Graph API (mocked)
- Test database operations for credential storage

#### Property-Based Testing

**Library:** PHPUnit with `spatie/phpunit-snapshot-assertions` for data validation, or custom generators using `fakerphp/faker` for property-based testing patterns.

For PHP/Laravel, we will implement property-based testing using:
- Custom test data generators with Faker
- Parameterized tests with data providers
- Minimum 100 iterations per property test

**Property Test Annotations:**
Each property-based test MUST be tagged with:
```php
/**
 * @test
 * Feature: whatsapp-embedded-signup, Property 1: Credential Storage Completeness
 * Validates: Requirements 2.1, 2.4
 */
```

### Test Categories

1. **EmbeddedSignupService Tests**
   - Token exchange logic
   - WABA details fetching
   - Credential storage

2. **WhatsAppAccountService Tests**
   - Dynamic client creation
   - Account lookup
   - Multi-account handling

3. **Webhook Routing Tests**
   - Phone number ID matching
   - User isolation
   - Unknown number handling

4. **Integration Tests**
   - Full signup flow (mocked Meta API)
   - Message sending with user credentials
   - Account disconnect flow

### Test Data Generators

```php
class WhatsAppTestDataGenerator
{
    public function generateCredentials(): array
    {
        return [
            'phone_number_id' => $this->faker->numerify('##################'),
            'waba_id' => $this->faker->numerify('##################'),
            'access_token' => 'EAAG' . $this->faker->regexify('[A-Za-z0-9]{100}'),
            'display_name' => $this->faker->company,
            'verified_name' => $this->faker->company,
            'quality_rating' => $this->faker->randomElement(['GREEN', 'YELLOW', 'RED']),
        ];
    }
    
    public function generateWebhookPayload(string $phoneNumberId): array
    {
        return [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => $phoneNumberId],
                        'messages' => [[
                            'from' => $this->faker->numerify('############'),
                            'text' => ['body' => $this->faker->sentence],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
```


## Frontend Implementation Details

### Facebook SDK Integration

#### Option 1: Asynchronous Loading (Recommended)

```html
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId            : '1484241559512577',
      autoLogAppEvents : true,
      xfbml            : true,
      version          : 'v24.0'
    });
  };

  // Load the JavaScript SDK asynchronously
  (function (d, s, id) {
    var js, fjs = d.getElementsByTagName(s)[0];
    if (d.getElementById(id)) return;
    js = d.createElement(s); js.id = id;
    js.src = "https://connect.facebook.net/en_US/sdk.js";
    fjs.parentNode.insertBefore(js, fjs);
  }(document, 'script', 'facebook-jssdk'));
</script>
```

#### Option 2: Synchronous Loading

```html
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId            : '1484241559512577',
      autoLogAppEvents : true,
      xfbml            : true,
      version          : 'v24.0'
    });
  };
</script>
<script async defer crossorigin="anonymous"
  src="https://connect.facebook.net/en_US/sdk.js">
</script>
```

### Session Info Message Listener

Listen for messages from the Embedded Signup iframe to capture session information:

```javascript
window.addEventListener('message', (event) => {
  // Only accept messages from Facebook
  if (event.origin !== "https://www.facebook.com" && event.origin !== "https://web.facebook.com") {
    return;
  }
  
  try {
    const data = JSON.parse(event.data);
    if (data.type === 'WA_EMBEDDED_SIGNUP') {
      // Handle different event types
      if (data.event === 'FINISH') {
        // User completed signup
        const { phone_number_id, waba_id } = data.data;
        console.log('Signup completed:', { phone_number_id, waba_id });
        handleSignupComplete(data.data);
      } else if (data.event === 'CANCEL') {
        // User cancelled signup
        console.log('Signup cancelled');
        handleSignupCancel();
      } else if (data.event === 'ERROR') {
        // Error occurred
        console.error('Signup error:', data.data);
        handleSignupError(data.data);
      }
    }
  } catch (e) {
    // Not a JSON message or not from embedded signup
    console.debug('Non-ES message received:', e);
  }
});
```

### Launch Signup Button

```html
<button 
  onclick="launchWhatsAppSignup()" 
  style="background-color: #1877f2; border: 0; border-radius: 4px; color: #fff; cursor: pointer; font-family: Helvetica, Arial, sans-serif; font-size: 16px; font-weight: bold; height: 40px; padding: 0 24px;">
  Login with Facebook
</button>
```

### Complete Launch Function

```javascript
function launchWhatsAppSignup() {
  // Check if FB SDK is loaded
  if (typeof FB === 'undefined') {
    console.error('Facebook SDK not loaded');
    alert('Facebook SDK is not loaded. Please refresh the page.');
    return;
  }

  // Launch the Embedded Signup flow
  FB.login(function(response) {
    if (response.authResponse) {
      const code = response.authResponse.code;
      
      // Send code to backend for token exchange
      sendCodeToBackend(code);
    } else {
      console.log('User cancelled login or did not fully authorize.');
      handleSignupCancel();
    }
  }, {
    config_id: '828935343106633', // ES Config ID
    response_type: 'code',        // Get authorization code
    override_default_response_type: true,
    extras: {
      setup: {
        // ES v4 specific settings for coexistence
        // solutionID is optional - for tracking purposes
      },
      featureType: '',
      sessionInfoVersion: '3',    // Latest session info version
    }
  });
}

async function sendCodeToBackend(code) {
  try {
    // Show loading state
    showLoading(true);
    
    const response = await fetch('/api/whatsapp/embedded-signup/callback', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${getAuthToken()}`, // User's auth token
        'Accept': 'application/json',
      },
      body: JSON.stringify({ code }),
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Show success message
      showSuccess(`WhatsApp connected: ${data.data.display_name}`);
      // Refresh account status
      refreshAccountStatus();
    } else {
      showError(data.message || 'Failed to connect WhatsApp account');
    }
  } catch (error) {
    console.error('Error sending code to backend:', error);
    showError('Network error. Please try again.');
  } finally {
    showLoading(false);
  }
}

function handleSignupComplete(data) {
  console.log('Embedded Signup completed with data:', data);
  // Additional handling if needed
}

function handleSignupCancel() {
  showMessage('WhatsApp connection cancelled.');
}

function handleSignupError(errorData) {
  showError(`Connection failed: ${errorData.error_message || 'Unknown error'}`);
}
```

### React/Vue Component Example

```jsx
// React Component: WhatsAppConnectButton.jsx
import { useEffect, useState } from 'react';

export default function WhatsAppConnectButton({ onSuccess, onError }) {
  const [loading, setLoading] = useState(false);
  const [sdkLoaded, setSdkLoaded] = useState(false);

  useEffect(() => {
    // Initialize Facebook SDK
    window.fbAsyncInit = function() {
      FB.init({
        appId: '1484241559512577',
        autoLogAppEvents: true,
        xfbml: true,
        version: 'v24.0'
      });
      setSdkLoaded(true);
    };

    // Load SDK script
    (function(d, s, id) {
      var js, fjs = d.getElementsByTagName(s)[0];
      if (d.getElementById(id)) return;
      js = d.createElement(s);
      js.id = id;
      js.src = "https://connect.facebook.net/en_US/sdk.js";
      fjs.parentNode.insertBefore(js, fjs);
    }(document, 'script', 'facebook-jssdk'));

    // Listen for session info messages
    const handleMessage = (event) => {
      if (event.origin !== "https://www.facebook.com" && 
          event.origin !== "https://web.facebook.com") return;
      
      try {
        const data = JSON.parse(event.data);
        if (data.type === 'WA_EMBEDDED_SIGNUP') {
          if (data.event === 'FINISH') {
            console.log('ES Session Info:', data.data);
          }
        }
      } catch (e) {
        // Ignore non-JSON messages
      }
    };

    window.addEventListener('message', handleMessage);
    return () => window.removeEventListener('message', handleMessage);
  }, []);

  const handleConnect = () => {
    if (!sdkLoaded) {
      alert('Facebook SDK is loading. Please wait.');
      return;
    }

    setLoading(true);

    FB.login(async (response) => {
      if (response.authResponse) {
        try {
          const result = await fetch('/api/whatsapp/embedded-signup/callback', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'Authorization': `Bearer ${localStorage.getItem('token')}`,
            },
            body: JSON.stringify({ code: response.authResponse.code }),
          });
          
          const data = await result.json();
          
          if (data.success) {
            onSuccess?.(data.data);
          } else {
            onError?.(data.message);
          }
        } catch (error) {
          onError?.(error.message);
        }
      } else {
        console.log('User cancelled');
      }
      setLoading(false);
    }, {
      config_id: '828935343106633',
      response_type: 'code',
      override_default_response_type: true,
      extras: {
        sessionInfoVersion: '3',
      }
    });
  };

  return (
    <button
      onClick={handleConnect}
      disabled={loading || !sdkLoaded}
      className="bg-[#1877f2] text-white font-bold py-2 px-6 rounded hover:bg-[#166fe5] disabled:opacity-50"
    >
      {loading ? 'Connecting...' : 'Connect WhatsApp'}
    </button>
  );
}
```

### Backend Token Exchange Flow

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Frontend      │     │   Laravel API   │     │  Meta Graph API │
│                 │     │                 │     │                 │
│  FB.login()     │     │                 │     │                 │
│       │         │     │                 │     │                 │
│       ▼         │     │                 │     │                 │
│  Get code       │────▶│ POST /callback  │     │                 │
│                 │     │       │         │     │                 │
│                 │     │       ▼         │     │                 │
│                 │     │ Exchange code   │────▶│ /oauth/access   │
│                 │     │                 │◀────│ _token          │
│                 │     │       │         │     │                 │
│                 │     │       ▼         │     │                 │
│                 │     │ Get WABA info   │────▶│ /debug_token    │
│                 │     │                 │◀────│ /WABA_ID        │
│                 │     │       │         │     │                 │
│                 │     │       ▼         │     │                 │
│                 │     │ Get phone info  │────▶│ /phone_numbers  │
│                 │     │                 │◀────│                 │
│                 │     │       │         │     │                 │
│                 │     │       ▼         │     │                 │
│                 │     │ Store in DB     │     │                 │
│  Success        │◀────│                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘
```
