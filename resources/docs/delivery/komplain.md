# Komplain

Saat pelanggan menekan **KOMPLAIN** pada konfirmasi penerimaan barang, sebuah komplain otomatis dibuat dan masuk ke antrean. Buka menu **Complain** di dashboard.

## Apa yang terjadi saat komplain masuk

1. Sebuah entri **komplain** dibuat dengan status **open**.
2. **AI Agent dimatikan** untuk kontak tersebut (`ai_active = false`) — agar ditangani **manual oleh PIC**, bukan bot.
3. Komplain tampil di antrean **Complain** untuk ditindaklanjuti.

## Menangani komplain

1. Buka menu **Complain** dan pilih komplain yang **open**.
2. Klik **Kirim Pesan** untuk membuka percakapan pelanggan langsung di **[Messages](/docs/whatsapp/inbox)** (deep-link otomatis ke kontak).
3. Selesaikan masalah dengan pelanggan.
4. Isi **catatan penyelesaian (resolution note)**, lalu **selesaikan** komplain.

## Setelah komplain diselesaikan

- Pelanggan menerima **pesan penyelesaian** otomatis via WhatsApp.
- **AI Agent diaktifkan kembali** untuk kontak tersebut — *hanya jika* tidak ada komplain lain yang masih terbuka untuk kontak yang sama.

## Tips

- Tanggapi komplain **secepat mungkin**; selama komplain terbuka, bot tidak membalas kontak itu.
- Catatan penyelesaian membantu Anda melacak pola masalah (mis. driver, kemasan, atau menu tertentu).
