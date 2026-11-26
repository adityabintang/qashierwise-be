# Dashboard Internal QashierWise

## Ringkasan

Tambahkan halaman **Laporan Internal** di `/admin/analytics`, khusus Super Admin. Halaman ini menampilkan pertumbuhan merchant, penggunaan produk, status langganan, dan volume pesan WhatsApp. Dashboard dan CSV mencantumkan waktu pengambilan data, periode, dan definisi setiap metrik agar angkanya bisa dipakai sebagai bukti perusahaan.

## Metrik

| Metrik | Cara menghitung |
|---|---|
| **Merchant terdaftar** | Satu akun utama per merchant. Akun staf POS, penulis CMS, dan Super Admin dikecualikan. |
| **Pertumbuhan 12 bulan** | Grafik pendaftaran baru per bulan berdasarkan tanggal pembuatan akun. Bulan kosong bernilai nol dan bulan berjalan diberi penanda. |
| **Merchant aktif** | Akun utama dengan minimal satu pesan WhatsApp tercatat, satu order dibuat, atau satu reservasi dibuat dalam 30 hari terakhir. Satu merchant dihitung sekali meski menggunakan beberapa fitur atau memiliki beberapa toko. |
| **Paid** | Merchant dengan periode langganan yang masih berlaku dan bukti pembayaran berhasil untuk periode tersebut. Merchant yang membatalkan perpanjangan tetap dihitung selama periodenya masih berlaku. |
| **Unpaid dan Belum Terverifikasi** | **Unpaid** mencakup merchant tanpa periode langganan yang berlaku, termasuk trial dan pembayaran tertunda. **Belum Terverifikasi** mencakup langganan yang masih berlaku dengan bukti bayar yang belum tersedia di riwayat. Ketiga kategori berjumlah sama dengan total merchant terdaftar. |
| **Pesan WhatsApp tercatat** | Total pesan yang masih tersimpan, dengan rincian masuk dan keluar. Pesan gagal yang tercatat tetap termasuk dalam volume. |
| **Merchant dengan WhatsApp aktif** | Jumlah merchant dengan minimal satu akun WhatsApp berstatus aktif. |

## Implementasi

- Tambahkan `GET /api/admin/analytics` di balik autentikasi Sanctum dan pemeriksaan Super Admin di server. Gunakan API ini untuk mengirim agregat, 12 titik grafik bulanan, dan waktu laporan dalam WIB. Menu statistik blog tetap tersedia bagi pengguna CMS.
- Hitung agregat dari data yang sudah ada. Kaitkan order ke pemilik toko, reservasi dan pesan ke pemilik akun, serta bukti bayar ke langganan dan periode yang berlaku. Untuk Xendit, gunakan catatan pembayaran siklus yang berhasil dan masukkan aktivasi akun tanpa bukti tersebut ke **Belum Terverifikasi**. Xendit mendokumentasikan aktivasi paket dan keberhasilan pembayaran siklus sebagai dua peristiwa terpisah. [Dokumentasi webhook Xendit](https://docs.xendit.co/apidocs/subscription-webhook)
- Gunakan komponen React dan grafik yang sudah tersedia. Ekspor CSV dari data yang sedang tampil agar angkanya persis sama dengan dashboard. CSV memuat agregat, deret bulanan, periode, waktu pengambilan, dan definisi metrik. Data laporan berisi angka agregat saja. Versi awal menggunakan riwayat yang tersimpan dan tidak memerlukan migrasi database.

## Verifikasi

- Uji akses Super Admin serta penolakan untuk tamu, penulis CMS, dan merchant biasa.
- Uji deduplikasi merchant, batas 30 hari, pergantian bulan WIB, bulan kosong, dan rekonsiliasi total pesan dengan rincian masuk dan keluar.
- Uji langganan yang habis, dibatalkan saat masih berlaku, dibayar dengan bukti lama, dan aktif tanpa bukti bayar. Cocokkan CSV dengan angka yang tampil, lalu jalankan tes Laravel, Pint, dan build Vite.
