# Broadcast template WhatsApp dari Contacts

## Ringkasan

Tambahkan aksi **Broadcast** di menu **Contacts**. Pengguna memilih kontak, mengisi template, dan melihat estimasi sisa kuota sebelum mengirim. Jika jumlah penerima melebihi sisa kuota, seluruh broadcast ditolak sebelum pesan masuk antrean.

## Alur pengguna

- Centang kontak satu per satu atau satu halaman sekaligus. Pilihan tetap tersimpan saat pengguna berpindah halaman atau mengubah pencarian. Hitung satu penerima untuk setiap nomor WhatsApp unik.
- Tampilkan template teks kategori Marketing dan Utility yang sudah disetujui Meta untuk akun aktif. Dukung variabel teks di judul dan isi, baik bernomor maupun bernama. Setiap nilai yang diisi berlaku untuk semua penerima. Tampilkan pratinjau sebelum pengiriman.
- Tampilkan jumlah penerima dan **estimasi sisa kuota yang dipantau QashierWise**. Jika berlebih, tampilkan pesan “Broadcast melebihi limit WhatsApp. Kamu memilih {jumlah} penerima, sementara estimasi sisa kuota adalah {sisa}. Kurangi jumlah penerima untuk melanjutkan.”
- Sebelum mengirim, minta pengguna mengonfirmasi bahwa penerima telah memberikan izin untuk jenis pesan tersebut. Tampilkan pemberitahuan “Risiko pembatasan atau pemblokiran nomor WhatsApp akibat penggunaan broadcast menjadi tanggung jawab pemilik akun.” Keluarkan kontak yang tercatat berhenti berlangganan dari pengiriman dan tampilkan jumlahnya. [Kebijakan WhatsApp Business](https://business.whatsapp.com/policy)

## Kuota dan pengiriman

- Ambil tier terbaru dari Graph API melalui `whatsapp_business_manager_messaging_limit`. Kurangi tier terbatas dengan jumlah nomor unik yang tercatat menerima atau sedang menunggu pengiriman template dalam jendela berjalan 24 jam, di seluruh nomor pengirim yang diketahui berada dalam portofolio Meta yang sama. Gunakan riwayat pesan masuk untuk mengecualikan pengiriman sebelumnya yang terjadi dalam jendela layanan pelanggan. Penggunaan di luar catatan QashierWise dapat membuat sisa kuota aktual lebih kecil. [Dokumentasi limit pengiriman Meta](https://developers.facebook.com/documentation/business-messaging/whatsapp/messaging-limits)
- Tambahkan API terautentikasi `GET /api/whatsapp/broadcasts/options` untuk template dan estimasi kuota, serta `POST /api/whatsapp/broadcasts` untuk menerima ID kontak, ID template, nilai variabel, konfirmasi, dan kunci idempotensi. Validasi semuanya terhadap akun WhatsApp aktif yang memiliki kontak tersebut. Reservasi kuota dilakukan secara atomik per portofolio agar dua pengiriman serentak tidak memakai slot yang sama. Kembalikan `BROADCAST_LIMIT_EXCEEDED` beserta jumlah penerima dan sisa kuota jika berlebih, atau `BROADCAST_QUOTA_UNAVAILABLE` jika limit atau portofolio belum bisa diverifikasi.
- Simpan broadcast dan hasil per penerima, lalu kirim bertahap lewat antrean Laravel. Periksa ulang akun, status template, opt-out, dan kuota saat memproses antrean. Hentikan pengiriman yang tersisa jika kuota turun di bawah reservasi atau Meta membatasi akun. Jika hasil sebuah permintaan tidak pasti, tandai untuk diperiksa tanpa mengirim ulang otomatis.
- Simpan pesan yang diterima Meta ke riwayat WhatsApp dan gunakan webhook status yang sudah ada untuk memperbarui hasil pengiriman. Tampilkan riwayat dan progres di Contacts melalui `GET /api/whatsapp/broadcasts` dan `GET /api/whatsapp/broadcasts/{id}`, termasuk jumlah yang masih antre, diterima Meta, sampai ke penerima, gagal, atau dilewati.

## Verifikasi dan rilis

- Uji jumlah penerima tepat di batas dan satu di atasnya, tier tanpa batas, Meta gagal memberi limit, pemakaian lintas nomor dalam satu portofolio, serta dua broadcast yang bersaing memakai sisa kuota.
- Uji kontak atau template milik akun lain, nomor duplikat, opt-out sebelum dan sesudah masuk antrean, variabel bernomor dan bernama, klik kirim berulang, penolakan Meta, serta pembaruan status lewat webhook.
- Jalankan migrasi, verifikasi worker dan scheduler Laravel, lalu uji alur Contacts pada akun uji Meta. Jalankan `composer test` dan `vendor/bin/pint --test --dirty`.
