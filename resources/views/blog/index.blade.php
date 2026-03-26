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

    <header class="relative overflow-hidden bg-white">
        <div class="pointer-events-none absolute -top-24 right-0 h-64 w-64 rounded-full bg-primary/10 blur-3xl"></div>
        <div class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <p class="mb-2 text-sm font-semibold uppercase tracking-[0.2em] text-primary">QashierWise Journal</p>
            <h1 class="max-w-3xl text-4xl font-black leading-tight text-slate-900 sm:text-5xl">Insight, strategi, dan update terbaru seputar bisnis Anda.</h1>
            <p class="mt-5 max-w-2xl text-base text-slate-600 sm:text-lg">Kumpulan artikel dari tim kami untuk membantu merchant berkembang lebih cepat.</p>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
        @if ($posts->isEmpty())
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                <h2 class="text-xl font-semibold text-slate-900">Belum ada artikel</h2>
                <p class="mt-3 text-slate-600">Artikel akan muncul setelah dipublikasikan dari panel admin.</p>
            </div>
        @else
            <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($posts as $post)
                    <article class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl">
                        @if ($post->featured_image)
                            <a href="{{ route('blog.show', $post->slug) }}" class="block">
                                <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="h-52 w-full object-cover">
                            </a>
                        @endif

                        <div class="p-6">
                            <div class="mb-3 flex flex-wrap items-start gap-2 text-xs text-slate-500">
                                <span>{{ \App\Helpers\TimezoneDisplayHelper::formatWithLabel($post->published_at) }}</span>
                                @if ($post->category)
                                    <span class="rounded-full bg-primary/10 px-2.5 py-1 font-medium text-primary">{{ $post->category->name }}</span>
                                @endif
                            </div>

                            <h2 class="text-xl font-bold leading-tight text-slate-900">
                                <a href="{{ route('blog.show', $post->slug) }}" class="transition group-hover:text-primary">{{ $post->title }}</a>
                            </h2>

                            @if ($post->excerpt)
                                <p class="mt-3 line-clamp-3 text-sm text-slate-600">{{ $post->excerpt }}</p>
                            @endif

                            <div class="mt-6 flex items-center justify-between border-t border-slate-100 pt-4">
                                <p class="text-sm text-slate-500">{{ $post->author?->name ?? 'Admin' }}</p>
                                <a href="{{ route('blog.show', $post->slug) }}" class="text-sm font-semibold text-primary transition hover:text-primary/80">Baca selengkapnya</a>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @endif
    </main>

    <x-timezone-detector-script />
</body>
</html>
