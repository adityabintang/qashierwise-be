# Legal Pages Localization Implementation

## Overview
Implementasi fitur multi-bahasa (Indonesia/Inggris) untuk halaman legal: Privacy Policy, Terms of Service, dan Refund Policy.

## Changes Made

### 1. Translation Files Created
- `resources/lang/id/legal.php` - Terjemahan Bahasa Indonesia
- `resources/lang/en/legal.php` - Terjemahan Bahasa Inggris

### 2. View Files Updated
Semua halaman legal telah diupdate untuk menggunakan translation keys:
- `resources/views/privacy-policy.blade.php`
- `resources/views/terms-of-service.blade.php`
- `resources/views/refund-policy.blade.php`

### 3. Features Added
- ✅ Language switcher component di navigation bar
- ✅ Mobile-responsive language switcher
- ✅ Automatic language detection via LocalizationMiddleware
- ✅ Session persistence untuk pilihan bahasa
- ✅ User preference storage (untuk authenticated users)

## How It Works

### Language Detection Priority
1. User preference (jika authenticated)
2. Session storage
3. Accept-Language header
4. Default locale (id)

### Language Switching
Users dapat mengganti bahasa melalui:
- Language switcher di navigation bar (desktop & mobile)
- URL: `/language/{locale}` (id atau en)

### Translation Structure
```php
// Contoh penggunaan di view
{{ __('legal.privacy_policy.title') }}
{{ __('legal.terms_of_service.section_1.title') }}
{{ __('legal.refund_policy.section_5.step_1') }}
```

## Testing

### Manual Testing
1. Buka halaman legal: `/privacy-policy`, `/terms-of-service`, `/refund-policy`
2. Klik language switcher di navigation bar
3. Pilih bahasa (ID/EN)
4. Verifikasi konten berubah sesuai bahasa yang dipilih
5. Refresh halaman - bahasa tetap tersimpan
6. Test di mobile view

### Routes Available
- `GET /privacy-policy` - Privacy Policy page
- `GET /terms-of-service` - Terms of Service page
- `GET /refund-policy` - Refund Policy page
- `GET /language/{locale}` - Language switcher endpoint

## File Structure
```
resources/
├── lang/
│   ├── id/
│   │   ├── legal.php (NEW)
│   │   └── landing.php (existing)
│   └── en/
│       ├── legal.php (NEW)
│       └── landing.php (existing)
└── views/
    ├── privacy-policy.blade.php (UPDATED)
    ├── terms-of-service.blade.php (UPDATED)
    ├── refund-policy.blade.php (UPDATED)
    └── components/
        └── language-switcher.blade.php (existing)
```

## Configuration

### Supported Locales
Defined in `config/app.php`:
```php
'supported_locales' => ['id', 'en'],
'locale' => 'id',
'fallback_locale' => 'id',
```

### Middleware
LocalizationMiddleware sudah diterapkan secara global pada group 'web' di `bootstrap/app.php`.

## Notes
- Semua halaman legal menggunakan layout yang konsisten dengan landing page
- Footer dan navigation menggunakan translation keys yang sama dengan landing page
- Language switcher component dapat digunakan di halaman lain dengan `<x-language-switcher />`
- Translation keys mengikuti struktur hierarki untuk kemudahan maintenance

## Future Improvements
- [ ] Add more languages (e.g., Chinese, Japanese)
- [ ] Add language-specific meta tags for SEO
- [ ] Add language switcher to dashboard pages
- [ ] Add translation management interface for admin

## Related Files
- `app/Http/Middleware/LocalizationMiddleware.php` - Language detection logic
- `bootstrap/app.php` - Middleware configuration
- `routes/web.php` - Route definitions
- `config/app.php` - Locale configuration
