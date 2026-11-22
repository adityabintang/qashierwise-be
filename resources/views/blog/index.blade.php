<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Blog | {{ config('app.name') }}</title>
    <meta name="description" content="Baca artikel terbaru dari {{ config('app.name') }}.">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo-16.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('images/logo-48.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/icon-192x192.png') }}">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('images/icon-512x512.png') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="fly-blog-page">
    <header class="fly-blog-navbar-wrap">
        <div class="fly-blog-navbar max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="/" class="fly-blog-logo" aria-label="QashierWise Home">
                <picture>
                    <source media="(min-width: 1024px)" srcset="{{ asset('images/logo-64.png') }}">
                    <source media="(min-width: 768px)" srcset="{{ asset('images/logo-48.png') }}">
                    <source media="(min-width: 640px)" srcset="{{ asset('images/logo-32.png') }}">
                    <img src="{{ asset('images/logo-32.png') }}" alt="QashierWise" class="fly-logo-icon">
                </picture>
                <span class="fly-logo-word">Blog</span>
            </a>

            <div class="fly-blog-search-wrapper">
                <form action="{{ route('blog.index') }}" method="GET" class="fly-blog-search-form" id="blogSearchForm">
                    <div class="fly-search-input-group">
                        <svg class="fly-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input 
                            type="text" 
                            name="search" 
                            id="searchInput"
                            value="{{ $search ?? '' }}" 
                            placeholder="{{ __('blog.search_placeholder') }}" 
                            class="fly-search-input"
                            aria-label="{{ __('blog.search_placeholder') }}"
                            autocomplete="off"
                        >
                        @if($search ?? false)
                            <button type="button" class="fly-search-clear" id="clearSearch" aria-label="Clear search">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        @endif
                        <div class="fly-search-loading" id="searchLoading" style="display: none;">
                            <svg class="fly-spinner" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                    </div>
                    
                    <div class="fly-sort-group">
                        <select name="sort" id="sort" class="fly-sort-select">
                            <option value="latest" {{ ($sort ?? 'latest') === 'latest' ? 'selected' : '' }}>{{ __('blog.sort_latest') }}</option>
                            <option value="oldest" {{ ($sort ?? 'latest') === 'oldest' ? 'selected' : '' }}>{{ __('blog.sort_oldest') }}</option>
                            <option value="title" {{ ($sort ?? 'latest') === 'title' ? 'selected' : '' }}>{{ __('blog.sort_title') }}</option>
                        </select>
                    </div>

                    <div class="fly-sort-group">
                        <select name="category" id="category" class="fly-sort-select">
                            <option value="">{{ __('blog.category_all') }}</option>
                            @foreach(($categories ?? collect()) as $category)
                                <option value="{{ $category->slug }}" {{ ($selectedCategory ?? '') === $category->slug ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

                <!-- CTA Buttons (Desktop) -->
                <div class="hidden md:flex items-center space-x-4">
                    <!-- Language Switcher -->
                    <x-language-switcher />

                    <a href="/" class="fly-btn ghost">{{ __('blog.home') }}</a>
                    @auth
                        <a href="/dashboard" class="fly-btn primary">{{ __('blog.dashboard') }}</a>
                    @else
                        <a href="/login" class="fly-btn primary">{{ __('blog.login') }}</a>
                    @endauth
                </div>
        </div>
    </header>

    <main>
        <section class="fly-hero-section {{ $featuredPost ? '' : 'is-empty' }}">
            <img
                src="/blog-cover.webp"
                alt="blog-cover"
                class="fly-hero-bg"
            >

            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                @if ($featuredPost)
                    @php
                        $featuredReadTime = max(1, (int) ceil(str_word_count(strip_tags($featuredPost->content ?? $featuredPost->excerpt ?? '')) / 200));
                        $featuredImage = $featuredPost->featured_image_url ?: 'https://fly.io/blog/unfortunately-mcp/assets/whack.webp';
                    @endphp

                    <article class="fly-hero-card">
                        <div class="fly-hero-copy">
                            <span class="fly-meta-row">
                                <span>By {{ strtoupper($featuredPost->author?->name ?? 'QASHIERWISE') }}</span>
                                <span class="fly-line"></span>
                                <span>{{ $featuredPost->published_at?->format('d M Y') ?? now()->format('d M Y') }}</span>
                            </span>

                            <h1>{{ $featuredPost->title }}</h1>

                            <p class="fly-hero-excerpt">{{ $featuredPost->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($featuredPost->content), 220) }}</p>

                            <p class="fly-readmore">{{ __('blog.read_more') }} <span aria-hidden="true">→</span></p>
                            <a href="{{ route('blog.show', $featuredPost->slug) }}" class="fly-overlay-link">
                                <span class="sr-only">{{ __('blog.read_more') }}</span>
                            </a>
                        </div>

                        <img src="{{ $featuredImage }}" alt="{{ $featuredPost->title }}" class="fly-hero-image">
                    </article>
                @endif
            </div>
        </section>

        <section class="fly-cards-section max-w-7xl mx-auto px-4 sm:px-6 lg:px-8" id="blog-cards-section">
            @if (! $featuredPost)
                <div class="fly-empty-state">
                    <h2>{{ __('blog.no_posts') }}</h2>
                    <p>{{ __('blog.no_posts_desc') }}</p>
                </div>
            @else
                <div id="blog-grid" class="fly-cards-grid">
                    @include('blog.partials.post-cards', ['posts' => $gridPosts])
                </div>

                <div id="blog-load-more" class="fly-load-more {{ $nextCursor ? '' : 'hidden' }}" data-next-cursor="{{ $nextCursor ?? '' }}">
                    <div class="fly-load-spinner" aria-hidden="true"></div>
                    <p>{{ __('blog.loading_more') }}</p>
                </div>
            @endif
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
                        <li><a href="/#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a></li>
                        <li><a href="/#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a></li>
                        <li><a href="/#about" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.about') }}</a></li>
                        <li><a href="/#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a></li>
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

    <x-timezone-detector-script />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const sortSelect = document.getElementById('sort');
            const categorySelect = document.getElementById('category');
            const clearButton = document.getElementById('clearSearch');
            const searchLoading = document.getElementById('searchLoading');
            const blogGrid = document.getElementById('blog-grid');
            const heroSection = document.querySelector('.fly-hero-section');
            const cardsSection = document.getElementById('blog-cards-section');
            const loadTrigger = document.getElementById('blog-load-more');
            
            let searchTimeout = null;
            let currentSearch = searchInput?.value || '';
            let currentSort = sortSelect?.value || 'latest';
            let currentCategory = categorySelect?.value || '';

            // Real-time search
            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    
                    if (searchLoading) {
                        searchLoading.style.display = 'flex';
                    }
                    
                    searchTimeout = setTimeout(() => {
                        currentSearch = e.target.value;
                        performSearch();
                    }, 500); // Debounce 500ms
                });
            }

            // Sort change
            if (sortSelect) {
                sortSelect.addEventListener('change', (e) => {
                    currentSort = e.target.value;
                    performSearch();
                });
            }

            if (categorySelect) {
                categorySelect.addEventListener('change', (e) => {
                    currentCategory = e.target.value;
                    performSearch();
                });
            }

            // Clear search
            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    searchInput.value = '';
                    currentSearch = '';
                    performSearch();
                });
            }

            function performSearch() {
                const params = new URLSearchParams();
                if (currentSearch) params.set('search', currentSearch);
                if (currentSort) params.set('sort', currentSort);
                if (currentCategory) params.set('category', currentCategory);
                
                const url = `{{ route('blog.index') }}${params.toString() ? '?' + params.toString() : ''}`;
                
                // Update URL without reload
                window.history.pushState({}, '', url);
                
                // Fetch new results
                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'text/html',
                    },
                })
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update hero section
                    const newHero = doc.querySelector('.fly-hero-section');
                    if (heroSection && newHero) {
                        heroSection.className = newHero.className;
                        heroSection.innerHTML = newHero.innerHTML;
                    }

                    // Handle empty state when no matching results
                    const newEmptyState = doc.querySelector('.fly-empty-state');
                    const existingEmptyState = cardsSection?.querySelector('.fly-empty-state');
                    if (cardsSection && newEmptyState) {
                        if (blogGrid) {
                            blogGrid.innerHTML = '';
                        }

                        if (loadTrigger) {
                            loadTrigger.classList.add('hidden');
                        }

                        if (!existingEmptyState) {
                            cardsSection.insertAdjacentHTML('afterbegin', newEmptyState.outerHTML);
                        }
                    } else if (existingEmptyState) {
                        existingEmptyState.remove();
                    }
                    
                    // Update grid
                    const newGrid = doc.querySelector('#blog-grid');
                    if (blogGrid && newGrid) {
                        blogGrid.innerHTML = newGrid.innerHTML;
                    }
                    
                    // Update load more
                    const newLoadMore = doc.querySelector('#blog-load-more');
                    if (loadTrigger && newLoadMore) {
                        loadTrigger.dataset.nextCursor = newLoadMore.dataset.nextCursor || '';
                        if (newLoadMore.classList.contains('hidden')) {
                            loadTrigger.classList.add('hidden');
                        } else {
                            loadTrigger.classList.remove('hidden');
                        }
                    }
                    
                    // Show/hide clear button
                    if (clearButton) {
                        clearButton.style.display = currentSearch ? 'flex' : 'none';
                    }
                    
                    if (searchLoading) {
                        searchLoading.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    if (searchLoading) {
                        searchLoading.style.display = 'none';
                    }
                });
            }

            // Load more functionality
            const grid = document.getElementById('blog-grid');

            if (!loadTrigger || !grid || !loadTrigger.dataset.nextCursor) {
                return;
            }

            let isLoading = false;

            const loadMorePosts = async () => {
                if (isLoading) {
                    return;
                }

                const cursor = loadTrigger.dataset.nextCursor;
                if (!cursor) {
                    loadTrigger.classList.add('hidden');
                    return;
                }

                isLoading = true;

                try {
                    const searchParam = currentSearch || '';
                    const sortParam = currentSort || 'latest';
                    const categoryParam = currentCategory || '';
                    const url = `{{ route('blog.load-more') }}?cursor=${encodeURIComponent(cursor)}&search=${encodeURIComponent(searchParam)}&sort=${encodeURIComponent(sortParam)}&category=${encodeURIComponent(categoryParam)}`;
                    
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load more blog posts');
                    }

                    const payload = await response.json();

                    if (payload.html) {
                        grid.insertAdjacentHTML('beforeend', payload.html);
                    }

                    loadTrigger.dataset.nextCursor = payload.next_cursor ?? '';

                    if (!payload.has_more || !payload.next_cursor) {
                        loadTrigger.classList.add('hidden');
                    }
                } catch (error) {
                    loadTrigger.classList.add('has-error');
                    loadTrigger.querySelector('p').textContent = 'Failed to load more articles. Scroll again to retry.';
                } finally {
                    isLoading = false;
                }
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        loadMorePosts();
                    }
                });
            }, {
                rootMargin: '260px 0px',
            });

            observer.observe(loadTrigger);
        });
    </script>
</body>
</html>
