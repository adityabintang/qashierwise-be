# SEO and Meta Tags Localization Guide

This guide explains how to implement SEO and meta tags localization across the application.

## Overview

The SEO localization system provides:
- Localized meta tags (title, description, keywords)
- Hreflang tags for language versions
- Open Graph and Twitter Card tags
- Structured data (JSON-LD) for search engines
- Updated sitemap with language variants

## Components

### 1. SeoHelper Class

Location: `app/Helpers/SeoHelper.php`

Provides methods for:
- `getMetaTags()` - Get localized meta tags
- `getHreflangTags()` - Generate hreflang tags
- `getOrganizationStructuredData()` - Organization schema
- `getWebsiteStructuredData()` - Website schema
- `getFaqStructuredData()` - FAQ schema
- `renderMetaTags()` - Render meta tags as HTML
- `renderHreflangTags()` - Render hreflang tags as HTML
- `renderStructuredData()` - Render JSON-LD

### 2. Blade Components

#### SEO Meta Component
Location: `resources/views/components/seo-meta.blade.php`

Usage:
```blade
<x-seo-meta :page="'landing'" :params="[]" />
```

Parameters:
- `page` - Translation file key (default: 'landing')
- `params` - Additional parameters for dynamic content

#### Structured Data Component
Location: `resources/views/components/structured-data.blade.php`

Usage:
```blade
<x-structured-data 
    :includeOrganization="true" 
    :includeWebsite="true" 
    :includeFaq="false" 
/>
```

Parameters:
- `includeOrganization` - Include organization schema (default: true)
- `includeWebsite` - Include website schema (default: true)
- `includeFaq` - Include FAQ schema (default: false)

### 3. SEO Head Partial
Location: `resources/views/partials/seo-head.blade.php`

Combines SEO meta tags and structured data in one include.

Usage:
```blade
@include('partials.seo-head', [
    'page' => 'landing',
    'params' => [],
    'includeFaq' => true
])
```

## Implementation Guide

### Step 1: Add Translation Keys

Ensure your translation files have SEO keys:

**resources/lang/en/landing.php**
```php
return [
    'meta_title' => 'Your Page Title',
    'meta_description' => 'Your page description',
    'meta_keywords' => 'keyword1, keyword2, keyword3',
    // ... other translations
];
```

**resources/lang/id/landing.php**
```php
return [
    'meta_title' => 'Judul Halaman Anda',
    'meta_description' => 'Deskripsi halaman Anda',
    'meta_keywords' => 'kata kunci1, kata kunci2, kata kunci3',
    // ... other translations
];
```

### Step 2: Update Blade Templates

#### Option A: Using the SEO Head Partial (Recommended)

Replace the existing meta tags in your `<head>` section:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- Include SEO meta tags and structured data --}}
    @include('partials.seo-head', [
        'page' => 'landing',
        'includeFaq' => true
    ])
    
    {{-- Rest of your head content --}}
    <!-- Preconnect for critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <!-- ... -->
</head>
```

#### Option B: Using Individual Components

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    {{-- SEO Meta Tags --}}
    <x-seo-meta :page="'landing'" />
    
    {{-- Favicon --}}
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    
    {{-- Structured Data --}}
    <x-structured-data :includeFaq="true" />
    
    {{-- Rest of your head content --}}
</head>
```

### Step 3: Update Existing Pages

#### Landing Page (welcome.blade.php)

Replace lines 7-56 (meta tags section) with:

```blade
@include('partials.seo-head', [
    'page' => 'landing',
    'includeFaq' => true
])
```

#### Dashboard Layout (layouts/app.blade.php)

Add in the `<head>` section:

```blade
<x-seo-meta :page="'dashboard'" />
```

#### Auth Pages (login.blade.php, register.blade.php)

Add in the `<head>` section:

```blade
<x-seo-meta :page="'auth'" />
```

## Sitemap

The sitemap has been updated to include language variants with hreflang tags.

Location: `public/sitemap.xml`

Each URL now includes:
```xml
<url>
    <loc>https://qashierwise.com</loc>
    <xhtml:link rel="alternate" hreflang="en" href="https://qashierwise.com"/>
    <xhtml:link rel="alternate" hreflang="id" href="https://qashierwise.com"/>
    <xhtml:link rel="alternate" hreflang="x-default" href="https://qashierwise.com"/>
</url>
```

## Hreflang Tags

Hreflang tags are automatically generated for all supported locales:

```html
<link rel="alternate" hreflang="en" href="https://qashierwise.com">
<link rel="alternate" hreflang="id" href="https://qashierwise.com">
<link rel="alternate" hreflang="x-default" href="https://qashierwise.com">
```

These tags help search engines understand:
- Which language versions are available
- Which version to show to users based on their language preference
- The default version (x-default)

## Structured Data

Structured data (JSON-LD) is included for:

### Organization Schema
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "QashierWise",
  "url": "https://qashierwise.com",
  ...
}
```

### Website Schema
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "name": "QashierWise",
  ...
}
```

### FAQ Schema (when enabled)
```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [...]
}
```

## Testing

### 1. Test Meta Tags

Visit your pages and view source to verify:
- Title is translated
- Description is translated
- Keywords are translated
- Hreflang tags are present
- Open Graph tags have correct locale

### 2. Test Language Switching

1. Switch language using the language switcher
2. Verify meta tags update to the new language
3. Check that hreflang tags remain consistent

### 3. Test Structured Data

Use Google's Rich Results Test:
https://search.google.com/test/rich-results

Paste your page URL to validate structured data.

### 4. Test Sitemap

Visit: https://qashierwise.com/sitemap.xml

Verify:
- All pages are listed
- Hreflang tags are present for each URL
- Language variants are correctly linked

## Best Practices

1. **Keep translations consistent**: Ensure meta titles and descriptions are properly translated and culturally appropriate

2. **Optimize for length**:
   - Title: 50-60 characters
   - Description: 150-160 characters

3. **Use keywords naturally**: Don't stuff keywords, use them naturally in titles and descriptions

4. **Update sitemap regularly**: When adding new pages, update the sitemap with language variants

5. **Test on multiple devices**: Verify Open Graph tags work on social media platforms

6. **Monitor search console**: Use Google Search Console to monitor international targeting

## Troubleshooting

### Meta tags not updating

1. Clear Laravel cache:
   ```bash
   php artisan cache:clear
   php artisan view:clear
   ```

2. Check translation files exist for both locales

3. Verify the `page` parameter matches your translation file name

### Hreflang tags not showing

1. Verify `app.supported_locales` is set in `config/app.php`
2. Check that `app.url` is correctly configured
3. Clear cache and reload

### Structured data errors

1. Use Google's Rich Results Test to identify issues
2. Verify translation keys exist (especially for FAQ items)
3. Check JSON syntax in the rendered output

## Additional Resources

- [Google Search Central - Hreflang](https://developers.google.com/search/docs/specialty/international/localized-versions)
- [Schema.org Documentation](https://schema.org/)
- [Open Graph Protocol](https://ogp.me/)
- [Twitter Cards](https://developer.twitter.com/en/docs/twitter-for-websites/cards/overview/abouts-cards)
