# Konfigurasi Delivery

Atur delivery di menu **Delivery**. Halaman ini berisi konfigurasi, direktori driver, dan galeri bukti pengiriman.

## Saklar utama

- **Aktif (is_active)** — saklar master delivery. Delivery hanya berjalan jika ini aktif.

> Toggle **Delivery** pada **[AI Agent](/docs/ai-agent/konfigurasi)** hanya bisa dinyalakan ketika konfigurasi delivery di sini **aktif**.

## Ongkir

- **Ongkir default (default_ongkir)** — biaya kirim standar. **Hanya** diatur di halaman Delivery ini, lalu otomatis disinkronkan ke alur AI Agent/katalog. (Halaman AI Agent tidak lagi punya input ongkir.)

## Syarat bukti pengiriman

Dua opsi yang mengatur halaman driver:

- **Wajib Foto (proof_required)** — driver harus mengunggah **foto** bukti barang diterima.
- **Wajib Lokasi (address_required)** — driver harus menandai **lokasi GPS** pengantaran di peta.

## Direktori driver

Kelola daftar driver yang bisa ditugaskan:

1. **Tambah driver** — nama & nomor WhatsApp.
2. Driver yang terdaftar muncul pada **dropdown penugasan** di **[Orders](/docs/pos/pesanan)**.

## Halaman bukti driver

Saat ditugaskan, driver menerima tautan unik `/delivery/{token}` (tanpa login). Di sana driver:

- Mengambil/mengunggah **foto** (otomatis dikompres agar cepat).
- Menandai **lokasi** lewat peta interaktif (tap untuk pin atau pakai GPS).

Galeri bukti pengiriman bisa Anda lihat kembali di halaman Delivery.

## Galeri & pemantauan

Halaman Delivery juga menampilkan **galeri bukti** dari pengiriman yang sudah selesai, sehingga Anda punya rekam jejak setiap pengantaran.
