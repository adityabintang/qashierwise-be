<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'QashierWise' }}</title>
    <meta name="description" content="{{ $description ?? '' }}">

    {{-- Fonts (replaces Next.js next/font). Manrope = body, Geist = display. --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --font-manrope: 'Manrope', system-ui, sans-serif;
            --font-sans: 'Geist', 'Manrope', system-ui, sans-serif;
        }
    </style>

    {{-- Which React page to mount (landing | privacy | terms | refund) --}}
    <script>window.__PAGE__ = @json($page ?? 'landing');</script>

    @vite('resources/js/next.js/main.tsx')
</head>
<body class="min-h-full flex flex-col bg-white text-ink-900">
    <div id="app"></div>
</body>
</html>
