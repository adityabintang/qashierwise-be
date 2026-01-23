<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kebijakan Pengembalian Dana - QashierWise</title>

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
            <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4">Kebijakan Pengembalian Dana</h1>
            <p class="text-gray-500 mb-12">Terakhir diperbarui: 23 Januari 2026</p>

            <!-- Content -->
            <div class="prose prose-lg max-w-none">
                <!-- Introduction -->
                <p class="text-gray-600 mb-6">Di QashierWise, kami berkomitmen untuk memberikan layanan terbaik kepada pelanggan kami. Kebijakan pengembalian dana ini menjelaskan hak Anda terkait pengembalian dana untuk layanan berlangganan QashierWise.</p>

                <!-- Section 1 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">1. Periode Uji Coba Gratis</h2>
                <p class="text-gray-600 mb-4">QashierWise menawarkan periode uji coba gratis selama 14 hari untuk pengguna baru:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Tidak ada biaya yang dikenakan selama periode uji coba</li>
                    <li>Anda dapat membatalkan kapan saja selama periode uji coba tanpa dikenakan biaya</li>
                    <li>Setelah periode uji coba berakhir, langganan akan otomatis dikonversi ke paket berbayar yang dipilih</li>
                    <li>Anda akan menerima notifikasi sebelum periode uji coba berakhir</li>
                </ul>

                <!-- Section 2 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">2. Kebijakan Pengembalian Dana 7 Hari</h2>
                <p class="text-gray-600 mb-4">Kami menawarkan jaminan pengembalian dana 100% dalam 7 hari pertama setelah pembayaran pertama Anda:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Berlaku untuk pembayaran pertama paket Standard dan Pro</li>
                    <li>Permintaan pengembalian dana harus diajukan dalam 7 hari kalender sejak tanggal pembayaran</li>
                    <li>Pengembalian dana akan diproses ke metode pembayaran asli</li>
                    <li>Waktu pemrosesan pengembalian dana: 5-14 hari kerja tergantung penyedia pembayaran</li>
                    <li>Akun Anda akan dinonaktifkan setelah pengembalian dana diproses</li>
                </ul>

                <!-- Section 3 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">3. Pembatalan Langganan</h2>
                <p class="text-gray-600 mb-4">Anda dapat membatalkan langganan Anda kapan saja:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Pembatalan dapat dilakukan melalui dashboard akun Anda</li>
                    <li>Layanan akan tetap aktif hingga akhir periode billing yang telah dibayar</li>
                    <li>Tidak ada pengembalian dana prorata untuk pembatalan di tengah periode billing (setelah 7 hari pertama)</li>
                    <li>Data Anda akan disimpan selama 30 hari setelah pembatalan untuk memudahkan reaktivasi</li>
                    <li>Setelah 30 hari, data akan dihapus secara permanen sesuai kebijakan privasi kami</li>
                </ul>

                <!-- Section 4 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">4. Kondisi yang Tidak Memenuhi Syarat Pengembalian Dana</h2>
                <p class="text-gray-600 mb-4">Pengembalian dana tidak akan diberikan dalam kondisi berikut:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Permintaan pengembalian dana diajukan setelah 7 hari sejak pembayaran pertama</li>
                    <li>Pelanggaran terhadap Ketentuan Layanan kami</li>
                    <li>Penyalahgunaan layanan atau aktivitas penipuan</li>
                    <li>Pembayaran perpanjangan langganan (hanya pembayaran pertama yang memenuhi syarat)</li>
                    <li>Biaya transaksi QRIS atau payment gateway yang dikenakan oleh pihak ketiga</li>
                    <li>Downgrade dari paket Pro ke Standard atau Basic</li>
                </ul>

                <!-- Section 5 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">5. Cara Mengajukan Pengembalian Dana</h2>
                <p class="text-gray-600 mb-4">Untuk mengajukan pengembalian dana, ikuti langkah-langkah berikut:</p>
                <ol class="list-decimal pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Hubungi tim support kami melalui email di support@qashierwise.com atau WhatsApp di +62 882-1545-7494</li>
                    <li>Sertakan informasi berikut:
                        <ul class="list-disc pl-6 mt-2 space-y-1">
                            <li>Nama akun dan email terdaftar</li>
                            <li>Nomor invoice atau ID transaksi</li>
                            <li>Alasan permintaan pengembalian dana</li>
                            <li>Tanggal pembayaran</li>
                        </ul>
                    </li>
                    <li>Tim kami akan meninjau permintaan Anda dalam 2-3 hari kerja</li>
                    <li>Jika disetujui, pengembalian dana akan diproses dalam 5-14 hari kerja</li>
                    <li>Anda akan menerima konfirmasi email setelah pengembalian dana diproses</li>
                </ol>

                <!-- Section 6 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">6. Pengembalian Dana untuk Transaksi QRIS</h2>
                <p class="text-gray-600 mb-4">Untuk transaksi QRIS yang dilakukan oleh pelanggan restoran Anda:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>QashierWise hanya menyediakan platform, bukan penyedia payment gateway</li>
                    <li>Pengembalian dana transaksi QRIS diatur oleh kebijakan penyedia payment gateway Anda (Midtrans, Xendit, dll)</li>
                    <li>Anda bertanggung jawab untuk mengelola pengembalian dana kepada pelanggan Anda</li>
                    <li>QashierWise tidak bertanggung jawab atas sengketa transaksi antara Anda dan pelanggan Anda</li>
                    <li>Biaya transaksi yang dikenakan oleh payment gateway tidak dapat dikembalikan</li>
                </ul>

                <!-- Section 7 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">7. Upgrade dan Downgrade Paket</h2>
                <p class="text-gray-600 mb-4">Ketentuan untuk perubahan paket langganan:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li><strong>Upgrade:</strong> Perbedaan harga akan diprorata dan ditagih segera. Fitur baru akan aktif setelah pembayaran berhasil</li>
                    <li><strong>Downgrade:</strong> Perubahan akan berlaku pada periode billing berikutnya. Tidak ada pengembalian dana untuk perbedaan harga</li>
                    <li>Anda dapat mengubah paket kapan saja melalui dashboard akun</li>
                    <li>Fitur yang tidak tersedia di paket baru akan dinonaktifkan setelah downgrade</li>
                </ul>

                <!-- Section 8 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">8. Gangguan Layanan dan Kompensasi</h2>
                <p class="text-gray-600 mb-4">Dalam hal terjadi gangguan layanan yang signifikan:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Kami akan memberikan notifikasi tentang gangguan layanan melalui email atau dashboard</li>
                    <li>Jika gangguan berlangsung lebih dari 24 jam berturut-turut, Anda berhak mendapatkan kredit layanan prorata</li>
                    <li>Kredit layanan akan otomatis diterapkan ke periode billing berikutnya</li>
                    <li>Gangguan yang disebabkan oleh pemeliharaan terjadwal tidak memenuhi syarat untuk kompensasi</li>
                    <li>Force majeure (bencana alam, perang, dll) tidak termasuk dalam kebijakan kompensasi</li>
                </ul>

                <!-- Section 9 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">9. Perubahan Kebijakan</h2>
                <p class="text-gray-600 mb-6">QashierWise berhak untuk mengubah kebijakan pengembalian dana ini kapan saja. Perubahan akan:</p>
                <ul class="list-disc pl-6 text-gray-600 space-y-2 mb-6">
                    <li>Diposting di halaman ini dengan tanggal "terakhir diperbarui" yang baru</li>
                    <li>Diberitahukan kepada pengguna aktif melalui email</li>
                    <li>Berlaku untuk transaksi baru setelah tanggal perubahan</li>
                    <li>Tidak mempengaruhi hak pengembalian dana yang sudah ada sebelum perubahan</li>
                </ul>

                <!-- Section 10 -->
                <h2 class="text-2xl font-bold text-gray-900 mt-8 mb-4">10. Hubungi Kami</h2>
                <p class="text-gray-600 mb-6">Jika Anda memiliki pertanyaan tentang kebijakan pengembalian dana ini atau ingin mengajukan permintaan pengembalian dana, silakan hubungi kami:</p>

                <!-- Contact Box -->
                <div class="bg-gray-50 rounded-xl p-6 mb-8">
                    <h3 class="font-bold text-gray-900 mb-3">QashierWise - Customer Support</h3>
                    <div class="text-gray-600 space-y-1">
                        <p><span class="font-medium">Email:</span> support@qashierwise.com</p>
                        <p><span class="font-medium">WhatsApp:</span> +62 882-1545-7494</p>
                        <p><span class="font-medium">Jam Operasional:</span> Senin - Jumat, 09:00 - 17:00 WIB</p>
                        <p><span class="font-medium">Alamat:</span> Jl. Widosari No. 55, Tegalrejo Raya, Salatiga, Jawa Tengah, Indonesia 50733</p>
                    </div>
                </div>

                <!-- Important Notice -->
                <div class="bg-blue-50 border-l-4 border-primary rounded-lg p-6 mb-8">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-primary text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Catatan Penting</h3>
                            <div class="text-sm text-gray-600">
                                <p>Dengan menggunakan layanan QashierWise, Anda menyetujui kebijakan pengembalian dana ini. Kami sangat menyarankan Anda untuk memanfaatkan periode uji coba gratis 14 hari untuk memastikan layanan kami sesuai dengan kebutuhan bisnis Anda sebelum melakukan pembayaran.</p>
                            </div>
                        </div>
                    </div>
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
                        <li><a href="/refund-policy" class="text-gray-600 hover:text-primary transition">Kebijakan Pengembalian Dana</a></li>
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
                <p>&copy; 2025 QashierWise by Aditya Bintang Fadila. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
