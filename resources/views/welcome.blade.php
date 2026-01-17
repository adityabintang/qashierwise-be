<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Primary Meta Tags -->
    <title>QashierWise - AI Chatbot WhatsApp untuk Restoran | Reservasi & Order Otomatis</title>
    <meta name="title" content="QashierWise - AI Chatbot WhatsApp untuk Restoran | Reservasi & Order Otomatis">
    <meta name="description" content="Platform AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS. Kelola reservasi, pesanan, delivery, dan pembayaran dalam satu dashboard. Coba gratis 14 hari!">
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

    <!-- Preconnect for critical resources -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="preconnect" href="https://images.unsplash.com">

    <!-- DNS Prefetch for additional resources -->
    <link rel="dns-prefetch" href="https://fonts.googleapis.com">
    <link rel="dns-prefetch" href="https://fonts.gstatic.com">
    <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">

    <!-- Preload critical fonts (non-blocking) -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"></noscript>

    <!-- Additional SEO Meta -->
    <meta name="geo.region" content="ID-JT">
    <meta name="geo.placename" content="Salatiga">
    <meta name="geo.position" content="-7.3305;110.5084">
    <meta name="ICBM" content="-7.3305, 110.5084">

    <!-- Critical CSS inline for above-the-fold content -->
    <style>
        /* Critical CSS - Inline for faster FCP */
        *,::after,::before{box-sizing:border-box;border:0 solid #e5e7eb}
        html{line-height:1.5;-webkit-text-size-adjust:100%;font-family:Inter,ui-sans-serif,system-ui,sans-serif}
        body{margin:0;line-height:inherit}
        .bg-white{background-color:#fff}
        .text-gray-900{color:#111827}
        .text-gray-600{color:#4b5563}
        .font-bold{font-weight:700}
        .font-semibold{font-weight:600}
        .text-xl{font-size:1.25rem;line-height:1.75rem}
        .text-3xl{font-size:1.875rem;line-height:2.25rem}
        .rounded-xl{border-radius:.75rem}
        .rounded-2xl{border-radius:1rem}
        .px-4{padding-left:1rem;padding-right:1rem}
        .py-2{padding-top:.5rem;padding-bottom:.5rem}
        .mb-4{margin-bottom:1rem}
        .flex{display:flex}
        .items-center{align-items:center}
        .justify-between{justify-content:space-between}
        .space-x-2>:not([hidden])~:not([hidden]){margin-left:.5rem}
        .fixed{position:fixed}
        .top-0{top:0}
        .left-0{left:0}
        .right-0{right:0}
        .z-50{z-index:50}
        .h-16{height:4rem}
        .h-7{height:1.75rem}
        .max-w-7xl{max-width:80rem}
        .mx-auto{margin-left:auto;margin-right:auto}
        .hidden{display:none}
        .pt-24{padding-top:6rem}
        .pb-12{padding-bottom:3rem}
        .text-center{text-align:center}
        .leading-tight{line-height:1.25}
        .gradient-text{background:linear-gradient(135deg,#4910ce 0%,#7c3aed 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .hero-gradient{background:linear-gradient(135deg,#f3e8ff 0%,#ede9fe 50%,#faf5ff 100%)}
        .bg-primary{background-color:#4910ce}
        .text-primary{color:#4910ce}
        .text-white{color:#fff}
        @media(min-width:768px){.md\\:flex{display:flex}.md\\:hidden{display:none}.md\\:pt-32{padding-top:8rem}.md\\:text-5xl{font-size:3rem;line-height:1}}
        @media(min-width:1024px){.lg\\:grid{display:grid}.lg\\:grid-cols-2{grid-template-columns:repeat(2,minmax(0,1fr))}.lg\\:text-left{text-align:left}.lg\\:text-6xl{font-size:3.75rem;line-height:1}}
        [x-cloak]{display:none!important}
        body{font-family:'Inter',sans-serif}
    </style>

    <!-- Tailwind CSS - Deferred loading -->
    <script>
        // Load Tailwind CSS asynchronously after critical content
        (function() {
            var tw = document.createElement('script');
            tw.src = 'https://cdn.tailwindcss.com';
            tw.onload = function() {
                tailwind.config = {
                    theme: {
                        extend: {
                            colors: {
                                primary: '#4910ce',
                            }
                        }
                    }
                }
            };
            document.head.appendChild(tw);
        })();
    </script>

    <!-- Font Awesome - Deferred loading (non-critical icons) -->
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

    <!-- Alpine.js - Deferred -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

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
            {
                "@type": "ListItem",
                "position": 1,
                "name": "Home",
                "item": "https://qashierwise.com"
            }
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
            "ratingValue": "4.8",
            "reviewCount": "24",
            "bestRating": "5",
            "worstRating": "1"
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
                    "ratingValue": "5",
                    "bestRating": "5"
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
                    "ratingValue": "5",
                    "bestRating": "5"
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
                    "ratingValue": "4",
                    "bestRating": "5"
                }
            }
        ],
        "offers": {
            "@type": "Offer",
            "price": "249000",
            "priceCurrency": "IDR",
            "priceValidUntil": "2025-12-31",
            "availability": "https://schema.org/InStock",
            "url": "https://qashierwise.com/#pricing",
            "shippingDetails": {
                "@type": "OfferShippingDetails",
                "shippingRate": {
                    "@type": "MonetaryAmount",
                    "value": "0",
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
                        "minValue": "0",
                        "maxValue": "1",
                        "unitCode": "DAY"
                    },
                    "transitTime": {
                        "@type": "QuantitativeValue",
                        "minValue": "0",
                        "maxValue": "0",
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
        .feature-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
    </style>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav x-data="{ menuOpen: false }" class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2">
                    <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="eager">
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
                <div class="relative order-2 w-full max-w-md lg:max-w-none mx-auto mt-8 lg:mt-0">
                    <img
                        src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=665&q=75"
                        srcset="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=75 400w,
                                https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=665&q=75 665w,
                                https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=75 800w"
                        sizes="(max-width: 640px) 400px, (max-width: 1024px) 665px, 800px"
                        alt="Restaurant ordering"
                        class="rounded-2xl shadow-2xl w-full"
                        width="665"
                        height="444"
                        fetchpriority="high"
                        decoding="async"
                        loading="eager">
                    <!-- Floating Elements - Hidden on small mobile, visible on larger screens -->
                    <div class="hidden sm:block absolute -bottom-4 md:-bottom-6 -left-2 md:-left-6 bg-white rounded-xl p-3 md:p-4 shadow-xl">
                        <div class="flex items-center space-x-2 md:space-x-3">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fab fa-whatsapp text-green-600 text-lg md:text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs md:text-sm font-semibold text-gray-900">{{ __('landing.hero.floating_messages') }}</p>
                                <p class="text-xs text-gray-500">{{ __('landing.hero.floating_messages_count') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="hidden sm:block absolute -top-2 md:-top-4 -right-2 md:-right-4 bg-white rounded-xl p-3 md:p-4 shadow-xl">
                        <div class="flex items-center space-x-2 md:space-x-3">
                            <div class="w-8 h-8 md:w-10 md:h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-primary text-lg md:text-xl"></i>
                            </div>
                            <div>
                                <p class="text-xs md:text-sm font-semibold text-gray-900">{{ __('landing.hero.floating_sales') }}</p>
                                <p class="text-xs text-green-600">{{ __('landing.hero.floating_sales_trend') }}</p>
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
                <p class="text-sm md:text-base text-gray-600">{{ __('landing.pricing.subtitle') }}</p>
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
                        <a href="/register" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition text-sm">
                            Mulai Gratis
                        </a>
                    </div>

                    <!-- Standard Plan (Mobile) -->
                    <div class="bg-white rounded-2xl p-5 border-2 border-primary relative w-72 flex-shrink-0">
                        <template x-if="token && isCurrentPlan('standard')">
                            <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Paket Saat Ini
                            </div>
                        </template>
                        <template x-if="!token || !isCurrentPlan('standard')">
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-white px-3 py-1 rounded-full text-xs font-semibold">
                                POPULER
                            </div>
                        </template>
                        <h3 class="text-lg font-bold text-gray-900 mb-2 mt-2">Standard</h3>
                        <p class="text-gray-500 text-xs mb-3">Hingga 2 outlet, delivery + QRIS</p>
                        <div class="mb-4">
                            <span class="text-2xl font-bold text-gray-900">Rp249.000</span>
                            <span class="text-gray-500 text-sm">/bln</span>
                        </div>
                        <ul class="space-y-2 mb-6 text-xs text-gray-600">
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Delivery + antrean & biaya</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Pembayaran QRIS unlimited</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Pengingat & auto confirm</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>XX pesan/bulan + Chat support</li>
                            <li class="flex items-start"><i class="fas fa-check text-primary mr-2 mt-0.5"></i>Customer Base</li>
                        </ul>
                        <template x-if="token">
                            <button
                                @click="checkout('standard')"
                                :disabled="loading || isCurrentPlan('standard')"
                                class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all text-sm disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span x-show="!loading || selectedPlan !== 'standard'">
                                    <span x-show="isCurrentPlan('standard')">Paket Aktif</span>
                                    <span x-show="!isCurrentPlan('standard')">Pilih Standard</span>
                                </span>
                                <span x-show="loading && selectedPlan === 'standard'" x-cloak>
                                    <i class="fas fa-spinner fa-spin mr-2"></i>Memproses...
                                </span>
                            </button>
                        </template>
                        <template x-if="!token">
                            <a href="/login?redirect=pricing&plan=standard" class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all text-sm">
                                Pilih Standard
                            </a>
                        </template>
                    </div>

                    <!-- Pro Plan (Mobile) -->
                    <div class="bg-white rounded-2xl p-5 border border-gray-200 w-72 flex-shrink-0 relative">
                        <template x-if="token && isCurrentPlan('pro')">
                            <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                                Paket Saat Ini
                            </div>
                        </template>
                        <h3 class="text-lg font-bold text-gray-900 mb-2">Pro</h3>
                        <p class="text-gray-500 text-xs mb-3">Tim unlimited, Analytic & API</p>
                        <div class="mb-4">
                            <span class="text-2xl font-bold text-gray-900">Rp2.990.000</span>
                            <span class="text-gray-500 text-sm">/bln</span>
                        </div>
                        <ul class="space-y-2 mb-6 text-xs text-gray-600">
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Analytic + ekspor CSV</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Webhook & API</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Multi-outlet & branding</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>Priority routing & handover</li>
                            <li class="flex items-start"><i class="fas fa-check text-green-600 mr-2 mt-0.5"></i>SLA + dedicated support</li>
                        </ul>
                        <template x-if="token">
                            <button
                                @click="checkout('pro')"
                                :disabled="loading || isCurrentPlan('pro')"
                                class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition text-sm disabled:opacity-50 disabled:cursor-not-allowed"
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
                            <a href="/login?redirect=pricing&plan=pro" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition text-sm">
                                Pilih Pro
                            </a>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Tablet/Desktop: grid layout -->
            <div class="hidden md:grid md:grid-cols-3 gap-6 lg:gap-8 max-w-5xl mx-auto">
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
                    <a href="/register" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                        Mulai Gratis
                    </a>
                </div>

                <!-- Standard Plan -->
                <div class="bg-white rounded-2xl p-6 lg:p-8 border-2 border-primary relative">
                    <template x-if="token && isCurrentPlan('standard')">
                        <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Paket Saat Ini
                        </div>
                    </template>
                    <template x-if="!token || !isCurrentPlan('standard')">
                        <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-white px-4 py-1 rounded-full text-xs font-semibold">
                            POPULER
                        </div>
                    </template>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Standard</h3>
                    <p class="text-gray-500 text-sm mb-4">Hingga 2 outlet, delivery + QRIS</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900">Rp249.000</span>
                        <span class="text-gray-500">/bln</span>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Delivery + antrean & biaya</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Pembayaran QRIS unlimited</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Pengingat & auto confirm</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>XX pesan/bulan + Chat support (2hr)</li>
                        <li class="flex items-center"><i class="fas fa-check text-primary mr-2"></i>Customer Base</li>
                    </ul>
                    <template x-if="token">
                        <button
                            @click="checkout('standard')"
                            :disabled="loading || isCurrentPlan('standard')"
                            class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span x-show="!loading || selectedPlan !== 'standard'">
                                <span x-show="isCurrentPlan('standard')">Paket Aktif</span>
                                <span x-show="!isCurrentPlan('standard')">Pilih Standard</span>
                            </span>
                            <span x-show="loading && selectedPlan === 'standard'" x-cloak>
                                <i class="fas fa-spinner fa-spin mr-2"></i>Memproses...
                            </span>
                        </button>
                    </template>
                    <template x-if="!token">
                        <a href="/login?redirect=pricing&plan=standard" class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all">
                            Pilih Standard
                        </a>
                    </template>
                </div>

                <!-- Pro Plan -->
                <div class="bg-white rounded-2xl p-6 lg:p-8 border border-gray-200 relative">
                    <template x-if="token && isCurrentPlan('pro')">
                        <div class="absolute -top-3 right-4 bg-green-600 text-white px-3 py-1 rounded-full text-xs font-semibold">
                            Paket Saat Ini
                        </div>
                    </template>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pro</h3>
                    <p class="text-gray-500 text-sm mb-4">Tim unlimited, Analytic & API</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900">Rp2.990.000</span>
                        <span class="text-gray-500">/bln</span>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Analytic + ekspor CSV</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Webhook & API</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Multi-outlet & branding</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>Priority routing & handover</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-600 mr-2"></i>SLA + dedicated support</li>
                    </ul>
                    <template x-if="token">
                        <button
                            @click="checkout('pro')"
                            :disabled="loading || isCurrentPlan('pro')"
                            class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
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
                        <a href="/login?redirect=pricing&plan=pro" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
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
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="lazy">
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
                currentPlan: null,
                token: null,

                init() {
                    // Get auth token from localStorage (stored as 'token' during login)
                    this.token = localStorage.getItem('token');
                    if (this.token) {
                        this.fetchSubscriptionStatus();
                        // Check if we need to auto-checkout after login redirect
                        this.checkAutoCheckout();
                    }
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
                        const response = await fetch('/api/subscription/checkout', {
                            method: 'POST',
                            headers: {
                                'Authorization': `Bearer ${this.token}`,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ plan_id: planId })
                        });

                        const data = await response.json();

                        if (data.success && data.data.checkout_url) {
                            // Redirect to Polar.sh checkout
                            window.location.href = data.data.checkout_url;
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
</body>
</html>
