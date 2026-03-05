<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('legal.refund_policy.meta_title') }}</title>

    <!-- Vite Assets (Tailwind CSS v4 + JS bundle with Alpine, Font Awesome, Inter font) -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>
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
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">{{ __('legal.refund_policy.title') }}</h1>
            <p class="text-gray-500 mb-12">{{ __('legal.last_updated') }}: {{ __('legal.refund_policy.last_updated_date') }}</p>

            <!-- Content -->
            <div class="prose prose-lg max-w-none">
                <!-- Introduction -->
                <p class="text-gray-600 mb-6">{{ __('legal.refund_policy.intro') }}</p>

                <!-- Section 1 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">1. {{ __('legal.refund_policy.section_1.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_1.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_1.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 2 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">2. {{ __('legal.refund_policy.section_2.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_2.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_2.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 3 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">3. {{ __('legal.refund_policy.section_3.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_3.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_3.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 4 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">4. {{ __('legal.refund_policy.section_4.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_4.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_4.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 5 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">5. {{ __('legal.refund_policy.section_5.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_5.intro') }}</p>
                <ol class="list-decimal pl-6 text-gray-600 space-y-2 mb-6">
                    <li>{{ __('legal.refund_policy.section_5.step_1') }}</li>
                    <li>{{ __('legal.refund_policy.section_5.step_2') }}
                        <ul class="list-disc pl-6 mt-2 space-y-1">
                            @foreach(__('legal.refund_policy.section_5.step_2_items') as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    </li>
                    <li>{{ __('legal.refund_policy.section_5.step_3') }}</li>
                    <li>{{ __('legal.refund_policy.section_5.step_4') }}</li>
                    <li>{{ __('legal.refund_policy.section_5.step_5') }}</li>
                </ol>

                <!-- Section 6 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">6. {{ __('legal.refund_policy.section_6.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_6.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_6.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 7 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">7. {{ __('legal.refund_policy.section_7.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_7.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>{!! __('legal.refund_policy.section_7.upgrade') !!}</li>
                    <li>{!! __('legal.refund_policy.section_7.downgrade') !!}</li>
                    @foreach(__('legal.refund_policy.section_7.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 8 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">8. {{ __('legal.refund_policy.section_8.title') }}</h2>
                <p class="text-gray-600 mb-4">{{ __('legal.refund_policy.section_8.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_8.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 9 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">9. {{ __('legal.refund_policy.section_9.title') }}</h2>
                <p class="text-gray-600 mb-6">{{ __('legal.refund_policy.section_9.intro') }}</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    @foreach(__('legal.refund_policy.section_9.items') as $item)
                        <li>{{ $item }}</li>
                    @endforeach
                </ul>

                <!-- Section 10 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">10. {{ __('legal.refund_policy.section_10.title') }}</h2>
                <p class="text-gray-600 mb-6">{{ __('legal.refund_policy.section_10.intro') }}</p>

                <!-- Contact Box -->
                <div class="bg-gray-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-gray-900 mb-3">{{ __('legal.customer_support') }}</h3>
                    <div class="text-gray-600 space-y-1">
                        <p><span class="font-medium">{{ __('legal.email') }}:</span> support@qashierwise.com</p>
                        <p><span class="font-medium">{{ __('legal.whatsapp') }}:</span> +62882003235019</p>
                        <p><span class="font-medium">{{ __('legal.operating_hours') }}:</span> {{ __('legal.monday_friday') }}</p>
                        <p><span class="font-medium">{{ __('legal.address') }}:</span> Jl. Widosari No. 55, Tegalrejo Raya, Salatiga, Jawa Tengah, Indonesia 50733</p>
                    </div>
                </div>

                <!-- Important Notice -->
                <div class="bg-blue-50 border-l-4 border-primary rounded-lg p-6 mb-8">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-primary text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">{{ __('legal.refund_policy.important_notice.title') }}</h3>
                            <div class="text-sm text-gray-600">
                                <p>{{ __('legal.refund_policy.important_notice.content') }}</p>
                            </div>
                        </div>
                    </div>
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
