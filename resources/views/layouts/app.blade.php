<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'QashierWise')</title>

    <!-- Preconnect for critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://js.pusher.com">

    <!-- DNS Prefetch -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    
    <!-- Inter Font - Non-blocking load -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>

    <!-- Tailwind CSS -->
    @if(app()->environment('local') && !file_exists(public_path('build/manifest.json')))
        <script>
            (function() {
                var tw = document.createElement('script');
                tw.src = 'https://cdn.tailwindcss.com';
                tw.onload = function() {
                    tailwind.config = {
                        theme: {
                            extend: {
                                fontFamily: { sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'] },
                            }
                        }
                    }
                };
                document.head.appendChild(tw);
            })();
        </script>
        <link rel="stylesheet" href="{{ asset('css/app-fallback.css') }}">
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <!-- Font Awesome - Non-blocking -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

    <!-- Alpine.js - Deferred -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Pusher JS - Deferred -->
    <script defer src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

    <!-- Laravel Echo - Deferred -->
    <script defer src="https://cdn.jsdelivr.net/npm/laravel-echo@1.15.3/dist/echo.iife.js"></script>

    <!-- ApexCharts - Deferred -->
    <script defer src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.1/dist/apexcharts.min.js"></script>

    <!-- Echo Setup Script - Deferred -->
    <script defer src="{{ asset('js/echo-setup.js') }}"></script>

    <style>
        [x-cloak] { display: none !important; }
        /* Critical CSS fallback */
        body { font-family: 'Inter', ui-sans-serif, system-ui, sans-serif; }
    </style>

    @stack('styles')
</head>
<body class="min-h-screen bg-[hsl(var(--background))] font-sans antialiased">
    @yield('content')

    @stack('scripts')
</body>
</html>
