<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QashierWise - AI Chatbot WhatsApp untuk Restoran</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4910ce',
                    }
                }
            }
        }
    </script>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
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
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo.png') }}" class="h-7 rounded-xl" alt="Logo">
                        <span class="text-xl font-bold text-primary">QashierWise</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#features" class="text-gray-600 hover:text-primary transition">Cara Kerja</a>
                    <a href="#fitur" class="text-gray-600 hover:text-primary transition">Fitur</a>
                    <a href="#pricing" class="text-gray-600 hover:text-primary transition">Harga</a>
                    <a href="#about" class="text-gray-600 hover:text-primary transition">Tentang Kami</a>
                    <a href="#faq" class="text-gray-600 hover:text-primary transition">FAQ</a>
                </div>

                <!-- CTA Buttons -->
                <div class="flex items-center space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-primary font-medium transition">Dashboard</a>
                        @else
                            <a href="/login" class="text-gray-600 hover:text-primary font-medium transition">Lihat Demo</a>
                            <a href="/login" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-all">
                                Coba Gratis 14 Hari
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-gradient pt-32 pb-20 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div>
                    <div class="inline-flex items-center px-4 py-2 bg-purple-100 rounded-full text-primary text-sm font-medium mb-6">
                        <i class="fas fa-sparkles mr-2"></i>
                        Didukung AI + Terintegrasi QRIS
                    </div>

                    <p class="text-gray-500 mb-4">Chatbot untuk WhatsApp + Sistem QRIS</p>

                    <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-gray-900 leading-tight mb-6">
                        Respon lebih cepat, jual lebih <span class="gradient-text">banyak</span>
                    </h1>

                    <p class="text-lg text-gray-600 mb-8 leading-relaxed">
                        QashierWise menghadirkan Chatbot WhatsApp yang restoran-first dengan satu layar admin: Inbox, Pesanan, Reservasi, Menu, dan CRM.
                    </p>

                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <a href="/login" class="inline-flex items-center justify-center px-8 py-4 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all">
                            <span>Coba Gratis 14 Hari</span>
                        </a>    
                        <a href="#demo" class="inline-flex items-center justify-center px-8 py-4 bg-white text-primary rounded-xl font-semibold border-2 border-primary/30 hover:border-primary transition-all">
                            <i class="fas fa-play-circle mr-2"></i>
                            <span>Lihat Demo</span>
                        </a>
                    </div>

                    <div class="flex flex-col gap-2 text-sm text-gray-500">
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Reservasi & Order via WhatsApp tanpa ribet</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Kelola pesanan + laporan dengan mudah</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Tanpa perubahan workflow (bayar di tempat)</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check text-green-500 mr-2"></i>
                            <span>Laporan Order & penjualan otomatis</span>
                        </div>
                    </div>
                </div>

                <!-- Right Content - Hero Image -->
                <div class="relative">
                    <img src="https://images.unsplash.com/photo-1556742049-0cfed4f6a45d?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Restaurant ordering" class="rounded-2xl shadow-2xl">
                    <!-- Floating Elements -->
                    <div class="absolute -bottom-6 -left-6 bg-white rounded-xl p-4 shadow-xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <i class="fab fa-whatsapp text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Pesan Masuk</p>
                                <p class="text-xs text-gray-500">+128 hari ini</p>
                            </div>
                        </div>
                    </div>
                    <div class="absolute -top-4 -right-4 bg-white rounded-xl p-4 shadow-xl">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-purple-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-chart-line text-primary"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-900">Penjualan</p>
                                <p class="text-xs text-green-500">↑ 24% bulan ini</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Cara kerja otomatis dengan AI + QRIS</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Semuanya untuk bisnis restoran: cepat, ringkas, dan akurat.</p>
            </div>

            <div class="grid md:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-green-400 to-green-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-green-500/30">
                        <i class="fab fa-whatsapp text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Terima chat di WhatsApp</h3>
                    <p class="text-sm text-gray-500">Balas manual atau otomatis, sesuaikan auto-reply, jam operasional, dan menu.</p>
                </div>

                <!-- Step 2 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-primary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-purple-500/30">
                        <i class="fas fa-robot text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">AI merespons otomatis</h3>
                    <p class="text-sm text-gray-500">Terkirim konfirmasi & menu, dan pelanggan terkelola dengan baik.</p>
                </div>

                <!-- Step 3 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-blue-400 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-blue-500/30">
                        <i class="fas fa-qrcode text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Bayar dengan QRIS</h3>
                    <p class="text-sm text-gray-500">Kirim link QRIS statis. Semua disimpan, status berubah otomatis.</p>
                </div>

                <!-- Step 4 -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-orange-400 to-orange-600 rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg shadow-orange-500/30">
                        <i class="fas fa-user-tie text-white text-2xl"></i>
                    </div>
                    <h3 class="font-semibold text-gray-900 mb-2">Karyawan pantau di Console</h3>
                    <p class="text-sm text-gray-500">Lihat inbox pesan pesanan, dan kelola semuanya dalam satu layar.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="fitur" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Fitur utama yang restoran butuhkan</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Semua alat & aplikasi dibuat untuk memaksimalkan pemasukan restoran</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fab fa-whatsapp text-green-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">AI Chatbot WhatsApp</h3>
                    <p class="text-gray-600">Balas cerdas dengan model AI, terima order, Talk to Staff, handover ke live agent langsung.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-desktop text-primary text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Console Satu Layar</h3>
                    <p class="text-gray-600">Inbox pesan, pesanan, kelola reservasi, dan semuanya dalam satu dashboard.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-motorcycle text-blue-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Order Delivery & Pickup</h3>
                    <p class="text-gray-600">Penjadwalan, order & delivery bisa terhubung, pembayaran QRIS terintegrasi.</p>
                </div>

                <!-- Feature 4 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-amber-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-calendar-check text-amber-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Reservasi Pintar</h3>
                    <p class="text-gray-600">Slot ketersediaan, jam buka, kapasitas, dan notifikasi staff. Pengingat T-24h & T+7hr.</p>
                </div>

                <!-- Feature 5 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-qrcode text-emerald-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Pembayaran QRIS</h3>
                    <p class="text-gray-600">Kirim invoice QRIS atau berikan QR statis via chat, semua tercatat otomatis.</p>
                </div>

                <!-- Feature 6 -->
                <div class="bg-white rounded-2xl p-8 feature-card transition-all duration-300">
                    <div class="w-12 h-12 bg-rose-100 rounded-xl flex items-center justify-center mb-4">
                        <i class="fas fa-chart-pie text-rose-600 text-xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-3">Laporan & CRM</h3>
                    <p class="text-gray-600">CRM, repeat rate, tag pelanggan, dan export CSV (Pro).</p>
                </div>
            </div>
        </div>
    </section>

    <!-- App Preview Section -->
    <section class="py-20 bg-gradient-to-b from-primary to-purple-800 overflow-hidden">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-white mb-4">Satu dashboard untuk semua</h2>
                <p class="text-purple-200 max-w-2xl mx-auto">Kelola chat, pesanan, reservasi, dan laporan dalam satu tampilan yang simpel</p>
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
                                        <span class="text-xs text-gray-400">2m ago</span>
                                    </div>
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-semibold">S</div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-800">Siti Nurhaliza</p>
                                                <p class="text-xs text-gray-500">Order delivery ke alamat...</p>
                                            </div>
                                        </div>
                                        <span class="text-xs text-gray-400">5m ago</span>
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
                    <i class="fab fa-whatsapp text-2xl text-green-500"></i>
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
    <section id="pricing" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Harga sederhana, tumbuh bersama Anda</h2>
                <p class="text-gray-600">Mulai gratis - upgrade kapan saja.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                <!-- Basic Plan -->
                <div class="bg-white rounded-2xl p-8 border border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Basic</h3>
                    <p class="text-gray-500 text-sm mb-4">1 outlet, No admin + pickup</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900">Rp0</span>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>AI chatbot dasar (% pesan/bulan)</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Reservasi & pickup orders</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Watermark menu digital</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Tanpa pembayaran online (bayar di tempat)</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>1 user staf + Email support (48hr)</li>
                    </ul>
                    <a href="/login" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                        Mulai Gratis
                    </a>
                </div>

                <!-- Standard Plan -->
                <div class="bg-white rounded-2xl p-8 border-2 border-primary relative">
                    <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-primary text-white px-4 py-1 rounded-full text-xs font-semibold">
                        POPULER
                    </div>
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
                    <a href="/login" class="block w-full text-center py-3 bg-primary text-white rounded-xl font-semibold hover:bg-primary/90 transition-all">
                        Pilih Standard
                    </a>
                </div>

                <!-- Pro Plan -->
                <div class="bg-white rounded-2xl p-8 border border-gray-200">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">Pro</h3>
                    <p class="text-gray-500 text-sm mb-4">Tim unlimited, Analytic & API</p>
                    <div class="mb-6">
                        <span class="text-3xl font-bold text-gray-900">Rp2.990.000</span>
                        <span class="text-gray-500">/bln</span>
                    </div>
                    <ul class="space-y-3 mb-8 text-sm text-gray-600">
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Analytic + ekspor CSV</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Webhook & API</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Multi-outlet & branding</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>Priority routing & handover</li>
                        <li class="flex items-center"><i class="fas fa-check text-green-500 mr-2"></i>SLA + dedicated support</li>
                    </ul>
                    <a href="/login" class="block w-full text-center py-3 bg-gray-100 text-gray-700 rounded-xl font-semibold hover:bg-gray-200 transition">
                        Pilih Pro
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Tentang Kami</h2>
                <p class="text-gray-600">Kenali lebih dekat QashierWise dan tim di belaknya</p>
            </div>

            <div class="prose prose-lg max-w-none text-gray-600 mb-12">
                <h3 class="text-xl font-semibold text-gray-900">Apa itu QashierWise?</h3>
                <p>
                    QashierWise adalah solusi modern yang menghadirkan AI Chatbot WhatsApp dengan sistem POS (Point of Sale) untuk restoran dan manajemen untuk proses reservasi dan pemesanan melalui WhatsApp. Serta menyajikan operasional bisnis dengan integrasi QRIS.
                </p>
                <p>
                    Dengan QashierWise restoran dapat mengelola operasional lewat API, mengubah pesan secara otomatis, memiliki pembayaran QRIS, dan meningkatkan bisnis yang kompetitif - semua dalam satu platform yang mudah digunakan.
                </p>
            </div>

            <div class="bg-gray-50 rounded-2xl p-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Pengelola</h3>
                <div class="flex items-center space-x-4">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-primary rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        AB
                    </div>
                    <div>
                        <h4 class="font-semibold text-gray-900">Aditya Bintang Fadila</h4>
                        <p class="text-gray-600 text-sm">QashierWise dibuat oleh Aditya Bintang Fadila, yang berkomitmen untuk menghadirkan solusi teknologi terbaik bagi industri F&B di Indonesia.</p>
                    </div>
                </div>
            </div>

            <div class="mt-12">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Visi Kami</h3>
                <p class="text-gray-600">
                    Menjadi platform terdepan dalam transformasi digital restoran di Indonesia, membantu bisnis F&B berkembang dengan teknologi yang mudah, modern dan terjangkau.
                </p>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section id="faq" class="py-20 bg-gray-50">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4">Pertanyaan yang sering diajukan</h2>
                <p class="text-gray-600">Semua dalam Bahasa Indonesia.</p>
            </div>

            <div class="space-y-4" x-data="{ open: null }">
                <!-- FAQ Item 1 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 1 ? null : 1" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">Apa itu QashierWise?</span>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform" :class="{ 'rotate-180': open === 1 }"></i>
                    </button>
                    <div x-show="open === 1" x-transition class="px-6 pb-4 text-gray-600">
                        QashierWise adalah platform AI Chatbot WhatsApp yang dirancang khusus untuk restoran, membantu mengelola reservasi, pesanan, dan pembayaran QRIS dalam satu dashboard.
                    </div>
                </div>

                <!-- FAQ Item 2 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 2 ? null : 2" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">Apakah perlu aplikasi terpisah untuk pelanggan?</span>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform" :class="{ 'rotate-180': open === 2 }"></i>
                    </button>
                    <div x-show="open === 2" x-transition class="px-6 pb-4 text-gray-600">
                        Tidak! Pelanggan cukup menggunakan WhatsApp yang sudah mereka miliki. Tidak perlu download aplikasi tambahan.
                    </div>
                </div>

                <!-- FAQ Item 3 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 3 ? null : 3" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">Bagaimana pembayaran dilakukan?</span>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform" :class="{ 'rotate-180': open === 3 }"></i>
                    </button>
                    <div x-show="open === 3" x-transition class="px-6 pb-4 text-gray-600">
                        Pembayaran bisa dilakukan melalui QRIS yang terintegrasi dengan Midtrans, Xendit, atau penyedia QRIS lainnya. Pelanggan juga bisa bayar di tempat.
                    </div>
                </div>

                <!-- FAQ Item 4 -->
                <div class="bg-white rounded-xl border border-gray-200">
                    <button @click="open = open === 4 ? null : 4" class="w-full px-6 py-4 text-left flex items-center justify-between">
                        <span class="font-medium text-gray-900">Apakah bisa multi-outlet dan multi nomor?</span>
                        <i class="fas fa-chevron-down text-gray-400 transform transition-transform" :class="{ 'rotate-180': open === 4 }"></i>
                    </button>
                    <div x-show="open === 4" x-transition class="px-6 pb-4 text-gray-600">
                        Ya! Paket Standard mendukung hingga 2 outlet, dan paket Pro mendukung unlimited outlet dengan fitur multi-branding.
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8 mb-12">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo.png') }}" class="h-7 rounded-xl" alt="Logo">
                        <span class="text-xl font-bold text-primary">QashierWise</span>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        Coba gratis 14 hari, QashierWise membantu restoran menerima reservasi & order via WhatsApp dengan cepat.
                    </p>
                    <div class="text-sm text-gray-600">
                        <p class="font-semibold text-gray-900 mb-1">Alamat</p>
                        <p>Jl. Widosari No. 55, Tegalrejo Raya<br>Salatiga, Jawa Tengah, Indonesia<br>50733</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h4 class="text-gray-900 font-semibold mb-4">Navigasi</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#fitur" class="text-gray-600 hover:text-primary transition">Fitur</a></li>
                        <li><a href="#pricing" class="text-gray-600 hover:text-primary transition">Harga</a></li>
                        <li><a href="#about" class="text-gray-600 hover:text-primary transition">Tentang Kami</a></li>
                        <li><a href="#faq" class="text-gray-600 hover:text-primary transition">FAQ</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h4 class="text-gray-900 font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" class="text-gray-600 hover:text-primary transition">Kebijakan Privasi</a></li>
                        <li><a href="/terms-of-service" class="text-gray-600 hover:text-primary transition">Ketentuan Layanan</a></li>
                    </ul>
                </div>

                <!-- Product -->
                <div>
                    <h4 class="text-gray-900 font-semibold mb-4">Produk</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">QashierWise Console</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">Chatbot WhatsApp</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-8 text-center text-sm text-gray-600">
                <p>&copy; 2025 QashierWise by Aditya Bintang Fadila. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
