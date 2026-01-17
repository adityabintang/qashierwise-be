<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ketentuan Layanan - QashierWise</title>

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

    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-white">
    <!-- Navigation -->
    <nav class="fixed top-0 left-0 right-0 z-50 bg-white/80 backdrop-blur-md border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <!-- Logo -->
                <div class="flex items-center space-x-2 mb-4">
                        <img src="{{ asset('images/logo-48.png') }}" class="h-7 rounded-xl" alt="Logo" width="28" height="28" loading="eager">
                        <span class="text-xl font-bold text-primary">QashierWise</span>
                </div>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="/#features" class="text-gray-600 hover:text-primary transition">Cara Kerja</a>
                    <a href="/#fitur" class="text-gray-600 hover:text-primary transition">Fitur</a>
                    <a href="/#pricing" class="text-gray-600 hover:text-primary transition">Harga</a>
                    <a href="/#about" class="text-gray-600 hover:text-primary transition">Tentang Kami</a>
                    <a href="/#faq" class="text-gray-600 hover:text-primary transition">FAQ</a>
                </div>

                <!-- CTA Buttons -->
                <div class="flex items-center space-x-4">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="text-gray-600 hover:text-primary font-medium transition">Dashboard</a>
                        @else
                            <a href="https://youtu.be/knoL8c0CJs8?si=N8RBGATUSK940ZF4" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-primary font-medium transition">Lihat Demo</a>
                            <a href="/register" class="bg-primary text-white px-5 py-2.5 rounded-lg font-medium hover:bg-primary/90 transition-all">
                                Coba Gratis 14 Hari
                            </a>
                        @endauth
                    @endif
                </div>
            </div>
        </div>
    </nav>

    <!-- Content -->
    <main class="pt-24 pb-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Back Link -->
            <a href="/" class="inline-flex items-center text-gray-600 hover:text-primary mb-8 transition">
                <i class="fas fa-arrow-left mr-2"></i>
                Kembali ke Beranda
            </a>

            <!-- Title -->
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Ketentuan Layanan</h1>
            <p class="text-gray-500 mb-8">Terakhir diperbarui: 10 Februari 2025</p>

            <!-- Intro -->
            <div class="prose prose-lg max-w-none">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">Syarat dan Ketentuan Penggunaan Produk QashierWise</h2>
                <p class="text-gray-600 mb-6">
                    Terima kasih atas kepercayaan Anda menggunakan produk QashierWise. Dengan menggunakan layanan dan/atau produk yang disediakan oleh "QashierWise", Anda, perusahaan dan/atau bisnis yang telah memberikan izin atau otorisasi untuk mewakili Anda ("Pengguna") setuju dengan Syarat dan Ketentuan Penggunaan Produk QashierWise berikut ini serta syarat, kebijakan dan dokumentasi terkait lainnya yang disediakan oleh QashierWise dari waktu ke waktu ("Syarat dan Ketentuan").
                </p>
                <p class="text-gray-600 mb-8">
                    QashierWise dapat meninjau dan mengubah Syarat dan Ketentuan ini dari waktu ke waktu atas kebijakan QashierWise sendiri. Pengguna mengakui dan menyetujui bahwa Pengguna wajib memantau Syarat dan Ketentuan ini dari waktu ke waktu untuk mengetahui kondisi atau informasi terbaru mengenai ketentuan penggunaan Produk yang disediakan oleh QashierWise.
                </p>

                <!-- Section I -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">I. Ketentuan Umum</h2>
                <p class="text-gray-600 mb-4">Ketentuan Umum ini berlaku untuk semua Pengguna yang menggunakan Produk (sebagaimana didefinisikan di bawah) yang disediakan oleh QashierWise.</p>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">A. Definisi Umum</h3>
                <ul class="list-disc pl-6 text-gray-600 space-y-3 mb-6">
                    <li><strong>BAST</strong> berarti Berita Acara Serah Terima, dokumen yang ditandatangani oleh Para Pihak sebelum Pelatihan dilakukan yang menyatakan bahwa Produk dapat digunakan oleh Pengguna.</li>
                    <li><strong>Hak Kekayaan Intelektual</strong> berarti semua paten, hak cipta, merek dagang, dan hak terkait lainnya sebagaimana didefinisikan oleh hukum yang berlaku.</li>
                    <li><strong>Informasi Rahasia</strong> berarti semua informasi terkait bisnis QashierWise yang telah atau akan diberikan kepada Pengguna termasuk namun tidak terbatas pada desain produk, informasi keuangan, dan rencana pemasaran.</li>
                    <li><strong>Layanan Percobaan</strong> berarti layanan Produk yang diberikan kepada Pengguna dengan batasan tertentu sebagaimana dimaksudkan ini.</li>
                    <li><strong>Periode Aktif</strong> berarti periode aktif langganan Produk berdasarkan paket yang dibayar oleh Pengguna.</li>
                    <li><strong>Keadaan Kahar</strong> berarti kondisi di luar kendali yang tidak terduga yang menyebabkan Para Pihak untuk memenuhi kewajiban mereka berdasarkan Syarat dan Ketentuan ini.</li>
                    <li><strong>Perjanjian Pengguna</strong> berarti perjanjian penggunaan Produk yang ditandatangani oleh Para Pihak yang merinci ketentuan penggunaan Produk.</li>
                    <li><strong>Pihak</strong> berarti QashierWise atau Pengguna.</li>
                    <li><strong>Produk</strong> berarti produk yang ditawarkan dan disediakan oleh QashierWise, termasuk namun tidak terbatas pada Sistem POS, Manajemen Inventaris, Laporan Analitik, dan Integrasi Pembayaran.</li>
                    <li><strong>Quotation</strong> berarti dokumen yang dikeluarkan oleh QashierWise kepada Pengguna, yang mengatur paket Produk yang dipilih oleh Pengguna.</li>
                </ul>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">B. Detail Paket, Biaya, dan Pembayaran</h3>
                <p class="text-gray-600 mb-6">Pengguna mengakui, memahami, dan menyetujui bahwa detail paket Produk yang dipilih oleh Pengguna adalah sebagaimana tercantum dalam Quotation dan/atau Perjanjian Pengguna.</p>

                <h3 class="text-xl font-bold text-gray-900 mt-6 mb-4">C. Representasi dan Jaminan Pengguna</h3>
                <p class="text-gray-600 mb-4">Pengguna menyatakan dan menjamin bahwa:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-3 mb-6">
                    <li>Pengguna kompeten dan berwenang untuk menyetujui Syarat dan Ketentuan ini.</li>
                    <li>Pengguna telah memperoleh semua lisensi yang diperlukan untuk kewajiban berdasarkan Syarat dan Ketentuan ini.</li>
                    <li>Tidak ada tindakan hukum yang sedang berlangsung yang dapat mempengaruhi kemampuan Pengguna untuk melakukan kewajibannya berdasarkan Syarat dan Ketentuan ini.</li>
                    <li>Pengguna mematuhi semua peraturan anti-suap dan anti-korupsi yang berlaku.</li>
                    <li>Pengguna menjamin bahwa telah memperoleh persetujuan yang sah dari pemilik data pribadi yang datanya diberikan kepada QashierWise sehubungan dengan penggunaan Produk.</li>
                    <li>Pengguna menjamin untuk selalu mematuhi syarat, ketentuan, dan kebijakan privasi yang berlaku untuk setiap Produk.</li>
                </ul>

                <!-- Section II -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">II. Ganti Rugi dan Batasan Tanggung Jawab</h2>
                <p class="text-gray-600 mb-4">Pengguna setuju untuk mengganti rugi, membela, dan membebaskan QashierWise dari segala klaim, kerugian, kerusakan, kewajiban, dan biaya yang timbul dari penggunaan Produk oleh Pengguna, termasuk namun tidak terbatas pada pelanggaran Syarat dan Ketentuan ini.</p>
                <p class="text-gray-600 mb-6">QashierWise tidak bertanggung jawab atas kerugian tidak langsung, insidental, khusus, atau konsekuensial yang timbul dari penggunaan atau ketidakmampuan menggunakan Produk.</p>

                <!-- Section III -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">III. Keamanan Data</h2>
                <p class="text-gray-600 mb-4">QashierWise menerapkan langkah-langkah keamanan standar industri untuk melindungi informasi pribadi Anda dari akses, kehilangan, penyalahgunaan, atau perubahan yang tidak sah.</p>
                <p class="text-gray-600 mb-6">Meskipun kami menjamin tindakan pencegahan yang wajar, tidak ada metode transmisi data melalui internet atau penyimpanan elektronik yang sepenuhnya aman. Oleh karena itu, kami tidak dapat menjamin keamanan mutlak.</p>

                <!-- Section IV -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">IV. Tautan Pihak Ketiga</h2>
                <p class="text-gray-600 mb-6">QashierWise mungkin berisi tautan ke situs web atau layanan pihak ketiga. Kami tidak bertanggung jawab atas praktik privasi atau konten situs web tersebut. Silakan tinjau kebijakan privasi mereka sebelum menggunakannya.</p>

                <!-- Section V -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">V. Perubahan Ketentuan Layanan</h2>
                <p class="text-gray-600 mb-6">Kami dapat memperbarui Ketentuan Layanan ini dari waktu ke waktu. Setiap perubahan akan diposting di halaman ini, dan tanggal "terakhir diperbarui" akan direvisi sesuai.</p>

                <!-- Section VI -->
                <h2 class="text-2xl font-bold text-gray-900 mt-10 mb-4">VI. Hubungi Kami</h2>
                <p class="text-gray-600 mb-4">Jika Anda memiliki pertanyaan, kekhawatiran, atau permintaan terkait Ketentuan Layanan ini, silakan hubungi kami di:</p>
                <div class="text-gray-600 mb-8">
                    <p><strong>Email:</strong> support@qashierwise.com</p>
                    <p><strong>Admin:</strong> admin@qashierwise.com</p>
                </div>
            </div>
        </div>
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
                        Coba gratis 14 hari, QashierWise membantu restoran menerima reservasi & order via WhatsApp dengan cepat.
                    </p>
                    <div class="text-sm text-gray-600">
                        <p class="font-semibold text-gray-900 mb-1">Alamat</p>
                        <p>Jl. Widosari No. 55, Tegalrejo Raya<br>Salatiga, Jawa Tengah, Indonesia<br>50733</p>
                    </div>
                </div>

                <!-- Navigation -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">Navigasi</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#fitur" class="text-gray-600 hover:text-primary transition">Fitur</a></li>
                        <li><a href="/#pricing" class="text-gray-600 hover:text-primary transition">Harga</a></li>
                        <li><a href="/#about" class="text-gray-600 hover:text-primary transition">Tentang Kami</a></li>
                        <li><a href="/#faq" class="text-gray-600 hover:text-primary transition">FAQ</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" class="text-gray-600 hover:text-primary transition">Kebijakan Privasi</a></li>
                        <li><a href="/terms-of-service" class="text-gray-600 hover:text-primary transition">Ketentuan Layanan</a></li>
                    </ul>
                </div>

                <!-- Product -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">Produk</h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">QashierWise Console</a></li>
                        <li><a href="#" class="text-gray-600 hover:text-primary transition">Chatbot WhatsApp</a></li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-gray-200 pt-8 text-center text-sm text-gray-600">
                <p>&copy; 2025 QashierWise by AdityaBintang. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
