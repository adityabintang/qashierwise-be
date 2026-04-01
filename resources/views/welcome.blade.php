<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <!-- Primary Meta Tags -->
    <title>QashierWise - AI Chatbot WhatsApp Restoran</title>
    <meta name="title" content="QashierWise - AI Chatbot WhatsApp Restoran">
    <meta name="description" content="Platform AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS. Kelola reservasi, pesanan, dan pembayaran. Coba gratis 14 hari!">
    <meta name="keywords" content="chatbot whatsapp restoran, AI chatbot restoran, reservasi restoran otomatis, order whatsapp, QRIS restoran, POS restoran, manajemen restoran, WhatsApp Business API restoran">
    <meta name="author" content="Aditya Bintang Fadila">
    <meta name="robots" content="index, follow">
    <meta name="language" content="Indonesian">
    <meta name="revisit-after" content="7 days">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://qashierwise.com">

     <!-- Favicon - Optimized sizes -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo-16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#4910ce">

    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://qashierwise.com">
    <meta property="og:title" content="QashierWise - AI Chatbot WhatsApp untuk Restoran">
    <meta property="og:description" content="Respon lebih cepat, jual lebih banyak. Platform AI Chatbot WhatsApp dengan integrasi QRIS untuk restoran. Kelola reservasi & order dalam satu dashboard.">
    <meta property="og:image" content="https://qashierwise.com/images/og-image.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="QashierWise - AI Chatbot WhatsApp untuk Restoran">
    <meta property="og:locale" content="id_ID">
    <meta property="og:site_name" content="QashierWise">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:url" content="https://qashierwise.com">
    <meta name="twitter:title" content="QashierWise - AI Chatbot WhatsApp untuk Restoran">
    <meta name="twitter:description" content="Respon lebih cepat, jual lebih banyak. Platform AI Chatbot WhatsApp dengan integrasi QRIS untuk restoran.">
    <meta name="twitter:image" content="https://qashierwise.com/images/og-image.png">
    <meta name="twitter:image:alt" content="QashierWise - AI Chatbot WhatsApp untuk Restoran">

    <!-- Preload critical resources to reduce network dependency chain -->
    <link rel="preload" href="{{ asset('site.webmanifest') }}" as="fetch" crossorigin>
    <link rel="preload" as="image" type="image/avif" href="{{ asset('images/hero-restaurant-205w.avif') }}" media="(max-width: 767px)" fetchpriority="high">
    <link rel="preload" as="image" type="image/avif" href="{{ asset('images/hero-restaurant-400w.avif') }}" media="(min-width: 768px)" fetchpriority="high">
    <link rel="preload" href="{{ asset('build/assets/fa-solid-900-DRAAbZTg.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('build/assets/fa-brands-400-BP5tdqmh.woff2') }}" as="font" type="font/woff2" crossorigin>

    <!-- Vite Assets (Tailwind CSS v4 + JS bundle with Alpine, Font Awesome, Inter font) -->
    @vite(['resources/css/app.css', 'resources/js/welcome.js'])

    <!-- Additional SEO Meta -->
    <meta name="geo.region" content="ID-JT">
    <meta name="geo.placename" content="Salatiga">
    <meta name="geo.position" content="-7.3305;110.5084">
    <meta name="ICBM" content="-7.3305, 110.5084">

    <!-- Critical CSS inline for above-the-fold content -->
    <style>
        [x-cloak]{display:none!important}
        body{font-family:'Inter',ui-sans-serif,system-ui,sans-serif;margin:0;overflow-x:hidden}
        .gradient-text{background:linear-gradient(135deg,#4910ce 0%,#7c3aed 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
       .hero-gradient{background:linear-gradient(135deg,#f3e8ff 0%,#ede9fe 50%,#faf5ff 100%);min-height:500px;contain:layout style}
        nav{height:64px;contain:layout}
        .hero-image-container{aspect-ratio:4/3;min-height:240px;contain:layout style}
        .floating-card{contain:layout style;will-change:transform}
        img{max-width:100%;height:auto}
        @media(min-width:768px){.hero-gradient{min-height:600px}.hero-image-container{aspect-ratio:665/444;min-height:300px}}
        @font-face{font-family:'Font Awesome 6 Free';font-display:swap}
        @font-face{font-family:'Font Awesome 6 Brands';font-display:swap}
    </style>

    @verbatim
    <!-- Structured Data - Organization -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Organization",
        "name": "QashierWise",
        "url": "https://qashierwise.com",
        "logo": "https://qashierwise.com/images/logo.png",
        "description": "Platform AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS",
        "address": {
            "@type": "PostalAddress",
            "streetAddress": "Jl. Widosari No. 55, Tegalrejo Raya",
            "addressLocality": "Salatiga",
            "addressRegion": "Jawa Tengah",
            "postalCode": "50733",
            "addressCountry": "ID"
        },
        "founder": {
            "@type": "Person",
            "name": "Aditya Bintang Fadila"
        },
        "foundingDate": "2025",
        "sameAs": []
    }
    </script>

    <!-- Structured Data - SoftwareApplication -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "QashierWise",
        "applicationCategory": "BusinessApplication",
        "applicationSubCategory": "Restaurant Management Software",
        "operatingSystem": "Web Browser",
        "description": "AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS. Kelola reservasi, pesanan, dan pembayaran dalam satu dashboard.",
        "offers": {
            "@type": "AggregateOffer",
            "lowPrice": "0",
            "highPrice": "2990000",
            "priceCurrency": "IDR",
            "offerCount": "3"
        },
        "featureList": [
            "AI Chatbot WhatsApp",
            "Reservasi Otomatis",
            "Order Delivery & Pickup",
            "Pembayaran QRIS",
            "Dashboard Manajemen",
            "Laporan & CRM"
        ]
    }
    </script>

    <!-- Structured Data - WebSite -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "WebSite",
        "name": "QashierWise",
        "url": "https://qashierwise.com",
        "description": "Platform AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS",
        "publisher": {
            "@type": "Organization",
            "name": "QashierWise"
        }
    }
    </script>

    <!-- Structured Data - FAQ -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "FAQPage",
        "mainEntity": [
            {
                "@type": "Question",
                "name": "Apa itu QashierWise?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "QashierWise adalah platform AI Chatbot WhatsApp yang dirancang khusus untuk restoran, membantu mengelola reservasi, pesanan, dan pembayaran QRIS dalam satu dashboard."
                }
            },
            {
                "@type": "Question",
                "name": "Apakah perlu aplikasi terpisah untuk pelanggan?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Tidak! Pelanggan cukup menggunakan WhatsApp yang sudah mereka miliki. Tidak perlu download aplikasi tambahan."
                }
            },
            {
                "@type": "Question",
                "name": "Bagaimana pembayaran dilakukan?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Pembayaran bisa dilakukan melalui QRIS yang terintegrasi dengan Midtrans, Xendit, atau penyedia QRIS lainnya. Pelanggan juga bisa bayar di tempat."
                }
            },
            {
                "@type": "Question",
                "name": "Apakah bisa multi-outlet dan multi nomor?",
                "acceptedAnswer": {
                    "@type": "Answer",
                    "text": "Ya! Paket Standard mendukung hingga 2 outlet, dan paket Pro mendukung unlimited outlet dengan fitur multi-branding."
                }
            }
        ]
    }
    </script>

    <!-- Structured Data - BreadcrumbList -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "BreadcrumbList",
        "itemListElement": [
            @foreach(App\Helpers\SeoHelper::getBreadcrumbItems(__('landing.meta_title')) as $index => $item)
            {
                "@type": "ListItem",
                "position": {{ $index + 1 }},
                "name": "{{ $item['label'] }}",
                "item": "{{ $item['url'] }}"
            }{{ !$loop->last ? ',' : '' }}
            @endforeach
        ]
    }
    </script>

    <!-- Structured Data - Product/Pricing -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "Product",
        "name": "QashierWise Standard",
        "description": "Paket Standard QashierWise dengan fitur delivery, QRIS unlimited, dan dukungan hingga 2 outlet",
        "image": [
            "https://qashierwise.com/images/logo.png",
            "https://qashierwise.com/images/og-image.png"
        ],
        "brand": {
            "@type": "Brand",
            "name": "QashierWise"
        },
        "aggregateRating": {
            "@type": "AggregateRating",
            "ratingValue": 4.8,
            "reviewCount": 24,
            "bestRating": 5,
            "worstRating": 1
        },
        "review": [
            {
                "@type": "Review",
                "author": {
                    "@type": "Person",
                    "name": "Budi Santoso"
                },
                "datePublished": "2025-11-15",
                "reviewBody": "QashierWise sangat membantu restoran kami. Reservasi jadi lebih teratur dan otomatis, pelanggan puas dengan respons cepat.",
                "reviewRating": {
                    "@type": "Rating",
                    "ratingValue": 5,
                    "bestRating": 5
                }
            },
            {
                "@type": "Review",
                "author": {
                    "@type": "Person",
                    "name": "Siti Nurhaliza"
                },
                "datePublished": "2025-11-20",
                "reviewBody": "Fitur QRIS dan order via WhatsApp sangat praktis. Dashboard mudah digunakan dan laporan lengkap.",
                "reviewRating": {
                    "@type": "Rating",
                    "ratingValue": 5,
                    "bestRating": 5
                }
            },
            {
                "@type": "Review",
                "author": {
                    "@type": "Person",
                    "name": "Ahmad Rizki"
                },
                "datePublished": "2025-11-25",
                "reviewBody": "Sistem yang bagus untuk restoran. AI chatbot cukup pintar dalam merespons pelanggan. Recommended!",
                "reviewRating": {
                    "@type": "Rating",
                    "ratingValue": 4,
                    "bestRating": 5
                }
            }
        ],
        "offers": {
            "@type": "Offer",
            "price": 249000,
            "priceCurrency": "IDR",
            "priceValidUntil": "2025-12-31",
            "availability": "https://schema.org/InStock",
            "url": "https://qashierwise.com/#pricing",
            "shippingDetails": {
                "@type": "OfferShippingDetails",
                "shippingRate": {
                    "@type": "MonetaryAmount",
                    "value": 0,
                    "currency": "IDR"
                },
                "shippingDestination": {
                    "@type": "DefinedRegion",
                    "addressCountry": "ID"
                },
                "deliveryTime": {
                    "@type": "ShippingDeliveryTime",
                    "handlingTime": {
                        "@type": "QuantitativeValue",
                        "minValue": 0,
                        "maxValue": 1,
                        "unitCode": "DAY"
                    },
                    "transitTime": {
                        "@type": "QuantitativeValue",
                        "minValue": 0,
                        "maxValue": 0,
                        "unitCode": "DAY"
                    }
                }
            },
            "hasMerchantReturnPolicy": {
                "@type": "MerchantReturnPolicy",
                "applicableCountry": "ID",
                "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow",
                "merchantReturnDays": 7,
                "returnMethod": "https://schema.org/ReturnByMail",
                "returnFees": "https://schema.org/FreeReturn"
            }
        }
    }
    </script>
    @endverbatim

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
        .gradient-text {
            background: linear-gradient(135deg, #4910ce 0%, #7c3aed 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-gradient {
            background: linear-gradient(135deg, #f3e8ff 0%, #ede9fe 50%, #faf5ff 100%);
        }
        .card-shadow {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        /* Composited animations for better performance */
        .feature-card {
            will-change: transform;
            transform: translateZ(0);
        }
        .feature-card:hover {
            transform: translateY(-4px) translateZ(0);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        /* Reduce motion for accessibility */
        @media (prefers-reduced-motion: reduce) {
            .feature-card, .floating-card, .transition {
                transition: none !important;
                animation: none !important;
            }
        }
    </style>

    <!-- Google Analytics 4 - Delayed for Lighthouse -->
    <script>
        window.addEventListener('load', () => {
            setTimeout(() => {
                if (localStorage.getItem('cookieConsent') === 'accepted') {
                    loadGoogleAnalytics();
                }
            }, 5000);
        });

        window.addEventListener('cookieConsentUpdated', (e) => {
            if (e.detail.consent === 'accepted') {
                setTimeout(() => loadGoogleAnalytics(), 5000);
            }
        });

        function loadGoogleAnalytics() {
            const script = document.createElement('script');
            script.async = true;
            script.src = 'https://www.googletagmanager.com/gtag/js?id={{ config("services.google_analytics.measurement_id") }}';
            document.head.appendChild(script);

            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            window.gtag = gtag;

            gtag('js', new Date());
            gtag('config', '{{ config("services.google_analytics.measurement_id") }}', {
                'anonymize_ip': true,
                'cookie_flags': 'SameSite=None;Secure'
            });
        }

        window.trackSignup = function(method = 'email') {
            if (typeof gtag !== 'undefined') {
                gtag('event', 'sign_up', { 'method': method, 'page_location': window.location.href });
            }
        };
    </script>
</head>
<body class="bg-white">
    <!-- Breadcrumb Navigation -->
    <x-breadcrumb :items="App\Helpers\SeoHelper::getBreadcrumbItems(__('landing.meta_title'))" />

    <!-- Navigation -->
    <nav x-data="{ menuOpen: false }" class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100" style="height: 64px; contain: layout;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="eager" style="width: 28px; height: 28px;">
                    <span class="text-xl font-bold text-primary">QashierWise</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.how_it_works') }}</a>
                    <a href="#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a>
                    <a href="#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a>
                    <a href="#about" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.about') }}</a>
                    <a href="#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a>
                </div>

                <!-- CTA Buttons (Desktop) -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Language Switcher -->
                    <x-language-switcher />

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                        @else
                            <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.view_demo') }}</a>
                            <a href="/login" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-all">
                                {{ __('landing.nav.try_free') }}
                            </a>
                        @endauth
                    @endif
                </div>

                <!-- Mobile Hamburger Button -->
                <button
                    @click="menuOpen = !menuOpen"
                    class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary/50 min-h-[44px] min-w-[44px] flex items-center justify-center"
                    aria-label="Toggle menu"
                    :aria-expanded="menuOpen"
                >
                    <i x-show="!menuOpen" class="fas fa-bars text-xl"></i>
                    <i x-show="menuOpen" x-cloak class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Mobile Menu Panel -->
        <div
            x-show="menuOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-2"
            class="md:hidden bg-white border-t border-gray-100 shadow-lg"
        >
            <div class="px-4 py-4 space-y-3">
                <a href="#features" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.how_it_works') }}</a>
                <a href="#fitur" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.features') }}</a>
                <a href="#pricing" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.pricing') }}</a>
                <a href="#about" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.about') }}</a>
                <a href="#faq" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.faq') }}</a>

                <!-- Mobile CTA Buttons -->
                <div class="pt-4 border-t border-gray-100 space-y-3">
                    <!-- Language Switcher for Mobile -->
                    <div class="flex justify-center">
                        <x-language-switcher />
                    </div>

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="block py-3 px-4 text-center text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                        @else
                            <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="block py-3 px-4 text-center text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.view_demo') }}</a>
                            <a href="/login" class="block py-3 px-4 text-center bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-all">
                                {{ __('landing.nav.try_free') }}
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>

        <!-- Mobile Menu Backdrop Overlay -->
        <div
            x-show="menuOpen"
            x-cloak
            @click="menuOpen = false"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="md:hidden fixed inset-0 bg-black/20 -z-10"
            style="top: calc(100% + 1px);"
        ></div>
    </nav>

    <!-- Main Content -->
    <main>
    <!-- Hero Section -->
    <section class="hero-gradient pt-24 md:pt-32 pb-12 md:pb-20 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Mobile: flex-col (text above image), Desktop: grid 2 cols -->
            <div class="flex flex-col lg:grid lg:grid-cols-2 gap-8 md:gap-12 items-center">
                <!-- Left Content -->
                <div class="text-center lg:text-left order-1">
                    <div class="inline-flex items-center px-3 md:px-4 py-2 bg-purple-100 rounded-full text-primary text-xs md:text-sm font-medium mb-4 md:mb-6">
                        <i class="fas fa-sparkles mr-2"></i>
                        {{ __('landing.hero.badge') }}
                    </div>

                    <p class="text-gray-500 mb-3 md:mb-4 text-sm md:text-base">{{ __('landing.hero.subtitle') }}</p>

                    <!-- Responsive heading: smaller on mobile -->
                    <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight mb-4 md:mb-6">
                        {{ __('landing.hero.title') }} <span class="gradient-text">{{ __('landing.hero.title_highlight') }}</span>
                    </h1>

                    <p class="text-base md:text-lg text-gray-600 mb-6 md:mb-8 leading-relaxed">
                        {{ __('landing.hero.description') }}
                    </p>

                    <div class="flex flex-col sm:flex-row gap-3 md:gap-4 mb-6 md:mb-8 justify-center lg:justify-start">
                        <a href="/login" class="inline-flex items-center justify-center px-6 md:px-8 py-3 md:py-4 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all text-sm md:text-base">
                            <span>{{ __('landing.hero.cta_primary') }}</span>
                        </a>
                        <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="inline-flex items-center justify-center px-6 md:px-8 py-3 md:py-4 bg-white text-primary rounded-xl font-semibold border-2 border-primary/30 hover:border-primary transition-all text-sm md:text-base">
                            <i class="fas fa-play-circle mr-2"></i>
                            <span>{{ __('landing.hero.cta_secondary') }}</span>
                        </a>
                    </div>

                    <div class="flex flex-col gap-2 text-xs md:text-sm text-gray-500 items-center lg:items-start">
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            <span>{{ __('landing.hero.benefits.reservations_orders') }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            <span>{{ __('landing.hero.benefits.manage_orders') }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            <span>{{ __('landing.hero.benefits.no_workflow_change') }}</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-600 mr-2"></i>
                            <span>{{ __('landing.hero.benefits.auto_reports') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Hero Image -->
                <div class="relative order-2 w-full max-w-sm lg:max-w-none mx-auto mt-6 lg:mt-0" style="aspect-ratio: 4/3; min-height: 240px;">
                    <picture>
                        <source
                            media="(max-width: 767px)"
                            type="image/avif"
                            srcset="{{ asset('images/hero-restaurant-205w.avif') }}"
                            width="205" height="137">
                        <source
                            media="(max-width: 767px)"
                            type="image/webp"
                            srcset="{{ asset('images/hero-restaurant-205w.webp') }}"
                            width="205" height="137">
                        <source
                            media="(max-width: 767px)"
                            srcset="{{ asset('images/hero-restaurant-205w.jpg') }}"
                            width="205" height="137">
                        <source
                            media="(min-width: 768px)"
                            type="image/avif"
                            srcset="{{ asset('images/hero-restaurant-400w.avif') }} 400w,
                                    {{ asset('images/hero-restaurant-665w.avif') }} 665w"
                            sizes="(min-width: 1024px) 400px, 320px">
                        <source
                            media="(min-width: 768px)"
                            type="image/webp"
                            srcset="{{ asset('images/hero-restaurant-400w.webp') }} 400w,
                                    {{ asset('images/hero-restaurant-665w.webp') }} 665w"
                            sizes="(min-width: 1024px) 400px, 320px">
                        <img
                            src="{{ asset('images/hero-restaurant-205w.jpg') }}"
                            srcset="{{ asset('images/hero-restaurant-205w.jpg') }} 205w,
                                    {{ asset('images/hero-restaurant-400w.jpg') }} 400w"
                            sizes="(max-width: 767px) 205px, (min-width: 1024px) 400px, 320px"
                            alt="Restaurant ordering"
                            class="rounded-2xl shadow-2xl w-full"
                            width="205"
                            height="137"
                            style="aspect-ratio: 4/3; object-fit: cover; width: 100%; height: auto;"
                            fetchpriority="high"
                            loading="eager">
                    </picture>
                    <!-- Floating Elements - Hidden on small mobile, visible on larger screens -->
                    <div class="hidden sm:block absolute -bottom-4 md:-bottom-6 -left-2 md:-left-6 bg-white rounded-xl p-3 md:p-4 shadow-xl floating-card" style="contain: layout style; will-change: transform;">
                        <div class="flex items-center gap-2 md:gap-3">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-green-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fab fa-whatsapp text-green-600 text-lg md:text-xl"></i>
                            </div>
                            <div class="flex flex-col justify-center">
                                <p class="text-xs md:text-sm font-semibold text-gray-900 leading-tight">{{ __('landing.hero.floating_messages') }}</p>
                                <p class="text-xs text-gray-500 leading-tight">{{ __('landing.hero.floating_messages_count') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden sm:block absolute -top-2 md:-top-4 -right-2 md:-right-4 bg-white rounded-xl p-3 md:p-4 shadow-xl floating-card" style="contain: layout style; will-change: transform;">
                        <div class="flex items-center gap-2 md:gap-3">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-purple-100 rounded-full flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-chart-line text-primary text-lg md:text-xl"></i>
                            </div>
                            <div class="flex flex-col justify-center">
                                <p class="text-xs md:text-sm font-semibold text-gray-900 leading-tight">{{ __('landing.hero.floating_sales') }}</p>
                                <p class="text-xs text-green-700 leading-tight">{{ __('landing.hero.floating_sales_trend') }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="features" class="py-12 md:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-16">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 md:mb-4">{{ __('landing.how_it_works.title') }}</h2>
                <p class="text-sm md:text-base text-gray-600 max-w-2xl mx-auto">{{ __('landing.how_it_works.subtitle') }}</p>
            </div>

            <!-- Responsive: 1 col mobile, 2 cols tablet, 4 cols desktop -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-green-500/30">
                        <i class="fab fa-whatsapp text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('landing.how_it_works.steps.0.title') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('landing.how_it_works.steps.0.description') }}</p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-primary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                        <i class="fas fa-robot text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('landing.how_it_works.steps.1.title') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('landing.how_it_works.steps.1.description') }}</p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                        <i class="fas fa-qrcode text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('landing.how_it_works.steps.2.title') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('landing.how_it_works.steps.2.description') }}</p>
                </div>

                <!-- Step 4 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-orange-500/30">
                        <i class="fas fa-user-tie text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">{{ __('landing.how_it_works.steps.3.title') }}</h3>
                    <p class="text-sm text-gray-500">{{ __('landing.how_it_works.steps.3.description') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="py-12 md:py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-16">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 md:mb-4">{{ __('landing.features.title') }}</h2>
                <p class="text-sm md:text-base text-gray-600 max-w-2xl mx-auto">{{ __('landing.features.subtitle') }}</p>
            </div>

            <!-- Responsive: 1 col mobile, 2 cols tablet, 3 cols desktop -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-green-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fab fa-whatsapp text-green-600 text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.0.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.0.description') }}</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fas fa-desktop text-primary text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.1.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.1.description') }}</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fas fa-motorcycle text-blue-600 text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.2.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.2.description') }}</p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-amber-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fas fa-calendar-check text-amber-600 text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.3.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.3.description') }}</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fas fa-qrcode text-emerald-600 text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.4.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.4.description') }}</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-2xl p-5 md:p-8 feature-card transition-all duration-300">
                    <div class="w-10 h-10 md:w-12 md:h-12 bg-rose-100 rounded-xl flex items-center justify-center mb-3 md:mb-4">
                        <i class="fas fa-chart-pie text-rose-600 text-lg md:text-xl"></i>
                    </div>
                    <h3 class="text-lg md:text-xl font-semibold text-gray-900 mb-2 md:mb-3">{{ __('landing.features.list.5.title') }}</h3>
                    <p class="text-sm md:text-base text-gray-600">{{ __('landing.features.list.5.description') }}</p>
                </div>
            </div>
        </div>
    </section>

    <!-- App Preview Section -->
    <section class="py-20 bg-gradient-to-b from-primary to-purple-800 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">{{ __('landing.app_preview.title') }}</h2>
                <p class="text-purple-200 max-w-2xl mx-auto">{{ __('landing.app_preview.subtitle') }}</p>
            </div>

            <!-- App Preview Image Placeholder -->
            <div class="relative mx-auto max-w-5xl">
                <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
                    <div class="bg-gray-100 px-4 py-3 flex items-center space-x-2">
                        <div class="w-3 h-3 bg-red-400 rounded-full"></div>
                        <div class="w-3 h-3 bg-yellow-400 rounded-full"></div>
                        <div class="w-3 h-3 bg-green-400 rounded-full"></div>
                    </div>
                    <div class="p-6 bg-gray-50">
                        <div class="grid grid-cols-4 gap-4">
                            <!-- Sidebar -->
                            <div class="bg-white rounded-xl p-4 shadow-sm">
                                <div class="flex items-center space-x-2 mb-4">
                                    <div class="w-8 h-8 bg-primary rounded-lg"></div>
                                    <span class="font-semibold text-gray-800">QashierWise</span>
                                </div>
                                <nav class="space-y-2">
                                    <div class="flex items-center space-x-2 bg-purple-100 text-primary px-3 py-2 rounded-lg">
                                        <i class="fas fa-inbox"></i>
                                        <span class="text-sm">Inbox</span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-gray-600 px-3 py-2">
                                        <i class="fas fa-utensils"></i>
                                        <span class="text-sm">Orders</span>
                                    </div>
                                    <div class="flex items-center space-x-2 text-gray-600 px-3 py-2">
                                        <i class="fas fa-calendar"></i>
                                        <span class="text-sm">Reservations</span>
                                    </div>
                                </nav>
                            </div>
                            <!-- Main Content -->
                            <div class="col-span-3 bg-white rounded-xl p-4 shadow-sm">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="font-semibold text-gray-800">Recent Conversations</h3>
                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">12 new</span>
                                </div>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white font-semibold">A</div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">Ahmad Rizki</p>
                                                <p class="text-xs text-gray-500">Mau pesan meja untuk 4 orang...</p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500">2m ago</span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">S</div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">Siti Nurhaliza</p>
                                                <p class="text-xs text-gray-500">Order delivery ke alamat...</p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-500">5m ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Integration Section -->
    <section class="py-16 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h3 class="text-xl font-semibold text-gray-900 mb-2">Integrasi yang didukung</h3>
            <p class="text-gray-500 mb-8">Sambungkan QashierWise untuk bisnis Anda—tanpa ribet.</p>
            <div class="flex flex-wrap justify-center items-center gap-8 md:gap-12">
                <div class="flex items-center space-x-2 text-gray-600">
                    <i class="fab fa-whatsapp text-2xl text-green-600"></i>
                    <span class="font-medium">WhatsApp Business API</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-600">
                    <i class="fas fa-brain text-2xl text-primary"></i>
                    <span class="font-medium">AI LLM (OpenAI/Claude)</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-600">
                    <i class="fas fa-qrcode text-2xl text-blue-500"></i>
                    <span class="font-medium">QRIS Midtrans</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-600">
                    <i class="fas fa-credit-card text-2xl text-emerald-500"></i>
                    <span class="font-medium">QRIS Xendit</span>
                </div>
                <div class="flex items-center space-x-2 text-gray-600">
                    <i class="fas fa-wallet text-2xl text-orange-500"></i>
                    <span class="font-medium">QRIS OkeOce</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing Section -->
    <section id="pricing" class="py-12 md:py-20 bg-gray-50" x-data="pricingSection()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-10 md:mb-16">
                <h2 class="text-2xl sm:text-3xl md:text-4xl font-bold text-gray-900 mb-3 md:mb-4">{{ __('landing.pricing.title') }}</h2>
                <p class="text-sm md:text-base text-gray-600 mb-6">{{ __('landing.pricing.subtitle') }}</p>

                <!-- Duration Toggle -->
                <div class="inline-flex items-center bg-white rounded-full p-1 shadow-md">
                    <button
                        @click="selectedDuration = '1_month'"
                        :class="selectedDuration === '1_month' ? 'bg-primary text-white' : 'text-gray-600 hover:text-gray-900'"
                        class="px-4 md:px-6 py-2 rounded-full font-semibold transition-all text-xs md:text-sm"
                    >
                        1 Bulan
                    </button>
                    <button
                        @click="selectedDuration = '3_months'"
                        :class="selectedDuration === '3_months' ? 'bg-primary text-white' : 'text-gray-600 hover:text-gray-900'"
                        class="px-4 md:px-6 py-2 rounded-full font-semibold transition-all text-xs md:text-sm"
                    >
                        3 Bulan
                    </button>
                    <button
                        @click="selectedDuration = '1_year'"
                        :class="selectedDuration === '1_year' ? 'bg-primary text-white' : 'text-gray-600 hover:text-gray-900'"
                        class="px-4 md:px-6 py-2 rounded-full font-semibold transition-all text-xs md:text-sm relative"
                    >
                        1 Tahun
                        <span class="absolute -top-2 -right-2 bg-green-700 text-white text-xs px-2 py-0.5 rounded-full font-bold">-10%</span>
                    </button>
                </div>

                <!-- Promo Code Input (only show if logged in) -->
                <template x-if="token">
                    <div class="mt-6 max-w-md mx-auto">
                        <div class="bg-white rounded-xl p-4 shadow-md">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-1"></i> Punya Kode Promo?
                            </label>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    x-model="promoCode"
                                    @input="promoError = null; promoSuccess = null"
                                    placeholder="Masukkan kode promo"
                                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent text-sm uppercase"
                                    :disabled="promoValidating"
                                >
                                <button
                                    @click="validatePromoCode()"
                                    :disabled="!promoCode || promoValidating"
                                    class="px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-all text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <span x-show="!promoValidating">Terapkan</span>
                                    <span x-show="promoValidating" x-cloak>
                                        <i class="fas fa-spinner fa-spin"></i>
                                    </span>
                                </button>
                            </div>
                            <!-- Promo Success Message -->
                            <div x-show="promoSuccess" x-cloak class="mt-2 p-2 bg-green-100 border border-green-400 text-green-700 rounded-lg text-xs">
                                <i class="fas fa-check-circle mr-1"></i>
                                <span x-text="promoSuccess"></span>
                            </div>
                            <!-- Promo Error Message -->
                            <div x-show="promoError" x-cloak class="mt-2 p-2 bg-red-100 border border-red-400 text-red-700 rounded-lg text-xs">
                                <i class="fas fa-exclamation-circle mr-1"></i>
                                <span x-text="promoError"></span>
                            </div>
                            <!-- Applied Promo Display -->
                            <div x-show="appliedPromo" x-cloak class="mt-2 flex items-center justify-between p-2 bg-purple-50 border border-purple-200 rounded-lg">
                                <div class="text-xs">
                                    <span class="font-semibold text-purple-900" x-text="appliedPromo?.code"></span>
                                    <span class="text-purple-700"> - Hemat </span>
                                    <span class="font-semibold text-purple-900" x-text="'Rp' + formatPrice(appliedPromo?.discount || 0)"></span>
                                </div>
                                <button
                                    @click="removePromoCode()"
                                    class="text-purple-600 hover:text-purple-800 text-xs"
                                >
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Error Message -->
            <div x-show="error" x-cloak class="mb-6 p-4 bg-red-100 border border-red-400 text-red-700 rounded-xl text-center max-w-md mx-auto">
                <span x-text="error"></span>
            </div>

            <!-- Mobile: horizontal scroll, Tablet+: grid -->
            <div class="md:hidden overflow-x-auto pb-4 -mx-4 px-4">
                <div class="flex gap-4 min-w-max">
                    <!-- Basic Plan (Mobile) -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200 w-72 flex-shrink-0 relative">
                        <template x-if="token && isCurrentPlan('free_trial')">
                            <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Paket Saat Ini
                            </div>
                        </template>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Basic</h3>
                        <p class="text-gray-500 text-xs mb-3">1 outlet, No admin + pickup</p>
                        <div class="mb-4">
                            <span class="text-2xl font-bold text-gray-900">Rp0</span>
                        </div>
                        <ul class="space-y-2 mb-6 text-xs text-gray-600">
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>AI chatbot dasar (% pesan/bulan)</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Reservasi & pickup orders</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Watermark menu digital</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Tanpa pembayaran online</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>1 user staf + Email support</li>
                        </ul>
                        <a href="/register" onclick="trackSignup('cta_button')" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition text-sm">
                            Mulai Gratis
                        </a>
                    </div>

                    <!-- Pro Plan (Mobile) -->
                    <div class="bg-white rounded-2xl p-5 border-2 border-primary relative w-72 flex-shrink-0">
                        <template x-if="token && isCurrentPlan('pro')">
                            <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Paket Saat Ini
                            </div>
                        </template>
                        <template x-if="!token || !isCurrentPlan('pro')">
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-white px-3 py-1 rounded-full text-xs font-semibold">
                                POPULER
                            </div>
                        </template>
                        <h3 class="text-lg font-bold text-gray-900 mb-2 mt-2">Pro</h3>
                        <p class="text-gray-500 text-xs mb-3">Hingga 2 outlet, delivery + QRIS</p>
                        <div class="mb-4">
                            <span class="text-2xl font-bold text-gray-900" x-text="'Rp' + formatPrice(getPrice('pro'))">Rp350.000</span>
                            <span class="text-gray-500 text-sm" x-show="selectedDuration === '1_month'">/bln</span>
                            <span class="text-gray-500 text-sm" x-show="selectedDuration === '3_months'">/3bln</span>
                            <span class="text-gray-500 text-sm" x-show="selectedDuration === '1_year'">/thn</span>
                            <div x-show="selectedDuration !== '1_month'" class="text-xs text-gray-600 mt-1">
                                <span x-text="'Rp' + formatPrice(getPricePerMonth('pro'))">Rp350.000</span>/bln
                            </div>
                        </div>
                        <ul class="space-y-2 mb-6 text-xs text-gray-600">
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Delivery + antrean & biaya</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Pembayaran QRIS unlimited</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Pengingat & auto confirm</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>XX pesan/bulan + Chat support</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Customer Base</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Analytic + ekspor CSV</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Webhook & API</li>
                        </ul>
                        <template x-if="token">
                            <button
                                @click="checkout('pro')"
                                :disabled="loading || isCurrentPlan('pro')"
                                class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span x-show="!loading || selectedPlan !== 'pro'">
                                    <span x-show="isCurrentPlan('pro')">Paket Aktif</span>
                                    <span x-show="!isCurrentPlan('pro')">Pilih Pro</span>
                                </span>
                                <span x-show="loading && selectedPlan === 'pro'" x-cloak>
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Memproses...
                                </span>
                            </button>
                        </template>
                        <template x-if="!token">
                            <a href="/login?redirect=pricing&plan=pro" class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all text-sm">
                                Pilih Pro
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tablet/Desktop: grid layout -->
            <div class="hidden md:grid md:grid-cols-2 gap-6 lg:gap-8 max-w-4xl mx-auto">
                <!-- Basic Plan -->
                <div class="bg-white rounded-2xl p-6 lg:p-8 border border-gray-200 relative">
                    <template x-if="token && isCurrentPlan('free_trial')">
                        <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Paket Saat Ini
                        </div>
                    </template>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Basic</h3>
                    <p class="text-gray-500 text-sm mb-4">1 outlet, No admin + pickup</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900">Rp0</span>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>AI chatbot dasar (% pesan/bulan)</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Reservasi & pickup orders</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Watermark menu digital</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Tanpa pembayaran online (bayar di tempat)</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>1 user staf + Email support (48hr)</li>
                    </ul>
                    <a href="/register" onclick="trackSignup('cta_button')" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                        Mulai Gratis
                    </a>
                </div>

                <!-- Pro Plan -->
                <div class="bg-white rounded-2xl p-6 lg:p-8 border-2 border-primary relative">
                    <template x-if="token && isCurrentPlan('pro')">
                        <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Paket Saat Ini
                        </div>
                    </template>
                    <template x-if="!token || !isCurrentPlan('pro')">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-white px-4 py-1 rounded-full text-xs font-semibold">
                            POPULER
                        </div>
                    </template>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pro</h3>
                    <p class="text-gray-500 text-sm mb-4">Hingga 2 outlet, delivery + QRIS</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900" x-text="'Rp' + formatPrice(getPrice('pro'))">Rp350.000</span>
                        <span class="text-gray-500" x-show="selectedDuration === '1_month'">/bln</span>
                        <span class="text-gray-500" x-show="selectedDuration === '3_months'">/3bln</span>
                        <span class="text-gray-500" x-show="selectedDuration === '1_year'">/thn</span>
                        <div x-show="selectedDuration !== '1_month'" class="text-sm text-gray-600 mt-1">
                            <span x-text="'Rp' + formatPrice(getPricePerMonth('pro'))">Rp350.000</span>/bln
                        </div>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Delivery + antrean & biaya</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Pembayaran QRIS unlimited</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Pengingat & auto confirm</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>XX pesan/bulan + Chat support (2hr)</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Customer Base</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Analytic + ekspor CSV</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Webhook & API</li>
                    </ul>
                    <template x-if="token">
                        <button
                            @click="checkout('pro')"
                            :disabled="loading || isCurrentPlan('pro')"
                            class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span x-show="!loading || selectedPlan !== 'pro'">
                                <span x-show="isCurrentPlan('pro')">Paket Aktif</span>
                                <span x-show="!isCurrentPlan('pro')">Pilih Pro</span>
                            </span>
                            <span x-show="loading && selectedPlan === 'pro'" x-cloak>
                                <i class="fas fa-spinner fa-spin mr-2"></i>Memproses...
                            </span>
                        </button>
                    </template>
                    <template x-if="!token">
                        <a href="/login?redirect=pricing&plan=pro" class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all">
                            Pilih Pro
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ __('landing.about.title') }}</h2>
                <p class="text-gray-600">{{ __('landing.about.subtitle') }}</p>
            </div>

            <div class="prose prose-lg max-w-none text-gray-600 mb-12">
                <h3 class="text-xl font-semibold text-gray-900">{{ __('landing.about.what_is_title') }}</h3>
                <p>
                    {{ __('landing.about.what_is_description_1') }}
                </p>
                <p>
                    {{ __('landing.about.what_is_description_2') }}
                </p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('landing.about.founder_title') }}</h3>
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-primary rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        AB
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 text-base">{{ __('landing.about.founder_name') }}</h3>
                        <p class="text-gray-600 text-sm">{{ __('landing.about.founder_description') }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-12">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">{{ __('landing.about.vision_title') }}</h3>
                <p class="text-gray-600">
                    {{ __('landing.about.vision_description') }}
                </p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">{{ __('landing.faq.title') }}</h2>
                <p class="text-gray-600">{{ __('landing.faq.subtitle') }}</p>
            </div>

            <div class="space-y-4" x-data="{ open: null }">
                <!-- FAQ Item 1 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 1 ? null : 1" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ __('landing.faq.items.0.question') }}</span>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" :class="{ 'rotate-180': open === 1 }"></i>
                    </button>
                    <div x-show="open === 1" x-transition class="px-6 pb-4 text-gray-600">
                        {{ __('landing.faq.items.0.answer') }}
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 2 ? null : 2" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ __('landing.faq.items.1.question') }}</span>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" :class="{ 'rotate-180': open === 2 }"></i>
                    </button>
                    <div x-show="open === 2" x-transition class="px-6 pb-4 text-gray-600">
                        {{ __('landing.faq.items.1.answer') }}
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 3 ? null : 3" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ __('landing.faq.items.2.question') }}</span>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" :class="{ 'rotate-180': open === 3 }"></i>
                    </button>
                    <div x-show="open === 3" x-transition class="px-6 pb-4 text-gray-600">
                        {{ __('landing.faq.items.2.answer') }}
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 4 ? null : 4" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">{{ __('landing.faq.items.3.question') }}</span>
                        <i class="fas fa-chevron-down text-gray-500 transform transition-transform" :class="{ 'rotate-180': open === 4 }"></i>
                    </button>
                    <div x-show="open === 4" x-transition class="px-6 pb-4 text-gray-600">
                        {{ __('landing.faq.items.3.answer') }}
                    </div>
                </div>
            </div>
        </div>
    </section>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="lazy" style="width: 28px; height: 28px;">
                        <span class="text-xl font-bold text-primary">QashierWise</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        {{ __('landing.footer.company_description') }}
                    </p>
                    <div class="text-sm text-gray-600">
                        <p class="font-semibold text-gray-900 mb-1">{{ __('landing.footer.address_title') }}</p>
                        <p>{!! __('landing.footer.address') !!}</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4 text-base">{{ __('landing.footer.navigation_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a></li>
                        <li><a href="#about" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.about') }}</a></li>
                        <li><a href="#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4 text-base">{{ __('landing.footer.legal_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.privacy_policy') }}</a></li>
                        <li><a href="/terms-of-service" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.terms_of_service') }}</a></li>
                        <li><a href="/refund-policy" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.refund_policy') }}</a></li>
                    </ul>
                </div>

                 <!-- Product -->
                 <div>
                     <h3 class="text-gray-900 font-semibold mb-4 text-base">{{ __('landing.footer.product_title') }}</h3>
                     <ul class="space-y-2 text-sm">
                         <li><a href="#" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.console') }}</a></li>
                         <li><a href="#" class="text-gray-600 hover:text-primary transition">{{ __('landing.footer.chatbot') }}</a></li>
                     </ul>
                 </div>

                 <!-- Social Media -->
                 <div>
                     <h3 class="text-gray-900 font-semibold mb-4 text-base">{{ __('landing.footer.follow_us') }}</h3>
                     <p class="text-sm text-gray-600 mb-4">{{ __('landing.footer.follow_us_desc') }}</p>
                     <x-social-links :size="'md'" :showLabels="false" />
                 </div>
             </div>

            <div class="border-t border-gray-200 pt-8 text-center text-sm text-gray-600">
                <p>{!! __('landing.footer.copyright') !!}</p>
            </div>
        </div>
    </footer>

    <!-- Pricing Section Alpine.js Component -->
    <script>
        function pricingSection() {
            return {
                loading: false,
                error: null,
                selectedPlan: null,
                selectedDuration: '1_month', // Default to monthly
                currentPlan: null,
                token: null,

                // Promo code state
                promoCode: '',
                promoValidating: false,
                promoError: null,
                promoSuccess: null,
                appliedPromo: null,

                // Pricing data from config
                plans: @js(config('subscription.plans')),

                init() {
                    // Get auth token from localStorage (stored as 'token' during login)
                    this.token = localStorage.getItem('token');
                    if (this.token) {
                        this.fetchSubscriptionStatus();
                        // Check if we need to auto-checkout after login redirect
                        this.checkAutoCheckout();
                    }

                    // Watch for duration changes and reset promo code
                    this.$watch('selectedDuration', () => {
                        if (this.appliedPromo) {
                            this.removePromoCode();
                        }
                    });
                },

                getPrice(plan) {
                    const planData = this.plans?.[plan]?.durations?.[this.selectedDuration];
                    const basePrice = planData?.price ?? 0;
                    // If promo is applied and matches this plan, show discounted price
                    if (this.appliedPromo && this.appliedPromo.plan === plan) {
                        return this.appliedPromo.final_amount;
                    }
                    return basePrice;
                },

                getPricePerMonth(plan) {
                    const finalPrice = this.getPrice(plan);
                    const months = this.selectedDuration === '1_month' ? 1 :
                                   this.selectedDuration === '3_months' ? 3 : 12;
                    return Math.round(finalPrice / months);
                },

                getDiscount(plan) {
                    return this.plans?.[plan]?.durations?.[this.selectedDuration]?.discount ?? 0;
                },

                formatPrice(amount) {
                    return new Intl.NumberFormat('id-ID').format(amount);
                },

                async validatePromoCode() {
                    if (!this.promoCode || this.promoValidating) return;

                    this.promoValidating = true;
                    this.promoError = null;
                    this.promoSuccess = null;

                    try {
                        // We need to validate against a specific plan
                        // For now, validate against standard plan
                        // In real scenario, you might want to validate when user clicks checkout
                        const response = await fetch('/api/promo-codes/validate', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${this.token}`,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                code: this.promoCode.toUpperCase(),
                                plan_id: 'pro', // Validate for pro plan
                                duration: this.selectedDuration
                            })
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.promoSuccess = data.data.message;
                            this.appliedPromo = {
                                code: data.data.code,
                                discount: data.data.discount,
                                final_amount: data.data.final_amount,
                                original_amount: data.data.original_amount,
                                plan: 'pro' // Store which plan this was validated for
                            };
                        } else {
                            this.promoError = data.error?.message || 'Kode promo tidak valid';
                            this.appliedPromo = null;
                        }
                    } catch (e) {
                        console.error('Promo validation error:', e);
                        this.promoError = 'Terjadi kesalahan. Silakan coba lagi.';
                        this.appliedPromo = null;
                    } finally {
                        this.promoValidating = false;
                    }
                },

                removePromoCode() {
                    this.promoCode = '';
                    this.appliedPromo = null;
                    this.promoError = null;
                    this.promoSuccess = null;
                },

                checkAutoCheckout() {
                    // Check URL parameters for auto-checkout after login
                    const urlParams = new URLSearchParams(window.location.search);
                    const plan = urlParams.get('plan');
                    const shouldCheckout = urlParams.get('checkout') === 'true';

                    if (plan && shouldCheckout && this.token) {
                        // Clean up URL parameters but keep the hash
                        const hash = window.location.hash || '';
                        window.history.replaceState({}, document.title, window.location.pathname + hash);

                        // Trigger checkout after a short delay to ensure component is ready
                        setTimeout(() => {
                            this.checkout(plan);
                        }, 500);
                    }
                },

                async fetchSubscriptionStatus() {
                    try {
                        const response = await fetch('/api/subscription/status', {
                            headers: {
                                'Authorization': `Bearer ${this.token}`,
                                'Accept': 'application/json',
                            }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            if (data.success && data.data.subscription) {
                                this.currentPlan = data.data.subscription.plan_name;
                            }
                        }
                    } catch (e) {
                        console.error('Failed to fetch subscription status:', e);
                    }
                },

                isCurrentPlan(plan) {
                    return this.currentPlan === plan;
                },

                async checkout(planId) {
                    if (this.loading) return;

                    this.loading = true;
                    this.error = null;
                    this.selectedPlan = planId;

                    try {
                        const requestBody = {
                            plan_id: planId,
                            duration: this.selectedDuration
                        };

                        // Add promo code if applied
                        if (this.appliedPromo && this.appliedPromo.code) {
                            requestBody.promo_code = this.appliedPromo.code;
                        }

                        const response = await fetch('/api/subscription/checkout', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${this.token}`,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(requestBody)
                        });

                        if (!response.ok) {
                            const errorData = await response.json().catch(() => ({ error: { message: 'Terjadi kesalahan pada server' } }));
                            throw new Error(errorData.error?.message || 'Gagal membuat sesi checkout');
                        }

                        const data = await response.json();

                        const checkoutUrl = data.data?.checkout_url ?? data.data?.redirect_url;

                        if (response.ok && checkoutUrl) {
                            window.location.href = checkoutUrl;
                        } else {
                            this.error = data.error?.message || 'Gagal membuat sesi checkout. Silakan coba lagi.';
                        }
                    } catch (e) {
                        console.error('Checkout error:', e);
                        this.error = 'Terjadi kesalahan. Silakan coba lagi.';
                    } finally {
                        this.loading = false;
                        this.selectedPlan = null;
                    }
                }
            };
        }
    </script>

    <!-- Cookie Consent Banner -->
    <x-cookie-consent />

    <!-- LocalBusiness Schema for Google Business Profile -->
    <script type="application/ld+json">
{!! json_encode(App\Helpers\SeoHelper::getLocalBusinessStructuredData(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) !!}
    </script>
</body>
</html>
