# QRIS

Setelah terdaftar sebagai **[sub-merchant](/docs/pembayaran/sub-merchant)**, Anda bisa membuat kode **QRIS** untuk menerima pembayaran dari pelanggan. Buka **Sub-Merchant → QRIS**.

## Membuat QRIS baru

1. Klik **Generate New QRIS**.
2. Isi detail:
   - **Customer Name** — nama pelanggan (opsional, untuk pencatatan).
   - **Nominal** — jumlah yang harus dibayar.
3. Sistem menampilkan **Generated QRIS** beserta:
   - **Kode QR** untuk dipindai pelanggan.
   - **Status** pembayaran (pending → paid).
   - **Platform Fee (2.5%)** yang dipotong.

Pelanggan memindai QR dengan aplikasi e-wallet/m-banking apa pun yang mendukung QRIS. Saat pembayaran berhasil, status otomatis berubah menjadi **paid**.

## Riwayat QRIS

Bagian **QRIS History** menampilkan seluruh kode yang pernah dibuat lengkap dengan nominal, status, dan waktunya — berguna untuk rekonsiliasi.

## QRIS untuk pesanan POS

Pembayaran pesanan dari **[POS → Orders](/docs/pos/pesanan)** juga memakai QRIS lewat **halaman pembayaran** (`/pay/qris/{orderId}`) yang bisa dikirim ke pelanggan via WhatsApp. Lihat **[Pembayaran POS](/docs/pos/pembayaran)**.

## Belum terdaftar?

Jika belum menjadi sub-merchant, halaman menampilkan **"Not Registered"**. Daftar dulu lewat **[Sub-Merchant → Register](/docs/pembayaran/sub-merchant)**.
