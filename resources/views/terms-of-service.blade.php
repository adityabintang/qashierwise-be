<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="{{ __('legal.terms_of_service.meta_description') }}">
    <title>{{ __('legal.terms_of_service.meta_title') }}</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#4910ce">
        <!-- Vite Assets (Tailwind CSS v4 + JS bundle with Alpine only) -->
    @vite(['resources/css/app.css', 'resources/js/static.js'])
    <style>
        [x-cloak] { display: none !important; }
    </style>

    <!-- Trigger Alpine.js to load immediately for legal pages (menu interaction) -->
    <script>
        // Trigger Alpine loading immediately by dispatching an event
        // This overrides the 3s delay in static.js for better LCP
        document.addEventListener('DOMContentLoaded', () => {
            // Dispatch a click event to trigger Alpine loading from static.js
            document.dispatchEvent(new Event('click'));
        });
    </script>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav x-data="{ menuOpen: false }" class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="eager">
                        <span class="text-xl font-bold text-primary">{{ __('legal.company_name') }}</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/#features" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.how_it_works') }}</a>
                    <a href="/#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a>
                    <a href="/#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a>
                    <a href="/#about" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.about') }}</a>
                    <a href="/#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a>
                </div>

                <!-- CTA Buttons -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Language Switcher -->
                    <x-language-switcher />
                    
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                        @else
                            <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.view_demo') }}</a>
                            <a href="/register" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-all">
                                {{ __('landing.nav.try_free') }}
                            </a>
                        @endauth
                    @endif
                </div>

                <!-- Mobile Hamburger Button -->
                <button
                    @click="menuOpen = !menuOpen"
                    class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary/50"
                    aria-label="Toggle menu"
                >
                    <i x-show="!menuOpen" class="fas fa-bars text-xl"></i>
                    <i x-show="menuOpen" x-cloak class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu -->
        <div
            x-show="menuOpen"
            x-cloak
            @click.away="menuOpen = false"
            class="md:hidden bg-white border-t border-gray-100 shadow-lg"
        >
            <div class="px-4 py-4 space-y-3">
                <a href="/#features" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.how_it_works') }}</a>
                <a href="/#fitur" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.features') }}</a>
                <a href="/#pricing" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.pricing') }}</a>
                <a href="/#about" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.about') }}</a>
                <a href="/#faq" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.faq') }}</a>

                <div class="pt-4 border-t border-gray-100 space-y-3">
                    <div class="flex justify-center">
                        <x-language-switcher />
                    </div>
                    
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="block py-3 px-4 text-center text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                        @else
                            <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="block py-3 px-4 text-center text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.view_demo') }}</a>
                            <a href="/register" class="block py-3 px-4 text-center bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-all">
                                {{ __('landing.nav.try_free') }}
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="pt-24 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Back Link -->
            <a href="/" class="inline-flex items-center text-gray-600 hover:text-primary mb-8 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                {{ __('legal.back_to_home') }}
            </a>

            <!-- Title -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">{{ __('legal.terms_of_service.title') }}</h1>
            <p class="text-gray-500 mb-8">{{ __('legal.last_updated') }}: {{ __('legal.terms_of_service.last_updated_date') }}</p>

            <!-- Intro -->
            <div class="prose prose-lg max-w-none">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">{{ __('legal.terms_of_service.intro.title') }}</h2>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.intro.paragraph_1') }}</p>
                <p class="text-gray-600 mb-8">{{ __('legal.terms_of_service.intro.paragraph_2') }}</p>

                <!-- Section I -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">I. {{ __('legal.terms_of_service.section_1.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.terms_of_service.section_1.intro') }}</p>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">A. {{ __('legal.terms_of_service.section_1.subsection_a.title') }}</h3>
                <ul class="list-disc pl-6 text-gray-600 space-y-3 mb-6">
                    @foreach(__('legal.terms_of_service.section_1.subsection_a.items') as $item)
                        <li>{!! $item !!}</li>
                    @endforeach
                </ul>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">B. {{ __('legal.terms_of_service.section_1.subsection_b.title') }}</h3>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.section_1.subsection_b.content') }}</p>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">C. {{ __('legal.terms_of_service.section_1.subsection_c.title') }}</h3>
                <p class="text-gray-600 mb-4">{{ __('legal.terms_of_service.section_1.subsection_c.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-3 mb-6">
                    @foreach(__('legal.terms_of_service.section_1.subsection_c.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section II -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">II. {{ __('legal.terms_of_service.section_2.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.terms_of_service.section_2.paragraph_1') }}</p>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.section_2.paragraph_2') }}</p>

                <!-- Section III -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">III. {{ __('legal.terms_of_service.section_3.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.terms_of_service.section_3.paragraph_1') }}</p>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.section_3.paragraph_2') }}</p>

                <!-- Section IV -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">IV. {{ __('legal.terms_of_service.section_4.title') }}</h2>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.section_4.content') }}</p>

                <!-- Section V -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">V. {{ __('legal.terms_of_service.section_5.title') }}</h2>
                <p class="text-gray-600 mb-6">{{ __('legal.terms_of_service.section_5.content') }}</p>

                <!-- Section VI -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">VI. {{ __('legal.terms_of_service.section_6.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.terms_of_service.section_6.intro') }}</p>
                <div class="text-gray-600 mb-8">
                    <p><strong>{{ __('legal.email') }}:</strong> support@qashierwise.com</p>
                    <p><strong>Admin:</strong> {{ __('legal.terms_of_service.section_6.admin_email') }}</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="lazy">
                        <span class="text-xl font-bold text-primary">{{ __('legal.company_name') }}</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        {!! __('landing.footer.company_description') !!}
                    </p>
                    <div class="text-sm text-gray-600">
                        <p class="font-semibold text-gray-900 mb-1">{{ __('legal.address') }}</p>
                        <p>{!! __('landing.footer.address') !!}</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">{{ __('landing.footer.navigation_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="/#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a></li>
                        <li><a href="/#about" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.about') }}</a></li>
                        <li><a href="/#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">{{ __('landing.footer.legal_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.privacy_policy') }}</a></li>
                        <li><a href="/terms-of-service" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.terms_of_service') }}</a></li>
                        <li><a href="/refund-policy" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.refund_policy') }}</a></li>
                    </ul>
                </div>

                <!-- Product -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">{{ __('landing.footer.product_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.console') }}</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.chatbot') }}</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-8 text-center text-sm text-gray-600">
                <p>{!! __('landing.footer.copyright') !!}</p>
            </div>
        </div>
    </footer>
</body>
</html>
