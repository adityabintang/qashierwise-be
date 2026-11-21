# Unduh kontak dan riwayat pesan WhatsApp

## Ringkasan

Tambahkan unduhan **CSV dan Excel (.xlsx)** di menu **Kontak** dan **Pesan**. File dibuat saat diminta dari data QashierWise yang masih tersimpan, lalu disimpan di perangkat pengguna. Fitur tersedia gratis sementara untuk semua pemilik akun WhatsApp yang terhubung, termasuk akun trial. Satu pengaturan server mengembalikan akses ke paket Pro saat masa gratis berakhir.

## Alur pengguna

- **Kontak — Unduh Kontak.** Pilih semua kontak atau semua hasil pencarian nama dan nomor telepon. Unduhan mencakup hasil di seluruh halaman. Kolom berisi nama, nomor WhatsApp, tag, tanggal ditambahkan, dan waktu pesan terakhir.
- **Pesan — Unduh Pesan.** Dari daftar percakapan, unduh seluruh chat. Dari header percakapan, unduh chat yang sedang dibuka. Keduanya mendukung filter tanggal opsional. Setiap baris memuat nama dan nomor kontak, waktu pesan, arah masuk atau keluar, jenis, status, isi yang tersedia, caption, nama file, dan tautan lampiran. Rentang tanggal mengikuti zona waktu perangkat dan mencakup kedua tanggal yang dipilih.
- **Lampiran.** File ekspor berisi keterangan dan tautan ke halaman QashierWise. Tautan meminta login bila diperlukan, lalu membuka lampiran setelah server memverifikasi pemiliknya. Jika media sudah hilang atau belum tersimpan, halaman menjelaskan bahwa lampiran tidak tersedia.

## Implementasi

- Tambahkan `GET /api/whatsapp/exports/contacts` dengan parameter `format` dan `search` opsional, serta `GET /api/whatsapp/exports/messages` dengan `format`, `contact_id`, dan pasangan tanggal opsional. Gunakan autentikasi yang sudah dipakai dashboard. Setiap kueri harus dibatasi ke pemilik dan nomor WhatsApp aktif. Pemilik akun lain dan staf tidak bisa mengambil file melalui API.
- Gunakan satu layanan pembentuk baris untuk kedua format. Baca data per bagian dari database, tulis CSV UTF-8, dan buat XLSX per baris dengan FastExcelWriter. Simpan nomor telepon dan teks sebagai sel teks di Excel, serta netralkan isi yang bisa dieksekusi sebagai formula. Urutkan pesan dari yang terlama berdasarkan waktu kirim, dengan waktu pencatatan sebagai cadangan. [FastExcelWriter](https://github.com/aVadim483/fast-excel-writer)
- Buat file di penyimpanan sementara privat, kirim sebagai unduhan tanpa cache, lalu hapus setelah respons selesai atau gagal. Tetapkan batas awal yang bisa dikonfigurasi sebesar **25.000 baris atau 25 MiB data per unduhan**. Jika batas terlampaui, tampilkan pesan untuk mempersempit tanggal atau pencarian. File hanya dikirim jika selesai dibuat.
- Tautan lampiran menggunakan ID pesan pada URL QashierWise. Endpoint lampiran memeriksa pemilik dan nomor aktif kembali sebelum membaca media dari penyimpanan yang dikenali. Setelah login, pengguna diarahkan kembali ke lampiran yang diminta. File ekspor tidak memuat URL penyimpanan publik, kredensial, atau metadata webhook mentah.

## Pengujian

- Cocokkan hasil CSV dan Excel untuk seluruh kontak, hasil pencarian, satu percakapan, dan semua pesan. Pastikan data yang berada di luar halaman pertama ikut terunduh.
- Uji batas tanggal, pesan dengan emoji dan baris baru, nomor berawalan `+` atau `0`, isi yang menyerupai formula, hasil kosong, dan ekspor yang melampaui batas.
- Uji akses pemilik di paket gratis dan Pro, pembatasan saat mode gratis dimatikan, serta penolakan akses dari staf dan akun lain. Uji juga tautan lampiran sebelum dan setelah login, media yang hilang, dan pembersihan file sementara.
