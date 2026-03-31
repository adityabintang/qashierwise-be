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

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900">
    <nav class="sticky top-0 z-50 border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <a href="/" class="flex items-center gap-2">
                <img src="{{ asset('images/logo-48.png') }}" class="h-8 w-8 rounded-xl" alt="Logo">
                <span class="text-lg font-bold text-primary">QashierWise</span>
            </a>
            <div class="flex items-center gap-6 text-sm font-medium">
                <a href="/" class="text-slate-600 transition hover:text-primary">Beranda</a>
                <a href="{{ route('blog.index') }}" class="text-primary">Blog</a>
                <a href="/login" class="rounded-lg bg-primary px-4 py-2 text-white transition hover:bg-primary/90">Masuk</a>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
        <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="h-64 w-full object-cover sm:h-80">

            <div class="p-6 sm:p-10">
                <div class="mb-5 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                    <span>{{ \App\Helpers\TimezoneDisplayHelper::formatWithLabel($post->published_at) }}</span>
                    @if ($post->category)
                        <span class="rounded-full bg-primary/10 px-2.5 py-1 font-medium text-primary">{{ $post->category->name }}</span>
                    @endif
                    <span>Oleh {{ $post->author?->name ?? 'Admin' }}</span>
                </div>

                <h1 class="text-3xl font-black leading-tight text-slate-900 sm:text-4xl">{{ $post->title }}</h1>

                @if ($post->excerpt)
                    <p class="mt-4 border-l-4 border-primary/40 pl-4 text-lg text-slate-600">{{ $post->excerpt }}</p>
                @endif

                <div class="mt-8 space-y-4 leading-8 text-slate-700">
                    {!! $post->content !!}
                </div>

                @if ($post->tags->isNotEmpty())
                    <div class="mt-10 flex flex-wrap gap-2 border-t border-slate-100 pt-6">
                        @foreach ($post->tags as $tag)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">#{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </article>

        @if ($relatedPosts->isNotEmpty())
            <section class="mt-10">
                <h2 class="mb-4 text-2xl font-bold text-slate-900">Artikel lain untuk Anda</h2>
                <div class="grid gap-4 md:grid-cols-3">
                    @foreach ($relatedPosts as $relatedPost)
                        <a href="{{ route('blog.show', $relatedPost->slug) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-primary/30 hover:shadow-md">
                            <h3 class="text-base font-semibold text-slate-900">{{ $relatedPost->title }}</h3>
                            @if ($relatedPost->excerpt)
                                <p class="mt-2 line-clamp-2 text-sm text-slate-600">{{ $relatedPost->excerpt }}</p>
                            @endif
                            <p class="mt-3 text-xs text-slate-500">{{ \App\Helpers\TimezoneDisplayHelper::formatWithLabel($relatedPost->published_at) }}</p>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </main>

    <x-timezone-detector-script />
</body>
</html>
