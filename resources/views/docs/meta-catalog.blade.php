<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Panduan tipe katalog Meta yang kompatibel dengan WhatsApp Business: kapan pakai vertical commerce, kapan hindari, dan bagaimana cara membuat katalog yang langsung bisa dipakai untuk multi-product message di QashierWise.">
    <title>Dokumentasi Katalog Meta — QashierWise</title>

    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo-180.png') }}">
    <meta name="theme-color" content="#4910ce">

    @vite(['resources/css/app.css', 'resources/js/static.js'])

    <style>
        [x-cloak] { display: none !important; }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.dispatchEvent(new Event('click'));
        });
    </script>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav x-data="{ menuOpen: false }" class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="/" class="flex items-center space-x-2">
                    <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="eager">
                    <span class="text-xl font-bold text-primary">{{ __('legal.company_name') }}</span>
                </a>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="/#features" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.how_it_works') }}</a>
                    <a href="/#fitur" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.features') }}</a>
                    <a href="/#pricing" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.pricing') }}</a>
                    <a href="{{ route('docs.meta-catalog') }}" class="text-primary font-medium transition">Dokumentasi</a>
                    <a href="/#faq" class="text-gray-600 hover:text-primary transition">{{ __('landing.nav.faq') }}</a>
                </div>

                <div class="hidden md:flex items-center space-x-4">
                    <x-language-switcher />
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                        @else
                            <a href="/register" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-all">{{ __('landing.nav.try_free') }}</a>
                        @endauth
                    @endif
                </div>

                <button @click="menuOpen = !menuOpen" class="md:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100" aria-label="Toggle menu">
                    <i x-show="!menuOpen" class="fas fa-bars text-xl"></i>
                    <i x-show="menuOpen" x-cloak class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <div x-show="menuOpen" x-cloak @click.away="menuOpen = false" class="md:hidden bg-white border-t border-gray-100 shadow-lg">
            <div class="px-4 py-4 space-y-3">
                <a href="/#features" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.how_it_works') }}</a>
                <a href="/#fitur" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.features') }}</a>
                <a href="/#pricing" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.pricing') }}</a>
                <a href="{{ route('docs.meta-catalog') }}" @click="menuOpen = false" class="block py-3 px-4 text-primary font-medium bg-primary/5 rounded-lg">Dokumentasi</a>
                <a href="/#faq" @click="menuOpen = false" class="block py-3 px-4 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg transition">{{ __('landing.nav.faq') }}</a>
                @if (Route::has('login'))
                    @auth
                        <a href="{{ url('/dashboard') }}" @click="menuOpen = false" class="block py-3 px-4 text-center text-gray-600 hover:text-primary font-medium transition">{{ __('landing.nav.dashboard') }}</a>
                    @else
                        <a href="/register" @click="menuOpen = false" class="block py-3 px-4 text-center bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-all">{{ __('landing.nav.try_free') }}</a>
                    @endauth
                @endif
            </div>
        </div>
    </nav>

    <main class="pt-24 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <a href="/" class="inline-flex items-center text-gray-600 hover:text-primary mb-8 transition">
                <i class="fas fa-arrow-left mr-2"></i> Kembali ke beranda
            </a>

            <header class="mb-10">
                <p class="text-xs font-semibold uppercase tracking-wider text-primary mb-2">Dokumentasi</p>
                <h1 class="text-3xl md:text-4xl font-bold text-gray-900 mb-3">Tipe Katalog Meta yang Kompatibel</h1>
                <p class="text-lg text-gray-600 leading-relaxed">
                    Halaman <strong>Meta Katalog</strong> di dashboard QashierWise hanya menampilkan katalog bertipe
                    <code class="px-1.5 py-0.5 bg-gray-100 rounded text-sm font-mono">commerce</code> — karena hanya tipe inilah yang
                    kompatibel dengan fitur produk WhatsApp Business: <em>cart</em>, multi-product message, dan order
                    flow QashierWise.
                </p>
            </header>

            <div class="prose prose-gray max-w-none">
                <section class="bg-primary/5 border border-primary/20 rounded-2xl p-6 mb-10">
                    <h2 class="text-xl font-bold text-gray-900 mt-0 mb-2">
                        <i class="fas fa-circle-info text-primary mr-2"></i>
                        Mengapa katalog saya tidak muncul di dashboard?
                    </h2>
                    <p class="text-gray-700 leading-relaxed mb-0">
                        Meta menyediakan banyak tipe katalog (vertical), tapi hanya beberapa yang bisa dipakai oleh WhatsApp
                        Business untuk berinteraksi dengan customer via chat. Jika katalog Anda dibuat dengan tipe yang
                        salah, dia akan ada di Commerce Manager tapi tidak muncul di QashierWise — dan tidak bisa dipakai
                        untuk pesan multi-produk.
                    </p>
                </section>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-10">
                    <!-- Compatible vertical types -->
                    <div class="rounded-2xl border-2 border-emerald-200 bg-emerald-50/60 p-6">
                        <div class="flex items-center gap-2 font-semibold text-emerald-700 mb-3">
                            <i class="fas fa-circle-check text-xl"></i>
                            <h3 class="text-lg font-bold text-emerald-800 m-0">Pilih tipe ini saat buat katalog di Meta</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Produk fisik → Produk online</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5">Disarankan untuk F&B / retail.</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Produk atau layanan lokal</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5">Restoran, warung dengan delivery.</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Layanan → Jasa profesional</strong>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-check text-emerald-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Produk atau konten digital → Aplikasi / Artikel</strong>
                                </span>
                            </li>
                        </ul>
                    </div>

                    <!-- Incompatible vertical types -->
                    <div class="rounded-2xl border-2 border-red-200 bg-red-50/60 p-6">
                        <div class="flex items-center gap-2 font-semibold text-red-700 mb-3">
                            <i class="fas fa-circle-xmark text-xl"></i>
                            <h3 class="text-lg font-bold text-red-800 m-0">Hindari — tidak akan muncul di QashierWise</h3>
                        </div>
                        <ul class="space-y-3 text-sm text-gray-700">
                            <li class="flex items-start gap-2">
                                <i class="fas fa-xmark text-red-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Real estate</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5 font-mono">home_listings</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-xmark text-red-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Kendaraan / Kendaraan dan promo</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5 font-mono">vehicles</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-xmark text-red-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Hotel dan penyewaan</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5 font-mono">hotels</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-xmark text-red-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Penerbangan / Tujuan</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5 font-mono">flights, destinations</span>
                                </span>
                            </li>
                            <li class="flex items-start gap-2">
                                <i class="fas fa-xmark text-red-500 mt-1 flex-shrink-0"></i>
                                <span>
                                    <strong>Media streaming</strong>
                                    <span class="block text-xs text-gray-500 mt-0.5 font-mono">media_title</span>
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>

                <section class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-10">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-lightbulb text-amber-500 text-xl mt-1"></i>
                        <div>
                            <h3 class="font-bold text-amber-900 mt-0 mb-2">Tip penting</h3>
                            <p class="text-amber-900/85 mb-0 leading-relaxed">
                                Tipe katalog <strong>tidak bisa diubah setelah dibuat</strong>. Untuk menghindari kesalahan,
                                disarankan membuat katalog langsung dari tombol <span class="font-semibold">"Buat Katalog Baru"</span>
                                di halaman <a href="/dashboard/meta-catalog" class="text-primary font-medium hover:underline">Meta Katalog</a>
                                di dashboard QashierWise — tipe <span class="font-mono bg-amber-100 px-1.5 py-0.5 rounded">commerce</span>
                                akan otomatis diterapkan.
                            </p>
                        </div>
                    </div>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Cara verifikasi tipe katalog yang sudah ada</h2>
                    <ol class="list-decimal pl-5 space-y-3 text-gray-700 leading-relaxed">
                        <li>Buka <a href="https://business.facebook.com/commerce" target="_blank" rel="noopener" class="text-primary hover:underline">Commerce Manager Meta</a> dan masuk ke business Anda.</li>
                        <li>Pilih katalog yang ingin dicek di sidebar kiri.</li>
                        <li>Di header katalog, perhatikan label tipe — kalau bukan <span class="font-mono bg-gray-100 px-1.5 py-0.5 rounded">commerce</span>, katalog ini tidak akan terhubung ke WhatsApp Business.</li>
                        <li>Untuk migrasi, <strong>buat katalog baru dengan tipe commerce</strong> via QashierWise, lalu pakai fitur <em>Sync Meta Katalog</em> dari halaman <a href="/dashboard/pos/products" class="text-primary hover:underline">POS Produk</a> untuk memindahkan produknya.</li>
                    </ol>
                </section>

                <section class="mb-10">
                    <h2 class="text-2xl font-bold text-gray-900 mb-4">Fitur yang bersifat khusus untuk katalog commerce</h2>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-gray-200 p-5 bg-white">
                            <i class="fab fa-whatsapp text-2xl text-emerald-500 mb-3"></i>
                            <h4 class="font-semibold text-gray-900 mb-1">Multi-Product Message</h4>
                            <p class="text-sm text-gray-600">Kirim banyak produk sekaligus ke customer dalam satu pesan dengan UI native WhatsApp.</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-5 bg-white">
                            <i class="fas fa-cart-shopping text-2xl text-primary mb-3"></i>
                            <h4 class="font-semibold text-gray-900 mb-1">Cart & Checkout</h4>
                            <p class="text-sm text-gray-600">Customer bisa tambah produk ke keranjang langsung dari pesan, lalu checkout via chat.</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-5 bg-white">
                            <i class="fas fa-robot text-2xl text-indigo-500 mb-3"></i>
                            <h4 class="font-semibold text-gray-900 mb-1">AI Agent Order Flow</h4>
                            <p class="text-sm text-gray-600">AI agent QashierWise otomatis kirim katalog saat customer minta menu, dan handle order end-to-end.</p>
                        </div>
                    </div>
                </section>

                <section class="bg-gray-50 rounded-2xl p-6">
                    <h3 class="font-bold text-gray-900 mb-2">Masih kebingungan?</h3>
                    <p class="text-gray-700 mb-4">
                        Tim QashierWise siap bantu setup katalog yang benar untuk bisnis Anda.
                    </p>
                    <a href="/register" class="inline-flex items-center bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition">
                        Coba QashierWise gratis
                        <i class="fas fa-arrow-right ml-2"></i>
                    </a>
                </section>
            </div>
        </div>
    </main>
</body>
</html>
