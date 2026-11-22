@foreach ($posts as $post)
    <article class="fly-post-card">
        <img
            src="{{ $post->featured_image_url ?: 'https://fly.io/blog/litestream-writable-vfs/assets/litestream-writable-vfs.jpg' }}"
            alt="{{ $post->title }}"
            class="fly-card-image"
            loading="lazy"
        >

        <span class="fly-meta-row card-meta">
            <span class="truncate">{{ __('blog.by_author') }} {{ strtoupper($post->author?->name ?? 'QASHIERWISE') }}</span>
            <span class="fly-line"></span>
            <span>{{ $post->published_at?->format('d M Y') ?? now()->format('d M Y') }}</span>
        </span>

        <h2>{{ $post->title }}</h2>
        <p class="fly-card-excerpt">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 200) }}</p>

        <p class="fly-readmore violet">{{ __('blog.read_more') }} <span aria-hidden="true">→</span></p>
        <a href="{{ route('blog.show', $post->slug) }}" class="fly-overlay-link">
            <span class="sr-only">{{ __('blog.read_more') }}</span>
        </a>
    </article>
@endforeach
