<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Privasi - QashierWise</title>

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
                            <a href="/login" class="text-gray-600 hover:text-primary font-medium transition">Lihat Demo</a>
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
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Kebijakan Privasi</h1>
            <p class="text-gray-500 mb-12">Terakhir diperbarui: 27 November 2025</p>

            <!-- Content -->
            <div class="prose prose-lg max-w-none">
                <!-- Section 1 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">1. Informasi yang Kami Kumpulkan</h2>
                <p class="text-gray-600 mb-4">QashierWise mengumpulkan informasi yang Anda berikan kepada kami ketika menggunakan layanan kami, termasuk:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Informasi bisnis restoran (nama, alamat, kontak)</li>
                    <li>Data reservasi dan pesanan pelanggan</li>
                    <li>Informasi komunikasi melalui WhatsApp Business API</li>
                    <li>Data transaksi dan pembayaran</li>
                    <li>Informasi penggunaan layanan dan statistik</li>
                </ul>

                <!-- Section 2 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">2. Penggunaan WhatsApp Business API</h2>
                <p class="text-gray-600 mb-4">QashierWise menggunakan WhatsApp Business API untuk memfasilitasi komunikasi antara restoran dan pelanggan. Kami:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Memproses pesan reservasi dan pesanan melalui WhatsApp</li>
                    <li>Menyimpan riwayat percakapan untuk keperluan operasional</li>
                    <li>Menggunakan data untuk meningkatkan layanan chatbot AI</li>
                    <li>Tidak membagikan data WhatsApp Anda kepada pihak ketiga tanpa izin</li>
                    <li>Mematuhi kebijakan privasi WhatsApp dan Meta</li>
                </ul>

                <!-- Section 3 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">3. Bagaimana Kami Menggunakan Informasi</h2>
                <p class="text-gray-600 mb-4">Informasi yang dikumpulkan digunakan untuk:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Menyediakan dan meningkatkan layanan QashierWise</li>
                    <li>Memproses reservasi dan pesanan pelanggan</li>
                    <li>Mengirim notifikasi dan konfirmasi melalui WhatsApp</li>
                    <li>Menganalisis dan meningkatkan performa sistem</li>
                    <li>Mematuhi kewajiban hukum dan peraturan yang berlaku</li>
                </ul>

                <!-- Section 4 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">4. Keamanan Data</h2>
                <p class="text-gray-600 mb-6">Kami menerapkan langkah-langkah keamanan yang sesuai untuk melindungi informasi Anda dari akses, pengungkapan, perubahan, atau penghancuran yang tidak sah. Data disimpan dengan enkripsi dan hanya dapat diakses oleh personel yang berwenang.</p>

                <!-- Section 5 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">5. Pembagian Informasi</h2>
                <p class="text-gray-600 mb-4">Kami tidak menjual, menyewakan, atau membagikan informasi pribadi Anda kepada pihak ketiga, kecuali:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Dengan persetujuan Anda</li>
                    <li>Untuk mematuhi kewajiban hukum</li>
                    <li>Dengan penyedia layanan pihak ketiga yang membantu operasional kami (seperti WhatsApp Business API, payment gateway)</li>
                    <li>Untuk melindungi hak, properti, atau keamanan QashierWise dan pengguna kami</li>
                </ul>

                <!-- Section 6 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">6. Retensi Data</h2>
                <p class="text-gray-600 mb-6">Kami menyimpan informasi Anda selama akun Anda aktif atau sepanjang diperlukan untuk menyediakan layanan. Anda dapat meminta penghapusan data dengan menghubungi kami.</p>

                <!-- Section 7 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">7. Hak Anda</h2>
                <p class="text-gray-600 mb-4">Anda memiliki hak untuk:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Mengakses dan mendapatkan salinan data pribadi Anda</li>
                    <li>Memperbaiki data yang tidak akurat</li>
                    <li>Meminta penghapusan data Anda</li>
                    <li>Membatasi atau menolak pemrosesan data tertentu</li>
                    <li>Menarik persetujuan yang telah diberikan</li>
                </ul>

                <!-- Section 8 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">8. Cookies dan Teknologi Pelacakan</h2>
                <p class="text-gray-600 mb-6">Website kami menggunakan cookies dan teknologi serupa untuk meningkatkan pengalaman pengguna, menganalisis traffic, dan personalisasi konten. Anda dapat mengatur preferensi cookies melalui browser Anda.</p>

                <!-- Section 9 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">9. Perubahan Kebijakan Privasi</h2>
                <p class="text-gray-600 mb-6">Kami dapat memperbarui kebijakan privasi ini dari waktu ke waktu. Perubahan akan diposting di halaman ini dengan tanggal "terakhir diperbarui" yang baru. Kami mendorong Anda untuk meninjau kebijakan ini secara berkala.</p>

                <!-- Section 10 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">10. Hubungi Kami</h2>
                <p class="text-gray-600 mb-6">Jika Anda memiliki pertanyaan tentang kebijakan privasi ini atau ingin menggunakan hak privasi Anda, silakan hubungi kami:</p>

                <!-- Contact Box -->
                <div class="bg-gray-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-gray-900 mb-3">QashierWise</h3>
                    <div class="text-gray-600 space-y-1">
                        <p><span class="font-medium">Email:</span> support@qashierwise.com</p>
                        <p><span class="font-medium">WhatsApp:</span> +62 882-1545-7494</p>
                        <p><span class="font-medium">Alamat:</span> Jl. Widosari No. 55, Tegalrejo Raya, Salatiga, Jawa Tengah, Indonesia 50733</p>
                    </div>
                </div>

                <!-- Section 11 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">11. Kepatuhan terhadap Regulasi</h2>
                <p class="text-gray-600 mb-6">QashierWise berkomitmen untuk mematuhi peraturan perlindungan data yang berlaku di Indonesia, termasuk Undang-Undang Perlindungan Data Pribadi (UU PDP), serta kebijakan WhatsApp Business API dan Meta Platform.</p>
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
                    <h3 class="text-gray-900 font-semibold mb-4">Navigasi</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/#fitur" class="text-gray-600 hover:text-primary transition">Fitur</a></li>
                        <li><a href="/#pricing" class="text-gray-600 hover:text-primary transition">Harga</a></li>
                        <li><a href="/#about" class="text-gray-600 hover:text-primary transition">Tentang Kami</a></li>
                        <li><a href="/#faq" class="text-gray-600 hover:text-primary transition">FAQ</a></li>
                    </ul>
                </div>

                <!-- Legal -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2 text-sm">
                        <li><a href="/privacy-policy" class="text-gray-600 hover:text-primary transition">Kebijakan Privasi</a></li>
                        <li><a href="/terms-of-service" class="text-gray-600 hover:text-primary transition">Ketentuan Layanan</a></li>
                    </ul>
                </div>

                <!-- Product -->
                <div>
                    <h3 class="text-gray-900 font-semibold mb-4">Produk</h4>
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
