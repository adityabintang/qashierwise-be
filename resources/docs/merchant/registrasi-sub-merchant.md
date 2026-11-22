# Registrasi Sub-Merchant (Aktifkan QRIS)

Agar bisa menerima pembayaran QRIS, akun Anda perlu terdaftar sebagai **Sub-Merchant** melalui Xendit. Prosesnya terdiri dari dua bagian: daftar dari dashboard QashierWise, lalu selesaikan verifikasi akun di situs Xendit.

> **Penting:** Jika verifikasi Xendit belum selesai, QRIS tidak bisa di-generate.

---

## Langkah 1 — Daftar dari Dashboard

Buka **Dashboard → Sub-Merchant**, isi nama usaha, lalu klik tombol **Daftar / Register**.

![Halaman pendaftaran sub-merchant](/docs/merchant/halaman-daftar.webp)

Sistem otomatis membuat akun di Xendit atas nama restoran Anda. Beberapa detik kemudian, **Xendit mengirim email undangan** ke alamat email login Anda.

---

## Langkah 2 — Buka Email dari Xendit

Cek inbox email Anda. Akan ada email dari Xendit dengan judul undangan untuk membuat akun.

![Email undangan dari Xendit](/docs/merchant/email-masuk.webp)

Klik **tautan** di dalam email tersebut. Anda akan diarahkan ke halaman pendaftaran Xendit.

> Jika email tidak ada di Inbox, cek folder **Spam / Junk**.

---

## Langkah 3 — Isi Form Akun Xendit

Di halaman Xendit, isi data berikut:

![Form buat akun Xendit](/docs/merchant/xendit-buat-akun.webp)

| Field | Keterangan |
|---|---|
| **Nama Lengkap** | Nama pemilik usaha |
| **Nama Bisnis** | Nama restoran / usaha |
| **Email** | Sudah terisi otomatis — jangan diubah |
| **Kata Sandi** | Buat password baru untuk akun Xendit Anda |

Centang reCAPTCHA, lalu klik **Daftar**.

---

## Langkah 4 — Verifikasi Email (OTP)

Xendit akan mengirim **kode OTP 6 digit** ke email Anda.

![Halaman input OTP Xendit](/docs/merchant/xendit-verifikasi-otp.webp)

Buka email dari Xendit dengan subjek *"Your Xendit Verification Code"*:

![Email OTP dari Xendit](/docs/merchant/xendit-email-otp.webp)

Salin kode 6 digit tersebut dan masukkan ke halaman Xendit. Kode berlaku **5 menit**.

---

## Langkah 5 — Isi Survey & Nomor HP

Xendit akan menampilkan beberapa pertanyaan singkat (jenis usaha, dll.). Jawab sesuai kondisi bisnis Anda.

Di langkah terakhir survey, masukkan **nomor HP aktif** Anda:

![Input nomor HP](/docs/merchant/xendit-nomor-hp.webp)

Klik **Submit**.

---

## Langkah 6 — Klik "Activate Now"

Setelah semua data terisi, Xendit menampilkan halaman konfirmasi. Klik **Activate Now** untuk melanjutkan ke proses verifikasi KYC.

![Halaman Activate Now](/docs/merchant/xendit-activate-now.webp)

> Proses aktivasi membutuhkan waktu **1–3 hari kerja** setelah dokumen diunggah.

---

## Langkah 7 — Upload Dokumen KYC

Anda akan diarahkan ke halaman **Account Activation** untuk mengunggah dokumen:

![Halaman upload dokumen KYC](/docs/merchant/xendit-upload-kyc.webp)

| Dokumen | Keterangan |
|---|---|
| **Business Logo** | Logo restoran / usaha |
| **Proof of Business** | Bukti legalitas usaha (SIUP, NIB, dll.) |
| **Photo of KTP** | KTP pemilik usaha (versi terbaru) |
| **Photo of NPWP** | NPWP (opsional, jika ada) |
| **Selfie** | Foto wajah pemilik usaha memegang KTP |

Klik **See Guidelines** pada tiap dokumen untuk melihat persyaratan foto yang benar. Setelah semua siap, klik **Start Now** dan ikuti langkah unggah.

---

## Langkah 8 — Pendaftaran Berhasil

Setelah semua dokumen dikirim, Xendit menampilkan halaman konfirmasi:

![Halaman sukses pendaftaran Xendit](/docs/merchant/xendit-berhasil.webp)

Klik **Kembali ke Dashboard**. Tim Xendit akan memproses verifikasi dalam **1–3 hari kerja**.

Setelah verifikasi selesai, kembali ke **QashierWise → Sub-Merchant → QRIS** — QRIS sudah bisa di-generate dan menerima pembayaran.

---

## Pertanyaan Umum

**Email Xendit tidak kunjung masuk?**
Tunggu 5–10 menit, cek folder Spam. Jika masih tidak ada, kembali ke dashboard QashierWise dan coba daftar ulang — sistem otomatis mengirim ulang email.

**Pilih "I'll do it later" di halaman Activate Now?**
Verifikasi bisa dilanjutkan kapan saja dengan login ke dashboard Xendit menggunakan email dan password yang sudah dibuat. Namun QRIS tetap tidak aktif sampai verifikasi selesai.

**Apakah ada biaya pendaftaran?**
Tidak ada. Setiap transaksi QRIS dikenai **fee platform 2,5%** yang dipotong otomatis dari nominal pembayaran.
