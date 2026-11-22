# Webhooks (Developer)

Bagi Anda yang ingin **mengintegrasikan QashierWise dengan sistem lain**, tersedia **Webhook** yang mengirim notifikasi event (mis. pesanan baru, pembayaran berhasil) ke URL milik Anda secara real-time. Tersedia pada paket **Pro**.

Buka **Dashboard → Developer → Webhooks**.

## Membuat webhook

1. Klik **Tambah Webhook** (jika kosong, tampil **"Belum ada webhook"**).
2. Isi:
   - **URL tujuan** — endpoint HTTPS milik Anda yang akan menerima data.
   - **Event** — jenis kejadian yang ingin dikirim (mis. order/payment).
3. Simpan.

## Menyimpan secret

Saat webhook dibuat, sistem menampilkan **signing secret** sekali saja, dengan peringatan **"Simpan Secret Ini!"**.

- **Salin dan simpan** secret di tempat aman — ini tidak ditampilkan lagi.
- Gunakan secret untuk **memverifikasi tanda tangan (signature)** setiap payload yang masuk, sehingga Anda yakin data benar-benar berasal dari QashierWise.

## Memverifikasi payload

Setiap request webhook menyertakan **signature** pada header. Di sisi server Anda:

1. Hitung HMAC dari **body mentah** request memakai **secret** Anda.
2. Bandingkan dengan signature pada header.
3. Proses event hanya jika cocok.

## Mengelola webhook

- **Lihat** daftar webhook beserta status & URL-nya.
- **Hapus** webhook lewat tombol hapus (konfirmasi pada dialog **"Hapus Webhook?"**).

## Tips

- Selalu balas dengan **HTTP 2xx** secepatnya; proses berat sebaiknya dijalankan asinkron.
- Siapkan **idempotensi** karena sebuah event bisa terkirim lebih dari sekali.
- Gunakan endpoint **HTTPS** yang valid agar pengiriman tidak gagal.
