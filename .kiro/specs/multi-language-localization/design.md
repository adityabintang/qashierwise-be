# Design Document: Multi-Language Localization

## Overview

This design implements comprehensive multi-language support for the entire application, supporting English (EN) and Indonesian (ID). The system uses Laravel's localization framework with organized translation files, a language switcher component, and middleware to manage user language preferences. All user-facing text, including landing page, dashboard, authentication, payments, and notifications, will be translated.

## Architecture

### High-Level Flow

```
User Request
    ↓
Detect User Language (Session/Profile/Header)
    ↓
Set Application Locale
    ↓
Load Translation Files
    ↓
Render Content with Translations
    ↓
Store Language Preference
```

### Component Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    Application Layer                         │
│  (Controllers, Views, API Endpoints)                         │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│           LocalizationMiddleware                             │
│  - Detect user language preference                           │
│  - Set application locale                                    │
└────────────────────┬────────────────────────────────────────┘
                     │
┌────────────────────▼────────────────────────────────────────┐
│         Translation File Loader                              │
│  - Load resources/lang/{locale}/*.php files                  │
│  - Cache translations for performance                        │
└────────────────────┬────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                         │
┌───────▼──────────┐    ┌────────▼──────────┐
│ resources/lang/en│    │ resources/lang/id │
│ - auth.php       │    │ - auth.php        │
│ - dashboard.php  │    │ - dashboard.php   │
│ - messages.php   │    │ - messages.php    │
│ - etc.           │    │ - etc.            │
└───────┬──────────┘    └────────┬──────────┘
        │                        │
        └────────────┬───────────┘
                     │
        ┌────────────▼────────────┐
        │  __() / trans() Helper  │
        │  - Retrieve translations│
        │  - Format with params   │
        └────────────┬────────────┘
                     │
        ┌────────────▼────────────┐
        │  Rendered Content       │
        │  (HTML/JSON/Email)      │
        └────────────────────────┘
```

## Components and Interfaces

### 1. Translation File Structure

```
resources/
├── lang/
│   ├── en/
│   │   ├── auth.php
│   │   ├── dashboard.php
│   │   ├── messages.php
│   │   ├── payments.php
│   │   ├── pos.php
│   │   ├── whatsapp.php
│   │   ├── ai_agent.php
│   │   ├── subscription.php
│   │   ├── submerchant.php
│   │   ├── landing.php
│   │   ├── common.php
│   │   └── validation.php
│   └── id/
│       ├── auth.php
│       ├── dashboard.php
│       ├── messages.php
│       ├── payments.php
│       ├── pos.php
│       ├── whatsapp.php
│       ├── ai_agent.php
│       ├── subscription.php
│       ├── submerchant.php
│       ├── landing.php
│       ├── common.php
│       └── validation.php
```

### 2. Sample Translation Files

**resources/lang/en/auth.php**
```php
return [
    'login' => 'Login',
    'register' => 'Register',
    'email' => 'Email Address',
    'password' => 'Password',
    'remember_me' => 'Remember Me',
    'forgot_password' => 'Forgot Your Password?',
    'sign_in' => 'Sign In',
    'create_account' => 'Create Account',
    'already_have_account' => 'Already have an account?',
    'dont_have_account' => "Don't have an account?",
    'invalid_credentials' => 'Invalid email or password',
    'email_verified' => 'Email verified successfully',
    'password_reset_sent' => 'Password reset link sent to your email',
];
```

**resources/lang/id/auth.php**
```php
return [
    'login' => 'Masuk',
    'register' => 'Daftar',
    'email' => 'Alamat Email',
    'password' => 'Kata Sandi',
    'remember_me' => 'Ingat Saya',
    'forgot_password' => 'Lupa Kata Sandi?',
    'sign_in' => 'Masuk',
    'create_account' => 'Buat Akun',
    'already_have_account' => 'Sudah punya akun?',
    'dont_have_account' => 'Belum punya akun?',
    'invalid_credentials' => 'Email atau kata sandi tidak valid',
    'email_verified' => 'Email berhasil diverifikasi',
    'password_reset_sent' => 'Link reset kata sandi telah dikirim ke email Anda',
];
```

### 3. LocalizationMiddleware

```php
class LocalizationMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Check user preference (if authenticated)
        if (auth()->check()) {
            $locale = auth()->user()->language_preference ?? config('app.locale');
        }
        // 2. Check session
        elseif (session()->has('locale')) {
            $locale = session('locale');
        }
        // 3. Check Accept-Language header
        elseif ($request->hasHeader('Accept-Language')) {
            $locale = $this->parseAcceptLanguage($request->header('Accept-Language'));
        }
        // 4. Use default
        else {
            $locale = config('app.locale');
        }

        // Validate locale is supported
        if (!in_array($locale, config('app.supported_locales'))) {
            $locale = config('app.locale');
        }

        // Set application locale
        app()->setLocale($locale);
        session(['locale' => $locale]);

        return $next($request);
    }

    private function parseAcceptLanguage(string $header): string
    {
        $locales = [];
        foreach (explode(',', $header) as $locale) {
            $parts = explode(';', $locale);
            $locales[] = trim($parts[0]);
        }

        foreach ($locales as $locale) {
            $lang = explode('-', $locale)[0];
            if (in_array($lang, config('app.supported_locales'))) {
                return $lang;
            }
        }

        return config('app.locale');
    }
}
```

### 4. LanguageSwitcher Component

```php
// app/View/Components/LanguageSwitcher.php
class LanguageSwitcher extends Component
{
    public array $languages;
    public string $currentLocale;

    public function __construct()
    {
        $this->languages = [
            'en' => 'English',
            'id' => 'Bahasa Indonesia',
        ];
        $this->currentLocale = app()->getLocale();
    }

    public function render()
    {
        return view('components.language-switcher');
    }

    public function switchLanguage(string $locale)
    {
        if (!in_array($locale, config('app.supported_locales'))) {
            abort(400);
        }

        session(['locale' => $locale]);

        if (auth()->check()) {
            auth()->user()->update(['language_preference' => $locale]);
        }

        return redirect()->back();
    }
}
```

**resources/views/components/language-switcher.blade.php**
```blade
<div class="language-switcher">
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button">
            {{ $languages[$currentLocale] }}
        </button>
        <div class="dropdown-menu">
            @foreach($languages as $locale => $name)
                <a class="dropdown-item {{ $locale === $currentLocale ? 'active' : '' }}"
                   href="{{ route('language.switch', $locale) }}">
                    {{ $name }}
                </a>
            @endforeach
        </div>
    </div>
</div>
```

### 5. Language Switching Route

```php
// routes/web.php
Route::get('/language/{locale}', function ($locale) {
    if (!in_array($locale, config('app.supported_locales'))) {
        abort(400);
    }

    session(['locale' => $locale]);

    if (auth()->check()) {
        auth()->user()->update(['language_preference' => $locale]);
    }

    return redirect()->back();
})->name('language.switch');
```

### 6. Configuration

**config/app.php**
```php
return [
    'locale' => 'en',
    'fallback_locale' => 'en',
    'supported_locales' => ['en', 'id'],
    'faker_locale' => 'en_US',
];
```

### 7. User Model Enhancement

```php
class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
        'language_preference',
    ];

    protected $casts = [
        'language_preference' => 'string',
    ];

    public function getLanguagePreferenceAttribute($value)
    {
        return $value ?? config('app.locale');
    }
}
```

### 8. Helper Functions

```php
// app/Helpers/LocalizationHelper.php
class LocalizationHelper
{
    /**
     * Get translated string with parameters
     */
    public static function trans(string $key, array $params = []): string
    {
        return __($key, $params);
    }

    /**
     * Get current locale
     */
    public static function getCurrentLocale(): string
    {
        return app()->getLocale();
    }

    /**
     * Check if locale is supported
     */
    public static function isLocaleSupported(string $locale): bool
    {
        return in_array($locale, config('app.supported_locales'));
    }

    /**
     * Get all supported locales
     */
    public static function getSupportedLocales(): array
    {
        return config('app.supported_locales');
    }

    /**
     * Format date according to locale
     */
    public static function formatDate($date, string $format = 'medium'): string
    {
        return Carbon::parse($date)->locale(app()->getLocale())->format($format);
    }

    /**
     * Format currency
     */
    public static function formatCurrency(int $amount): string
    {
        return 'Rp ' . number_format($amount, 0, ',', '.');
    }
}
```

### 9. Blade Directives

```php
// app/Providers/AppServiceProvider.php
public function boot()
{
    Blade::directive('trans', function ($expression) {
        return "<?php echo __($expression); ?>";
    });

    Blade::directive('locale', function () {
        return "<?php echo app()->getLocale(); ?>";
    });
}
```

### 10. API Response Localization

```php
class ApiResponse
{
    public static function success($data = null, string $message = null, int $code = 200)
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message ? __($message) : null,
        ], $code);
    }

    public static function error(string $message, int $code = 400, $errors = null)
    {
        return response()->json([
            'success' => false,
            'message' => __($message),
            'errors' => $errors,
        ], $code);
    }
}
```

## Data Models

### User Table Enhancement

```sql
ALTER TABLE users ADD COLUMN (
    language_preference VARCHAR(5) DEFAULT 'en'
);
```

## Translation Coverage

### Core Translation Files

1. **auth.php** - Login, registration, password reset
2. **dashboard.php** - Dashboard menu, titles, labels
3. **messages.php** - WhatsApp messages, status labels
4. **payments.php** - QRIS, payment status, transactions
5. **pos.php** - POS menu, products, orders, tables
6. **whatsapp.php** - WhatsApp account, templates, contacts
7. **ai_agent.php** - AI agent configuration, status
8. **subscription.php** - Plans, billing, status
9. **submerchant.php** - Merchant management, balance
10. **landing.php** - Landing page content, features, pricing
11. **common.php** - Common buttons, labels, messages
12. **validation.php** - Form validation messages

## Error Handling

### Missing Translation Handling

```php
// config/logging.php - Log missing translations
'channels' => [
    'missing_translations' => [
        'driver' => 'single',
        'path' => storage_path('logs/missing_translations.log'),
    ],
];

// app/Providers/AppServiceProvider.php
if (app()->isLocal()) {
    \Illuminate\Support\Facades\Lang::handleMissingKeysUsing(function ($key) {
        \Log::channel('missing_translations')->warning("Missing translation: {$key}");
        return $key;
    });
}
```

## Testing Strategy

### Unit Tests
- Test LocalizationMiddleware locale detection
- Test language preference persistence
- Test translation file loading
- Test helper functions (formatDate, formatCurrency)
- Test API response localization
- Test missing translation handling

### Property-Based Tests
- **Property 1: Locale Consistency** - For any supported locale, setting it should persist across requests
- **Property 2: Translation Key Retrieval** - For any valid translation key, __() should return non-empty string
- **Property 3: Date Formatting** - For any date, formatting should produce valid output in selected locale
- **Property 4: Currency Formatting** - For any amount, formatting should produce valid Rupiah format

### Integration Tests
- Test full language switching flow
- Test translation loading for all features
- Test API responses in different locales
- Test email sending in user's preferred language
- Test landing page in both languages

## Correctness Properties

A property is a characteristic or behavior that should hold true across all valid executions of a system—essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.

### Property 1: Locale Persistence

**For any** supported locale selected by a user, the application should maintain that locale across subsequent requests until changed.

**Validates: Requirements 2.3, 2.4, 2.5**

### Property 2: Translation Key Resolution

**For any** valid translation key in the current locale, the __() helper should return a non-empty translated string.

**Validates: Requirements 1.1, 1.2, 1.3**

### Property 3: Fallback to English

**For any** missing translation key in Indonesian locale, the system should fall back to English translation.

**Validates: Requirements 1.4, 1.5**

### Property 4: User Preference Persistence

**For any** authenticated user who selects a language, that preference should be saved to their profile and restored on next login.

**Validates: Requirements 2.4, 2.5**

### Property 5: Date Formatting Consistency

**For any** date value, formatting it in the same locale multiple times should produce identical output.

**Validates: Requirements 12.1, 12.2**

### Property 6: Currency Formatting Correctness

**For any** numeric amount, formatting as currency should produce valid Rupiah format (Rp X.XXX,XX).

**Validates: Requirements 12.2, 12.3**

### Property 7: API Response Localization

**For any** API request with Accept-Language header, the response messages should be in the requested language.

**Validates: Requirements 13.1, 13.2, 13.3**

### Property 8: Complete Translation Coverage

**For any** user-facing text in the application, there should be a translation key available in both EN and ID locales.

**Validates: Requirements 15.1, 15.5**

### Property 9: Landing Page Language Switching

**For any** visitor on the landing page, selecting a language should update all visible content to that language.

**Validates: Requirements 15.8, 15.9**

### Property 10: Email Localization

**For any** notification email sent to a user, the email content should be in the user's preferred language.

**Validates: Requirements 14.1, 14.2, 14.3**

## Implementation Phases

### Phase 1: Foundation
- Create translation file structure
- Implement LocalizationMiddleware
- Add language_preference to users table
- Create LanguageSwitcher component

### Phase 2: Core Features
- Translate authentication pages
- Translate dashboard interface
- Translate common buttons and labels
- Implement language switching route

### Phase 3: Feature-Specific
- Translate WhatsApp features
- Translate POS system
- Translate payments and QRIS
- Translate AI agent interface

### Phase 4: Advanced
- Translate landing page
- Implement date/number formatting
- Translate email templates
- Translate API responses

### Phase 5: Quality Assurance
- Verify 100% translation coverage
- Test all features in both languages
- Implement missing translation logging
- Performance optimization

## Performance Considerations

1. **Translation Caching**: Laravel caches translation files in production
2. **Lazy Loading**: Load translation files only when needed
3. **Database Queries**: Minimize queries for language preference (use eager loading)
4. **Session Storage**: Store locale in session to avoid repeated lookups
5. **CDN**: Serve static assets with language-specific URLs if needed

## SEO Considerations

1. **Hreflang Tags**: Add hreflang links for language versions
2. **URL Structure**: Consider language prefix in URLs (e.g., /en/..., /id/...)
3. **Meta Tags**: Translate meta descriptions and keywords
4. **Sitemap**: Include language variants in sitemap
