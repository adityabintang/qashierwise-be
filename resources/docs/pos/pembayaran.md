# Pembayaran (POS)

Menu **Payment** mengatur bagaimana pesanan dibayar. QashierWise mendukung pembayaran **QRIS online** maupun **bayar di tempat**. Buka **POS → Payment**.

## Metode pembayaran

- **QRIS** — pelanggan membayar dengan memindai QR. Status pembayaran terdeteksi otomatis lewat webhook penyedia pembayaran (Xendit).
- **Bayar di tempat (pickup)** — pesanan dikonfirmasi manual oleh kasir; cocok untuk paket Basic.
- **Tunai/lainnya** — dicatat manual sesuai kebutuhan outlet.

## Alur pembayaran QRIS

1. Pada sebuah pesanan, pilih metode **QRIS** dan **buka link pembayaran**.
2. Pelanggan diarahkan ke **halaman pembayaran QRIS** (`/pay/qris/{orderId}`) — bisa juga dikirim lewat WhatsApp.
3. Pelanggan memindai & membayar.
4. Status pesanan otomatis menjadi **paid** saat pembayaran dikonfirmasi penyedia.

> Untuk menerima pembayaran QRIS, akun Anda perlu menjadi **sub-merchant** yang aktif. Lihat **[Pembayaran → Sub-Merchant](/docs/pembayaran/sub-merchant)** dan **[QRIS](/docs/pembayaran/qris)**.

## Catatan

- Pembayaran QRIS **unlimited** tersedia pada paket **Pro**.
- Setiap pembayaran sukses tercatat sebagai **[Transaksi](/docs/pos/transaksi)** dan masuk **[Laporan](/docs/pos/laporan)**.
- Dana masuk akan tampil di **[Saldo](/docs/pembayaran/saldo-penarikan)** dan bisa ditarik ke rekening bank.
