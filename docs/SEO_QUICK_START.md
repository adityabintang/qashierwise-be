# SEO Localization - Quick Start Guide

## 5-Minute Integration

### Step 1: Add to Your Blade Template

Replace your existing meta tags with:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- SEO Meta Tags & Structured Data --}}
    @include('partials.seo-head', [
        'page' => 'landing',  // or 'dashboard', 'auth', etc.
        'includeFaq' => true  // set to true if page has FAQ
    ])
    
    {{-- Your other head content --}}
</head>
```

### Step 2: Add Translation Keys

In `resources/lang/en/your-page.php`:
```php
return [
    'meta_title' => 'Your Page Title - QashierWise',
    'meta_description' => 'Your page description for search engines',
    'meta_keywords' => 'keyword1, keyword2, keyword3',
    // ... other translations
];
```

In `resources/lang/id/your-page.php`:
```php
return [
    'meta_title' => 'Judul Halaman Anda - QashierWise',
    'meta_description' => 'Deskripsi halaman untuk mesin pencari',
    'meta_keywords' => 'kata kunci1, kata kunci2, kata kunci3',
    // ... other translations
];
```

### Step 3: Done! 🎉

Your page now has:
- ✅ Localized meta tags
- ✅ Hreflang tags for SEO
- ✅ Open Graph tags for social media
- ✅ Twitter Card tags
- ✅ Structured data (JSON-LD)

## Alternative: Component-Based Approach

If you prefer more control:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- SEO Meta Tags --}}
    <x-seo-meta :page="'landing'" />
    
    {{-- Favicon --}}
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    
    {{-- Structured Data --}}
    <x-structured-data :includeFaq="true" />
</head>
```

## What You Get

### Meta Tags
```html
<title>Your Localized Title</title>
<meta name="description" content="Your localized description">
<meta name="keywords" content="your, localized, keywords">
```

### Hreflang Tags
```html
<link rel="alternate" hreflang="en" href="https://qashierwise.com">
<link rel="alternate" hreflang="id" href="https://qashierwise.com">
<link rel="alternate" hreflang="x-default" href="https://qashierwise.com">
```

### Open Graph Tags
```html
<meta property="og:title" content="Your Localized Title">
<meta property="og:description" content="Your localized description">
<meta property="og:locale" content="en_US">
```

### Structured Data
```html
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "QashierWise",
  ...
}
</script>
```

## Testing

### 1. View Source
Right-click on your page → View Page Source → Check meta tags

### 2. Google Rich Results Test
Visit: https://search.google.com/test/rich-results
Paste your URL

### 3. Facebook Debugger
Visit: https://developers.facebook.com/tools/debug/
Paste your URL

### 4. Twitter Card Validator
Visit: https://cards-dev.twitter.com/validator
Paste your URL

## Common Issues

### Meta tags not showing?
```bash
php artisan cache:clear
php artisan view:clear
```

### Wrong language?
Check your translation files exist:
- `resources/lang/en/your-page.php`
- `resources/lang/id/your-page.php`

### Hreflang not working?
Verify `config/app.php`:
```php
'supported_locales' => ['en', 'id'],
```

## Need More Help?

See full documentation: `docs/SEO_LOCALIZATION_GUIDE.md`
