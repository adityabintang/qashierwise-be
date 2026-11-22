# Saldo & Penarikan

Dana dari pembayaran QRIS masuk ke **saldo sub-merchant** Anda dan bisa ditarik ke rekening bank. Buka **Sub-Merchant → Balance** dan **Sub-Merchant → Withdrawals**.

## Memahami saldo

Pada **Balance Overview** terdapat dua angka:

- **Available Balance (Saldo Tersedia)** — dana yang **siap ditarik** ke rekening bank.
- **Pending Balance (Saldo Tertahan)** — dana yang masih dalam proses *settlement* dan belum bisa ditarik.

Setiap transaksi sudah dipotong **fee 2,5%** sebelum menambah saldo.

## Mengatur rekening bank

Sebelum menarik dana, atur rekening tujuan di **Sub-Merchant → Bank Account**:

1. Klik **Tambah Rekening Bank**.
2. Isi:
   - **Pilih Bank** — dari daftar bank.
   - **Nomor Rekening**.
   - **Nama Pemilik Rekening** — harus sesuai data bank.
3. Simpan. Anda bisa **Ubah Rekening** kapan saja.

> Jika rekening belum diatur, halaman penarikan menampilkan **"Rekening Bank Belum Dikonfigurasi"**.

## Melakukan penarikan (withdrawal)

1. Buka **Sub-Merchant → Withdrawals**.
2. Klik **Request Withdrawal**.
3. Pilih **Rekening Tujuan** dan masukkan **nominal**.
4. Kirim permintaan.

**Ketentuan:**
- **Minimum penarikan: Rp 10.000.**
- Hanya **Available Balance** yang bisa ditarik.
- Status penarikan (pending → diproses → selesai) tampil di daftar withdrawals.

## Tips

- Pastikan **nama pemilik rekening** persis sama dengan data bank agar transfer tidak gagal.
- Lakukan rekonsiliasi rutin antara **[Transaksi](/docs/pos/transaksi)**, **QRIS History**, dan mutasi rekening Anda.
