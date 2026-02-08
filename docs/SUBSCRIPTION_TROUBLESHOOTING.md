# Troubleshooting Guide - Subscription

## Daftar Isi
1. [Masalah Pembayaran](#masalah-pembayaran)
2. [Masalah Subscription](#masalah-subscription)
3. [Masalah Akses](#masalah-akses)
4. [Masalah Teknis](#masalah-teknis)
5. [Masalah Kartu Kredit](#masalah-kartu-kredit)
6. [Masalah E-Wallet](#masalah-e-wallet)
7. [Masalah Bank Transfer](#masalah-bank-transfer)
8. [Error Messages](#error-messages)
9. [Kapan Harus Menghubungi Support](#kapan-harus-menghubungi-support)

---

## Masalah Pembayaran

### Pembayaran Ditolak / Gagal

**Gejala**: Pembayaran ditolak saat checkout atau recurring payment gagal.

**Penyebab Umum**:
- Saldo/limit kartu tidak mencukupi
- Kartu expired atau diblokir
- Transaksi online tidak diaktifkan
- Kesalahan input data kartu
- Bank menolak transaksi

**Solusi**:

1. **Periksa Saldo/Limit Kartu**
   - Pastikan saldo mencukupi untuk pembayaran
   - Periksa limit transaksi harian/bulanan
   - Hubungi bank jika perlu menaikkan limit

2. **Periksa Status Kartu**
   - Pastikan kartu tidak expired
   - Pastikan kartu tidak diblokir
   - Hubungi bank untuk mengaktifkan kartu

3. **Aktifkan Transaksi Online**
   - Login ke mobile banking
   - Aktifkan fitur transaksi online/e-commerce
   - Atau hubungi bank untuk aktivasi

4. **Periksa Data Kartu**
   - Pastikan nomor kartu benar (16 digit)
   - Pastikan tanggal expired benar (MM/YY)
   - Pastikan CVV benar (3 digit di belakang kartu)
   - Pastikan nama sesuai dengan kartu

5. **Coba Metode Pembayaran Lain**
   - Gunakan kartu lain
   - Atau gunakan e-wallet/bank transfer

6. **Hubungi Bank**
   - Tanyakan mengapa transaksi ditolak
   - Minta bank untuk approve transaksi
   - Pastikan tidak ada fraud alert

**Jika Masih Gagal**: Hubungi support kami dengan screenshot error dan detail transaksi.

---

### Pembayaran Pending Terlalu Lama

**Gejala**: Status pembayaran "pending" lebih dari 1 jam.

**Penyebab Umum**:
- Verifikasi bank belum selesai
- Pembayaran belum dikonfirmasi (untuk VA/convenience store)
- Masalah koneksi dengan payment gateway

**Solusi**:

1. **Untuk Kartu Kredit**:
   - Periksa SMS/email dari bank untuk OTP
   - Selesaikan verifikasi 3D Secure
   - Tunggu 5-10 menit untuk konfirmasi

2. **Untuk E-Wallet**:
   - Buka aplikasi e-wallet
   - Selesaikan verifikasi pembayaran
   - Pastikan saldo mencukupi

3. **Untuk Virtual Account**:
   - Pastikan Anda sudah transfer ke nomor VA
   - Pastikan jumlah transfer sesuai
   - Tunggu 5-10 menit untuk konfirmasi otomatis

4. **Untuk Convenience Store**:
   - Pastikan Anda sudah bayar di kasir
   - Simpan struk sebagai bukti
   - Tunggu 5-10 menit untuk konfirmasi

5. **Refresh Halaman**:
   - Refresh halaman subscription
   - Atau logout dan login kembali

**Jika Masih Pending Setelah 1 Jam**: Hubungi support dengan order ID dan bukti pembayaran.

---

### Double Charge / Pembayaran Ganda

**Gejala**: Dicharge 2x untuk subscription yang sama.

**Penyebab Umum**:
- Klik tombol bayar multiple kali
- Refresh halaman saat proses pembayaran
- Bug sistem (jarang terjadi)

**Solusi**:

1. **Periksa Riwayat Transaksi**:
   - Cek statement bank/kartu kredit
   - Pastikan benar-benar ada 2 transaksi
   - Catat tanggal dan jumlah transaksi

2. **Periksa Email Konfirmasi**:
   - Cek email dari Midtrans
   - Hitung jumlah email konfirmasi pembayaran

3. **Hubungi Support Segera**:
   - Kirim screenshot statement bank
   - Kirim email konfirmasi dari Midtrans
   - Berikan order ID untuk kedua transaksi

4. **Proses Refund**:
   - Support akan investigasi
   - Jika terbukti double charge, refund akan diproses
   - Refund biasanya 7-14 hari kerja

**Pencegahan**: Jangan klik tombol bayar multiple kali atau refresh halaman saat proses pembayaran.

---

### Pembayaran Berhasil Tapi Subscription Tidak Aktif

**Gejala**: Sudah bayar dan dapat konfirmasi, tapi subscription masih belum aktif.

**Penyebab Umum**:
- Delay dalam update status
- Webhook dari Midtrans belum diterima
- Masalah sinkronisasi database

**Solusi**:

1. **Tunggu 5-10 Menit**:
   - Kadang ada delay dalam update status
   - Refresh halaman setelah beberapa menit

2. **Logout dan Login Kembali**:
   - Logout dari akun
   - Clear browser cache
   - Login kembali

3. **Periksa Email Konfirmasi**:
   - Pastikan Anda menerima email konfirmasi dari Midtrans
   - Catat order ID dari email

4. **Hubungi Support**:
   - Kirim bukti pembayaran (screenshot/email)
   - Berikan order ID
   - Support akan manual activate subscription Anda

**Jika Urgent**: Hubungi support via WhatsApp untuk penanganan cepat.

---

## Masalah Subscription

### Subscription Tidak Muncul di Dashboard

**Gejala**: Sudah berlangganan tapi tidak muncul di dashboard.

**Solusi**:

1. **Refresh Halaman**:
   - Tekan Ctrl+F5 (Windows) atau Cmd+Shift+R (Mac)
   - Atau clear browser cache

2. **Periksa Akun yang Benar**:
   - Pastikan Anda login dengan akun yang benar
   - Jika punya multiple akun, coba login dengan akun lain

3. **Periksa Status Pembayaran**:
   - Buka halaman [Manage Subscription](/subscription/manage)
   - Periksa apakah pembayaran sudah berhasil

4. **Logout dan Login Kembali**:
   - Logout dari akun
   - Clear browser cache
   - Login kembali

**Jika Masih Tidak Muncul**: Hubungi support dengan bukti pembayaran.

---

### Subscription Expired Padahal Sudah Bayar

**Gejala**: Subscription status "expired" meskipun sudah melakukan pembayaran.

**Penyebab Umum**:
- Pembayaran recurring gagal
- Kartu expired atau limit tidak cukup
- Subscription dibatalkan sebelumnya

**Solusi**:

1. **Periksa Email Notifikasi**:
   - Cek email untuk notifikasi pembayaran gagal
   - Cek email untuk notifikasi subscription expired

2. **Periksa Riwayat Pembayaran**:
   - Cek statement bank/kartu kredit
   - Pastikan pembayaran bulan ini sudah diproses

3. **Periksa Status Kartu**:
   - Pastikan kartu tidak expired
   - Pastikan limit mencukupi

4. **Lakukan Pembayaran Manual**:
   - Jika pembayaran recurring gagal, lakukan pembayaran manual
   - Kunjungi halaman [Pricing](/pricing)
   - Berlangganan kembali

**Jika Sudah Bayar Tapi Masih Expired**: Hubungi support dengan bukti pembayaran.

---

### Tidak Bisa Membatalkan Subscription

**Gejala**: Tombol "Batalkan Subscription" tidak berfungsi atau tidak muncul.

**Solusi**:

1. **Periksa Status Subscription**:
   - Pastikan subscription status "active"
   - Subscription yang sudah "cancelled" atau "expired" tidak bisa dibatalkan lagi

2. **Periksa Browser**:
   - Coba gunakan browser lain
   - Clear browser cache
   - Disable browser extensions

3. **Periksa JavaScript**:
   - Pastikan JavaScript enabled di browser
   - Coba disable ad blocker

4. **Coba Device Lain**:
   - Coba dari komputer/laptop
   - Atau coba dari mobile

**Jika Masih Tidak Bisa**: Hubungi support untuk manual cancellation.

---

## Masalah Akses

### Tidak Bisa Mengakses Fitur Premium

**Gejala**: Subscription aktif tapi tidak bisa mengakses fitur premium.

**Solusi**:

1. **Periksa Status Subscription**:
   - Buka halaman [Manage Subscription](/subscription/manage)
   - Pastikan status "active"
   - Pastikan belum expired

2. **Logout dan Login Kembali**:
   - Logout dari akun
   - Clear browser cache
   - Login kembali

3. **Periksa Paket Subscription**:
   - Pastikan paket Anda mendukung fitur yang ingin diakses
   - Beberapa fitur hanya tersedia di paket Pro

4. **Clear Browser Cache**:
   - Clear cache dan cookies
   - Restart browser

**Jika Masih Tidak Bisa**: Hubungi support dengan detail fitur yang tidak bisa diakses.

---

### Error "Subscription Required"

**Gejala**: Muncul error "Subscription Required" meskipun sudah berlangganan.

**Solusi**:

1. **Periksa Status Subscription**:
   - Pastikan subscription masih aktif
   - Pastikan belum expired

2. **Refresh Session**:
   - Logout dan login kembali
   - Clear browser cache

3. **Periksa Tanggal Expired**:
   - Buka halaman [Manage Subscription](/subscription/manage)
   - Periksa tanggal akhir periode
   - Pastikan belum melewati tanggal tersebut

**Jika Subscription Aktif Tapi Masih Error**: Hubungi support.

---

## Masalah Teknis

### Halaman Pembayaran Tidak Muncul

**Gejala**: Setelah klik "Berlangganan", halaman pembayaran Midtrans tidak muncul.

**Solusi**:

1. **Periksa Pop-up Blocker**:
   - Disable pop-up blocker di browser
   - Allow pop-up untuk website kami
   - Coba klik tombol lagi

2. **Periksa JavaScript**:
   - Pastikan JavaScript enabled
   - Disable browser extensions yang mungkin block JavaScript

3. **Coba Browser Lain**:
   - Coba Chrome, Firefox, atau Edge
   - Update browser ke versi terbaru

4. **Clear Browser Cache**:
   - Clear cache dan cookies
   - Restart browser
   - Coba lagi

**Jika Masih Tidak Muncul**: Hubungi support.

---

### Redirect Loop / Infinite Loading

**Gejala**: Halaman terus loading atau redirect berulang-ulang.

**Solusi**:

1. **Clear Browser Cache**:
   - Clear cache dan cookies
   - Restart browser

2. **Disable Browser Extensions**:
   - Disable semua extensions
   - Coba lagi

3. **Coba Incognito/Private Mode**:
   - Buka browser dalam mode incognito
   - Coba akses halaman

4. **Coba Browser/Device Lain**:
   - Gunakan browser lain
   - Atau coba dari device lain

**Jika Masih Loop**: Hubungi support dengan detail browser dan device yang digunakan.

---

### Error 500 / Server Error

**Gejala**: Muncul error "500 Internal Server Error".

**Solusi**:

1. **Tunggu Beberapa Menit**:
   - Mungkin ada maintenance atau masalah sementara
   - Coba lagi setelah 5-10 menit

2. **Refresh Halaman**:
   - Tekan F5 atau Ctrl+F5
   - Atau clear cache dan coba lagi

3. **Coba Lagi Nanti**:
   - Jika error persisten, tunggu 30 menit - 1 jam
   - Mungkin ada maintenance scheduled

**Jika Error Persisten**: Hubungi support, mungkin ada masalah di server kami.

---

## Masalah Kartu Kredit

### Kartu Ditolak Meskipun Saldo Cukup

**Solusi**:

1. **Periksa Transaksi Online**:
   - Pastikan fitur transaksi online aktif
   - Login ke mobile banking dan aktifkan

2. **Periksa Limit Transaksi**:
   - Periksa limit transaksi harian/bulanan
   - Hubungi bank untuk menaikkan limit

3. **Periksa Fraud Alert**:
   - Bank mungkin block transaksi karena fraud alert
   - Hubungi bank dan minta approve transaksi

4. **Periksa Kartu Internasional**:
   - Pastikan kartu mendukung transaksi internasional
   - Midtrans mungkin dikenali sebagai merchant internasional

---

### OTP Tidak Diterima

**Solusi**:

1. **Periksa Nomor HP**:
   - Pastikan nomor HP terdaftar di bank benar
   - Update nomor HP di bank jika perlu

2. **Tunggu Beberapa Menit**:
   - OTP kadang delay 1-5 menit
   - Jangan request OTP berulang kali

3. **Periksa Signal HP**:
   - Pastikan HP ada signal
   - Coba restart HP

4. **Hubungi Bank**:
   - Tanyakan mengapa OTP tidak dikirim
   - Minta bank resend OTP

---

### Verifikasi 3D Secure Gagal

**Solusi**:

1. **Pastikan OTP Benar**:
   - Input OTP dengan benar
   - Jangan typo

2. **Jangan Expired**:
   - OTP biasanya valid 5 menit
   - Jika expired, request OTP baru

3. **Coba Lagi**:
   - Jika gagal, coba proses pembayaran dari awal
   - Request OTP baru

---

## Masalah E-Wallet

### QR Code Tidak Muncul

**Solusi**:

1. **Refresh Halaman**:
   - Refresh halaman pembayaran
   - QR code akan generate ulang

2. **Coba Browser Lain**:
   - Gunakan browser lain
   - Atau coba dari mobile

3. **Periksa Koneksi Internet**:
   - Pastikan koneksi stabil
   - Coba reconnect

---

### Scan QR Code Gagal

**Solusi**:

1. **Periksa Aplikasi E-Wallet**:
   - Pastikan aplikasi sudah login
   - Update aplikasi ke versi terbaru

2. **Periksa Kamera**:
   - Pastikan kamera HP berfungsi
   - Bersihkan lensa kamera

3. **Periksa Pencahayaan**:
   - Pastikan pencahayaan cukup
   - Jangan terlalu gelap atau terlalu terang

4. **Screenshot QR Code**:
   - Screenshot QR code
   - Upload dari galeri di aplikasi e-wallet

---

### Saldo E-Wallet Tidak Cukup

**Solusi**:

1. **Top Up Saldo**:
   - Top up saldo e-wallet
   - Pastikan saldo mencukupi untuk pembayaran

2. **Gunakan Metode Lain**:
   - Gunakan e-wallet lain
   - Atau gunakan kartu kredit/bank transfer

---

## Masalah Bank Transfer

### Nomor Virtual Account Tidak Muncul

**Solusi**:

1. **Refresh Halaman**:
   - Refresh halaman pembayaran
   - Nomor VA akan generate ulang

2. **Coba Browser Lain**:
   - Gunakan browser lain
   - Clear cache

3. **Periksa Koneksi Internet**:
   - Pastikan koneksi stabil

---

### Transfer Gagal / Ditolak

**Solusi**:

1. **Periksa Nomor VA**:
   - Pastikan nomor VA benar
   - Copy-paste untuk menghindari typo

2. **Periksa Jumlah Transfer**:
   - Pastikan jumlah transfer sesuai persis
   - Jangan lebih atau kurang

3. **Periksa Saldo**:
   - Pastikan saldo rekening mencukupi

4. **Coba Bank Lain**:
   - Jika transfer dari bank lain gagal, coba dari bank yang sama dengan VA

---

### Transfer Berhasil Tapi Tidak Terkonfirmasi

**Solusi**:

1. **Tunggu 5-10 Menit**:
   - Konfirmasi biasanya instan, tapi bisa delay

2. **Periksa Bukti Transfer**:
   - Pastikan transfer berhasil
   - Pastikan nomor VA benar

3. **Hubungi Support**:
   - Kirim bukti transfer
   - Berikan nomor VA dan order ID

---

## Error Messages

### "Payment method not available"

**Penyebab**: Metode pembayaran tidak tersedia untuk transaksi ini.

**Solusi**: Gunakan metode pembayaran lain.

---

### "Transaction expired"

**Penyebab**: Waktu pembayaran sudah habis (biasanya 24 jam).

**Solusi**: Mulai proses pembayaran dari awal.

---

### "Invalid card number"

**Penyebab**: Nomor kartu salah atau tidak valid.

**Solusi**: Periksa dan input ulang nomor kartu dengan benar.

---

### "Insufficient balance"

**Penyebab**: Saldo/limit tidak mencukupi.

**Solusi**: Top up saldo atau gunakan kartu/metode lain.

---

### "Card expired"

**Penyebab**: Kartu sudah expired.

**Solusi**: Gunakan kartu yang masih valid.

---

### "Transaction declined by bank"

**Penyebab**: Bank menolak transaksi.

**Solusi**: Hubungi bank untuk mengetahui alasan penolakan.

---

### "Duplicate transaction"

**Penyebab**: Transaksi yang sama sudah diproses sebelumnya.

**Solusi**: Periksa status subscription. Jika sudah aktif, tidak perlu bayar lagi.

---

## Kapan Harus Menghubungi Support

Hubungi support jika:

- ✅ Sudah mencoba semua solusi di atas tapi masih bermasalah
- ✅ Terjadi double charge
- ✅ Pembayaran berhasil tapi subscription tidak aktif lebih dari 1 jam
- ✅ Error yang tidak ada di troubleshooting guide ini
- ✅ Masalah urgent yang perlu penanganan cepat

### Informasi yang Perlu Disiapkan

Saat menghubungi support, siapkan:

1. **Detail Akun**:
   - Email akun
   - User ID (jika ada)

2. **Detail Masalah**:
   - Deskripsi masalah
   - Kapan masalah terjadi
   - Langkah yang sudah dicoba

3. **Detail Transaksi** (jika terkait pembayaran):
   - Order ID
   - Tanggal transaksi
   - Jumlah pembayaran
   - Metode pembayaran

4. **Bukti**:
   - Screenshot error message
   - Screenshot bukti pembayaran
   - Email konfirmasi dari Midtrans

### Cara Menghubungi Support

- **Email**: support@example.com
- **WhatsApp**: +62 xxx-xxxx-xxxx
- **Live Chat**: Di dashboard (untuk subscriber aktif)
- **Jam Operasional**: Senin - Jumat, 09:00 - 17:00 WIB

---

## Tips Mencegah Masalah

### Sebelum Berlangganan

- ✅ Pastikan koneksi internet stabil
- ✅ Gunakan browser yang updated
- ✅ Disable ad blocker dan pop-up blocker
- ✅ Pastikan saldo/limit mencukupi
- ✅ Pastikan kartu aktif dan tidak expired

### Saat Pembayaran

- ✅ Jangan klik tombol bayar multiple kali
- ✅ Jangan refresh halaman saat proses pembayaran
- ✅ Tunggu hingga proses selesai
- ✅ Simpan bukti pembayaran

### Setelah Berlangganan

- ✅ Simpan email konfirmasi
- ✅ Catat tanggal billing
- ✅ Pastikan kartu tidak expired sebelum tanggal billing
- ✅ Pastikan saldo/limit cukup untuk pembayaran recurring

---

*Terakhir diperbarui: 24 Januari 2026*
