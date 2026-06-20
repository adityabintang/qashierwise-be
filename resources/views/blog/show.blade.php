<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(config('app.env') === 'local')
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif
    <title>{{ $post->seo_title ?: $post->title }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $post->seo_description ?: ($post->excerpt ?: 'Artikel dari QashierWise') }}">

    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/logo-48.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">

    <!-- Open Graph / Social Media -->
    <meta property="og:title" content="{{ $post->seo_title ?: $post->title }}">
    <meta property="og:description" content="{{ $post->seo_description ?: ($post->excerpt ?: 'Artikel dari QashierWise') }}">
    <meta property="og:image" content="{{ $post->featured_image_url }}">
    <meta property="og:type" content="article">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/blog-show.css') }}">
</head>
<body class="blog-show-page">

    <!-- Glassmorphism Navbar -->
    <nav class="blog-nav" id="blogNav">
        <div class="blog-nav-inner">
            <a href="/" class="blog-nav-logo">
                <img src="{{ asset('images/logo-48.png') }}" alt="QashierWise">
                <span>QashierWise</span>
            </a>
            <div class="blog-nav-links">
                <a href="/" class="blog-nav-link hide-sm">Beranda</a>
                <a href="{{ route('blog.index') }}" class="blog-nav-link active">Blog</a>
                <a href="/login" class="blog-nav-cta">Masuk</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="blog-hero">
        <!-- Title and Meta Info -->
        <div class="blog-hero-header">
            <!-- Meta Info -->
            <div class="blog-meta">
                <span class="blog-meta-date">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                    {{ \App\Helpers\TimezoneDisplayHelper::formatWithLabel($post->published_at) }}
                </span>

                @if ($post->category)
                    <span class="blog-meta-category">{{ $post->category->name }}</span>
                @endif

                <span class="blog-meta-separator"></span>

                <span class="blog-meta-author">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                    </svg>
                    {{ $post->author?->name ?? 'Admin' }}
                </span>
            </div>

            <!-- Title -->
            <h1 class="blog-title">{{ $post->title }}</h1>
        </div>

        <!-- Featured Image -->
        <div class="blog-hero-image-wrap">
            <img
                src="{{ $post->featured_image_url }}"
                alt="{{ $post->title }}"
                class="blog-hero-image"
                loading="eager"
            >
        </div>
    </div>

    <!-- Glass Article -->
    <main class="blog-article-wrap">
        <article class="blog-article">

            <!-- Excerpt -->
            @if ($post->excerpt)
                <p class="blog-excerpt">{{ $post->excerpt }}</p>
            @endif

            <!-- Content -->
            <div class="blog-content">
                {!! $post->content !!}
            </div>

            <!-- Tags -->
            @if ($post->tags->isNotEmpty())
                <div class="blog-tags">
                    @foreach ($post->tags as $tag)
                        <span class="blog-tag">#{{ $tag->name }}</span>
                    @endforeach
                </div>
            @endif

            <!-- Share -->
            <div class="blog-share">
                <span class="blog-share-label">Bagikan</span>
                <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(request()->url()) }}" target="_blank" rel="noopener" class="blog-share-btn" aria-label="Share on Twitter">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(request()->url()) }}" target="_blank" rel="noopener" class="blog-share-btn" aria-label="Share on Facebook">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="https://wa.me/?text={{ urlencode($post->title . ' ' . request()->url()) }}" target="_blank" rel="noopener" class="blog-share-btn" aria-label="Share on WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                </a>
                <button class="blog-share-btn" aria-label="Copy link" onclick="navigator.clipboard.writeText(window.location.href).then(() => { this.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'m4.5 12.75 6 6 9-13.5\' /></svg>'; setTimeout(() => { this.innerHTML = '<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke-width=\'1.5\' stroke=\'currentColor\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' d=\'M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244\' /></svg>'; }, 2000); })">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" /></svg>
                </button>
            </div>
        </article>
    </main>

    <!-- Related Posts -->
    @if ($relatedPosts->isNotEmpty())
        <section class="blog-related">
            <div class="blog-related-header">
                <h2>Artikel Lainnya</h2>
                <div class="blog-related-line"></div>
            </div>
            <div class="blog-related-grid">
                @foreach ($relatedPosts as $i => $relatedPost)
                    <a href="{{ route('blog.show', $relatedPost->slug) }}" class="blog-related-card">
                        <h3>{{ $relatedPost->title }}</h3>
                        @if ($relatedPost->excerpt)
                            <p class="excerpt">{{ $relatedPost->excerpt }}</p>
                        @endif
                        <span class="card-date">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            {{ \App\Helpers\TimezoneDisplayHelper::formatWithLabel($relatedPost->published_at) }}
                        </span>
                        <span class="card-arrow">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Footer -->
    <footer style="position: relative; z-index: 2; background: #581c87; color: #f3e8ff; padding: 4rem 0;">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="lazy" style="width: 28px; height: 28px;">
                        <span class="text-xl font-bold" style="color: #fff;">QashierWise</span>
                    </div>
                    <p class="text-sm mb-4" style="color: rgba(243, 232, 255, 0.7);">
                        {{ __('landing.footer.company_description') }}
                    </p>
                    <div class="text-sm" style="color: rgba(243, 232, 255, 0.7);">
                        <p class="font-semibold mb-1" style="color: rgba(255,255,255,0.9);">{{ __('landing.footer.address_title') }}</p>
                        <p>{!! __('landing.footer.address') !!}</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h3 class="font-semibold mb-4 text-base" style="color: #fff;">{{ __('landing.footer.navigation_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#fitur" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="/#pricing" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.nav.pricing') }}</a></li>
                        <li><a href="/#about" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.nav.about') }}</a></li>
                        <li><a href="/#faq" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.nav.faq') }}</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h3 class="font-semibold mb-4 text-base" style="color: #fff;">{{ __('landing.footer.legal_title') }}</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.privacy_policy') }}</a></li>
                        <li><a href="/terms-of-service" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.terms_of_service') }}</a></li>
                        <li><a href="/refund-policy" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.refund_policy') }}</a></li>
                    </ul>
                </div>

                 <!-- Product -->
                 <div>
                     <h3 class="font-semibold mb-4 text-base" style="color: #fff;">{{ __('landing.footer.product_title') }}</h3>
                     <ul class="space-y-2 text-sm">
                         <li><a href="#" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.console') }}</a></li>
                         <li><a href="#" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.chatbot') }}</a></li>
                     </ul>
                 </div>

                 <!-- Social Media -->
                 <div>
                     <h3 class="font-semibold mb-4 text-base" style="color: #fff;">{{ __('landing.footer.follow_us') }}</h3>
                     <p class="text-sm mb-4" style="color: rgba(243, 232, 255, 0.7);">{{ __('landing.footer.follow_us_desc') }}</p>
                     <x-social-links :size="'md'" :showLabels="false" />
                 </div>
             </div>

            <div class="pt-8 text-center text-sm" style="border-top: 1px solid rgba(255, 255, 255, 0.12); color: rgba(243, 232, 255, 0.6);">
                <p>{!! __('landing.footer.copyright') !!}</p>
            </div>
        </div>
    </footer>

    <!-- Back to Top -->
    <button class="back-to-top" id="backToTop" aria-label="Kembali ke atas" onclick="window.scrollTo({ top: 0, behavior: 'smooth' })">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
        </svg>
    </button>

    <x-timezone-detector-script />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const nav = document.getElementById('blogNav');
            const backToTop = document.getElementById('backToTop');

            // Scroll handler
            const handleScroll = () => {
                const scrollY = window.scrollY;

                // Nav shadow
                if (scrollY > 20) {
                    nav.classList.add('scrolled');
                } else {
                    nav.classList.remove('scrolled');
                }

                // Back to top visibility
                if (scrollY > 400) {
                    backToTop.classList.add('visible');
                } else {
                    backToTop.classList.remove('visible');
                }
            };

            // Throttled scroll
            let ticking = false;
            window.addEventListener('scroll', () => {
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        handleScroll();
                        ticking = false;
                    });
                    ticking = true;
                }
            }, { passive: true });

            handleScroll();


        });
    </script>
</body>
</html>
