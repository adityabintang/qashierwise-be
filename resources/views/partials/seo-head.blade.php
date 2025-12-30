{{-- SEO Meta Tags Component --}}
<x-seo-meta :page="$page ?? 'landing'" :params="$params ?? []" />

<!-- Favicon - Optimized sizes -->
<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
<link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo-16.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#4910ce">

{{-- Structured Data --}}
<x-structured-data 
    :includeOrganization="$includeOrganization ?? true" 
    :includeWebsite="$includeWebsite ?? true" 
    :includeFaq="$includeFaq ?? false" 
/>
