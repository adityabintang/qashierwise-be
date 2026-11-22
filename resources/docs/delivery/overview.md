# Delivery — Pengenalan

Modul **Delivery** mengatur pesanan antar: ongkir, penugasan driver, bukti pengiriman (foto + lokasi), serta penanganan komplain pelanggan. Tersedia pada paket **Pro**.

Buka menu **Delivery** di dashboard. Sub-halaman:

| Halaman | Fungsi |
| --- | --- |
| **[Konfigurasi](/docs/delivery/konfigurasi)** | Aktifkan delivery, atur ongkir, syarat foto/lokasi, & direktori driver. |
| **[Komplain](/docs/delivery/komplain)** | Tangani komplain pelanggan atas pesanan. |

## Alur pengiriman

1. Pesanan delivery dibuat (dari **[Orders](/docs/pos/pesanan)** atau via WhatsApp) dan dibayar (QRIS).
2. Merchant **mengonfirmasi** pesanan dan **menugaskan driver**.
3. Driver membuka **halaman bukti pengiriman** lewat tautan khusus (`/delivery/{token}`) — tanpa perlu login.
4. Driver mengunggah **foto bukti** dan/atau **lokasi GPS** sesuai syarat yang diatur.
5. Pelanggan menerima notifikasi dan tombol konfirmasi **"barang diterima?"** (TERIMA / KOMPLAIN).
6. Status pengiriman diperbarui: *out_for_delivery → delivered* atau *complaint*.

## Status fulfillment

Pengiriman punya status tersendiri (terpisah dari status pembayaran):
`awaiting_confirmation → confirmed / out_for_delivery → delivered / complaint`.

## Saluran pesan

- Pesan ke **driver** dan **merchant** dikirim dari nomor admin QashierWise.
- Pesan ke **pelanggan** dikirim dari **nomor WhatsApp resmi Anda**; bila percakapan sudah lewat 24 jam, sistem otomatis beralih ke pengiriman teks cadangan.

Lanjut: **[Konfigurasi Delivery](/docs/delivery/konfigurasi)**.
