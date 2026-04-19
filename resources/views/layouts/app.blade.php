<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QashierWise')</title>

    <!-- Vite Assets (Tailwind CSS v4, Alpine.js, axios, Font Awesome, Inter font) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
        /* Critical CSS fallback */
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>

    @stack('head-scripts')
    @stack('styles')

    <!-- Google Analytics 4 -->
    <x-google-analytics />

    <!-- Meta Pixel -->
    <x-meta-pixel />
</head>
<body class="min-h-screen bg-[hsl(var(--background))] font-sans antialiased">
    @yield('content')

    @stack('scripts')

    <!-- Cookie Consent Banner -->
    <x-cookie-consent />
</body>
</html>
