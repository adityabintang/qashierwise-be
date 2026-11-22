# Uji Coba AI Agent

Sebelum membiarkan AI melayani pelanggan sungguhan, uji jawabannya langsung dari dashboard.

## Playground chat

Pada menu **AI Agent** tersedia kolom **"Ketik pesan untuk test AI Agent..."**:

1. Ketik pertanyaan seperti yang akan ditanyakan pelanggan (mis. *"Jam buka berapa?"*, *"Ada menu apa saja?"*).
2. AI menjawab langsung di area chat uji coba.
3. Sesuaikan kembali **[konfigurasi](/docs/ai-agent/konfigurasi)** bila jawaban kurang tepat, lalu uji lagi.

Playground memakai persona & info bisnis yang sama dengan yang dipakai di produksi, jadi hasilnya mewakili pengalaman pelanggan.

## Tes pengiriman katalog

Anda juga bisa menguji apakah katalog terkirim dengan benar:

1. Pilih **katalog** yang ingin diuji.
2. Masukkan **nomor WhatsApp tujuan** dalam format E.164 tanpa tanda `+` (mis. `62812xxxxxxxx`).
3. Jalankan tes. Hasil menampilkan:
   - **Pesan status** keberhasilan.
   - **Jumlah produk terkirim** vs **total produk** dalam katalog.

Bila jumlah produk terkirim lebih kecil dari total, periksa kembali kelengkapan produk di **[Katalog](/docs/katalog/overview)** (mis. gambar/harga yang belum lengkap).

## Anti-spam

QashierWise punya proteksi anti-spam bawaan (pembatasan jumlah balasan & deduplikasi pesan) agar AI tidak membalas berlebihan atau membalas pesan yang sama dua kali. Fitur ini berjalan otomatis di latar belakang.
