# Konfigurasi Reservasi

Atur aturan reservasi di **Reservasi → Konfigurasi**. Pengaturan ini menentukan slot, biaya, DP, dan pesan otomatis.

## Pengaturan dasar

- **Toko** — outlet yang dikonfigurasi.
- **Biaya Reservasi (Rp)** — biaya reservasi (mis. *Rp 0* untuk gratis).
- **Kapasitas Per Slot** — jumlah maksimal reservasi yang diterima dalam satu slot waktu.

## Pembayaran & DP

- **Metode Pembayaran** — cara pelanggan membayar reservasi.
- **Persentase DP (%)** — besarnya uang muka yang harus dibayar untuk mengunci reservasi (mis. 50%). Sisa dibayar di tempat.

## Pesan WhatsApp otomatis

- **Template WhatsApp** — template yang dipakai untuk konfirmasi/pengingat.
- **Bahasa Template** — bahasa pesan.
- **Waktu Pengingat** — kapan pengingat dikirim sebelum jadwal (mis. T-24 jam, T-1 jam).
- **Nomor WhatsApp Tujuan** — nomor untuk notifikasi internal (mis. `6281234567890`).

## Jam buka & slot

Atur ketersediaan dengan:

- **Tanggal Mulai** & **Tanggal Akhir** — rentang tanggal yang menerima reservasi.
- **Jam Buka** (mis. `17:00`) & **Jam Tutup** (mis. `23:00`).
- **Jumlah maksimal reservasi per slot**.

## Form Publik {#form-publik}

QashierWise menyediakan **form reservasi publik** yang bisa dibagikan ke pelanggan (tautan `/reservations/form`). Lewat form ini pelanggan:

1. Memilih **tanggal & jam** (slot yang tersedia).
2. Memilih **meja** sesuai jumlah orang.
3. (Opsional) memilih **produk** untuk pre-order.
4. Mengisi data diri & **membayar DP** bila diaktifkan.

Setelah submit, pelanggan bisa memantau **status reservasi**, dan reservasi langsung masuk ke dashboard Anda.

Lihat hasilnya di **[Kalender Reservasi](/docs/reservasi/kalender)**.
