@foreach ($posts as $post)
    @php
        $readTime = max(1, (int) ceil(str_word_count(strip_tags($post->content ?? $post->excerpt ?? '')) / 200));
    @endphp

    <article class="fly-post-card">
        <img
            src="{{ $post->featured_image_url ?: 'https://fly.io/blog/litestream-writable-vfs/assets/litestream-writable-vfs.jpg' }}"
            alt="{{ $post->title }}"
            class="fly-card-image"
            loading="lazy"
        >

        <span class="fly-meta-row card-meta">
            <span class="truncate">By {{ strtoupper($post->author?->name ?? 'QASHIERWISE') }}</span>
            <span class="fly-line"></span>
            <span>{{ $readTime }} min Read</span>
        </span>

        <h2>{{ $post->title }}</h2>
        <p class="fly-card-excerpt">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 200) }}</p>

        <p class="fly-readmore violet">Read more <span aria-hidden="true">→</span></p>
        <a href="{{ route('blog.show', $post->slug) }}" class="fly-overlay-link">
            <span class="sr-only">Read more</span>
        </a>
    </article>
@endforeach
