<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @if(config('app.env') === 'local')
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    @endif
    <title>Blog | {{ config('app.name') }}</title>
    <meta name="description" content="Baca artikel terbaru dari {{ config('app.name') }}.">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="fly-blog-page">
    <header class="fly-blog-navbar-wrap">
        <div class="fly-blog-navbar container mx-auto px-4 sm:px-6 lg:px-8">
            <a href="{{ route('blog.index') }}" class="fly-blog-logo" aria-label="Blog home">
                <span class="fly-logo-badge">F</span>
                <span class="fly-logo-word">Blog</span>
            </a>

            <nav class="fly-blog-main-nav" aria-label="Main menu">
                <a href="{{ route('blog.index') }}" class="is-active">Articles</a>
                <a href="https://fly.io/security/" target="_blank" rel="noreferrer">Security</a>
                <a href="https://fly.io/infra-log/" target="_blank" rel="noreferrer">Infra Log</a>
                <a href="https://fly.io/customer-stories/" target="_blank" rel="noreferrer">Customers</a>
                <a href="https://fly.io/docs/" target="_blank" rel="noreferrer">Docs</a>
                <a href="https://community.fly.io/" target="_blank" rel="noreferrer">Community</a>
                <a href="https://status.flyio.net/" target="_blank" rel="noreferrer">Status</a>
                <a href="https://fly.io/pricing/" target="_blank" rel="noreferrer">Pricing</a>
            </nav>

            <div class="fly-blog-actions">
                <a href="https://fly.io/app/sign-in" target="_blank" rel="noreferrer" class="fly-btn ghost">Sign In</a>
                <a href="https://fly.io/docs/hands-on/start/" target="_blank" rel="noreferrer" class="fly-btn primary">Get Started</a>
            </div>
        </div>
    </header>

    <main>
        <section class="fly-hero-section">
            <img
                src="https://fly.io/static/images/blog-cover.webp"
                srcset="https://fly.io/static/images/blog-cover@2x.webp 2x"
                alt=""
                class="fly-hero-bg"
            >

            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
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
                                <span>{{ $featuredReadTime }} min Read</span>
                            </span>

                            <h1>{{ $featuredPost->title }}</h1>

                            <p class="fly-hero-excerpt">{{ $featuredPost->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($featuredPost->content), 220) }}</p>

                            <p class="fly-readmore">Read more <span aria-hidden="true">→</span></p>
                            <a href="{{ route('blog.show', $featuredPost->slug) }}" class="fly-overlay-link">
                                <span class="sr-only">Read more</span>
                            </a>
                        </div>

                        <img src="{{ $featuredImage }}" alt="{{ $featuredPost->title }}" class="fly-hero-image">
                    </article>
                @endif
            </div>
        </section>

        <section class="container mx-auto px-4 sm:px-6 lg:px-8">
            @if (! $featuredPost)
                <div class="fly-empty-state">
                    <h2>No published articles yet</h2>
                    <p>Articles will show up here once they are published.</p>
                </div>
            @else
                <div id="blog-grid" class="fly-cards-grid">
                    @include('blog.partials.post-cards', ['posts' => $gridPosts])
                </div>

                <div id="blog-load-more" class="fly-load-more {{ $nextCursor ? '' : 'hidden' }}" data-next-cursor="{{ $nextCursor ?? '' }}">
                    <div class="fly-load-spinner" aria-hidden="true"></div>
                    <p>Loading more articles...</p>
                </div>
            @endif
        </section>
    </main>

    <x-timezone-detector-script />

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const loadTrigger = document.getElementById('blog-load-more');
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
                    const response = await fetch(`{{ route('blog.load-more') }}?cursor=${encodeURIComponent(cursor)}`, {
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
