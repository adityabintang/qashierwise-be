<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment and QRIS Language Lines (Indonesian)
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut digunakan untuk fitur terkait pembayaran
    | termasuk pembuatan QRIS, status pembayaran, transaksi, pengaturan provider,
    | dan notifikasi pembayaran.
    |
    */

    // Page Titles
    'qris_payment_title' => 'Pembayaran QRIS - :order_id',
    'provider_settings_title' => 'Pengaturan Provider - QashierWise',
    'generate_qris_title' => 'Buat QRIS - QashierWise',
    'transactions_title' => 'Transaksi - QashierWise',
    'balance_title' => 'Saldo - QashierWise',

    // QRIS Payment Page
    'qris_payment' => 'Pembayaran QRIS',
    'merchant' => 'Merchant',
    'payment_amount' => 'Jumlah Pembayaran',
    'total_payment' => 'Total Pembayaran',
    'valid_until' => 'Berlaku Hingga',
    'order_id' => 'ID Pesanan',
    'powered_by' => 'Didukung oleh QashierWise',

    // Payment Status
    'payment_successful' => 'Pembayaran Berhasil!',
    'payment_expired' => 'Pembayaran Kedaluwarsa',
    'payment_cancelled' => 'Pembayaran Dibatalkan',
    'payment_pending' => 'Pembayaran Menunggu',
    'thank_you_payment' => 'Terima kasih atas pembayaran Anda',
    'payment_time_expired' => 'Waktu pembayaran telah habis',
    'transaction_cancelled' => 'Transaksi ini telah dibatalkan',
    'qr_code_unavailable' => 'Kode QR tidak tersedia',
    'expired' => 'Kedaluwarsa',

    // Payment Instructions
    'payment_instructions' => 'Cara Pembayaran:',
    'instruction_1' => 'Buka aplikasi e-wallet atau mobile banking',
    'instruction_2' => 'Pilih menu Scan QR atau QRIS',
    'instruction_3' => 'Scan kode QR di atas',
    'instruction_4' => 'Konfirmasi pembayaran',

    // Transaction Status
    'status_pending' => 'Menunggu',
    'status_settlement' => 'Selesai',
    'status_success' => 'Berhasil',
    'status_paid' => 'Dibayar',
    'status_failed' => 'Gagal',
    'status_expired' => 'Kedaluwarsa',
    'status_cancelled' => 'Dibatalkan',
    'status_active' => 'Aktif',

    // Provider Settings
    'provider_settings' => 'Pengaturan Provider',
    'manage_provider_credentials' => 'Kelola kredensial provider pembayaran Anda',
    'active_provider' => 'Provider Aktif',
    'no_active_provider' => 'Tidak Ada Provider Aktif',
    'all_qris_use_provider' => 'Semua pembuatan QRIS akan menggunakan provider ini',
    'configure_provider_message' => 'Silakan konfigurasi dan aktifkan provider untuk membuat QRIS',
    'provider_cards' => 'Kartu Provider',

    // Provider Names
    'provider_doku' => 'Doku',
    'provider_xendit' => 'Xendit',
    'provider_midtrans' => 'Midtrans',
    'provider_duitku' => 'Duitku',

    // Provider API Types
    'snap_api' => 'Integrasi SNAP API',
    'qr_codes_api' => 'QR Codes API',
    'qris_charge_api' => 'QRIS Charge API',
    'invoice_api' => 'Invoice API',

    // Provider Status
    'coming_soon' => 'Segera Hadir',
    'integration_coming_soon' => 'Integrasi segera hadir',
    'not_configured' => 'Belum dikonfigurasi',
    'last_validated' => 'Terakhir Divalidasi',
    'valid' => 'Valid',
    'invalid' => 'Tidak Valid',
    'currently_active' => 'Sedang Aktif',

    // Provider Actions
    'configure_provider' => 'Konfigurasi :provider',
    'revalidate' => 'Validasi Ulang',
    'set_as_active' => 'Jadikan Aktif',
    'edit' => 'Edit',

    // Provider Configuration Modal
    'configure' => 'Konfigurasi',
    'api_key' => 'API Key',
    'callback_token' => 'Callback Token',
    'server_key' => 'Server Key',
    'client_key' => 'Client Key',
    'enter_api_key' => 'Masukkan API Key :provider',
    'enter_callback_token' => 'Masukkan Callback Token :provider',
    'enter_server_key' => 'Masukkan Server Key :provider',
    'enter_client_key' => 'Masukkan Client Key :provider',
    'required_field' => 'Wajib diisi',
    'save_and_validate' => 'Simpan & Validasi',
    'saving' => 'Menyimpan...',

    // Provider Messages
    'provider_configured_success' => 'Provider berhasil dikonfigurasi dan divalidasi!',
    'provider_save_failed' => 'Gagal menyimpan kredensial provider',
    'provider_validation_failed' => 'Validasi gagal',
    'provider_not_found' => 'Provider tidak ditemukan',
    'provider_not_supported' => 'Provider :provider tidak didukung',
    'no_active_provider_configured' => 'Tidak ada provider pembayaran aktif yang dikonfigurasi. Silakan konfigurasi provider di pengaturan.',
    'provider_not_configured' => 'Provider :provider tidak dikonfigurasi dengan benar. Silakan validasi kredensial Anda.',
    'provider_invalid' => 'Provider tidak dikonfigurasi dengan benar. Silakan validasi kredensial Anda di pengaturan.',

    // QRIS Generation
    'generate_qris' => 'Buat QRIS',
    'qris_code' => 'Kode QRIS',
    'qris_generated' => 'QRIS berhasil dibuat',
    'qris_generation_failed' => 'Pembuatan QRIS gagal',
    'amount' => 'Jumlah',
    'description' => 'Deskripsi',
    'customer_name' => 'Nama Pelanggan',
    'customer_email' => 'Email Pelanggan',
    'expiry_minutes' => 'Menit Kedaluwarsa',
    'generate' => 'Buat',
    'generating' => 'Membuat...',

    // Transaction List
    'transactions' => 'Transaksi',
    'transaction_history' => 'Riwayat Transaksi',
    'transaction_id' => 'ID Transaksi',
    'provider' => 'Provider',
    'date' => 'Tanggal',
    'time' => 'Waktu',
    'net_amount' => 'Jumlah Bersih',
    'platform_fee' => 'Biaya Platform',
    'reference_id' => 'ID Referensi',
    'paid_at' => 'Dibayar Pada',
    'expires_at' => 'Kedaluwarsa Pada',
    'no_transactions' => 'Belum ada transaksi',
    'view_details' => 'Lihat Detail',
    'download_qr' => 'Unduh QR',
    'share_link' => 'Bagikan Link',
    'cancel_transaction' => 'Batalkan Transaksi',

    // Balance
    'balance' => 'Saldo',
    'available_balance' => 'Saldo Tersedia',
    'pending_balance' => 'Saldo Tertunda',
    'total_earnings' => 'Total Pendapatan',
    'withdraw' => 'Tarik',
    'withdrawal_request' => 'Permintaan Penarikan',
    'withdrawal_amount' => 'Jumlah Penarikan',
    'bank_account' => 'Rekening Bank',
    'account_number' => 'Nomor Rekening',
    'account_holder' => 'Pemegang Rekening',
    'request_withdrawal' => 'Ajukan Penarikan',

    // Notifications
    'payment_received' => 'Pembayaran diterima',
    'payment_received_message' => 'Pembayaran sebesar :amount telah diterima',
    'qris_expired' => 'QRIS kedaluwarsa',
    'qris_expired_message' => 'Kode QRIS untuk pesanan :order_id telah kedaluwarsa',
    'withdrawal_approved' => 'Penarikan disetujui',
    'withdrawal_approved_message' => 'Permintaan penarikan Anda sebesar :amount telah disetujui',
    'withdrawal_rejected' => 'Penarikan ditolak',
    'withdrawal_rejected_message' => 'Permintaan penarikan Anda sebesar :amount telah ditolak',

    // Error Messages
    'merchant_not_active' => 'Sub-merchant tidak aktif',
    'amount_must_positive' => 'Jumlah harus positif',
    'merchant_must_have_user' => 'Sub-merchant harus terkait dengan pengguna',
    'transaction_not_found' => 'Transaksi tidak ditemukan',
    'only_pending_can_cancel' => 'Hanya transaksi yang menunggu yang dapat dibatalkan',
    'invalid_webhook_signature' => 'Tanda tangan webhook tidak valid',
    'webhook_processing_failed' => 'Gagal memproses webhook',
    'transaction_update_failed' => 'Gagal memperbarui transaksi',

    // Success Messages
    'transaction_cancelled' => 'Transaksi berhasil dibatalkan',
    'withdrawal_requested' => 'Penarikan berhasil diajukan',
    'provider_activated' => 'Provider berhasil diaktifkan',
    'credentials_saved' => 'Kredensial berhasil disimpan',
    'credentials_validated' => 'Kredensial berhasil divalidasi',

    // Validation Messages
    'invalid_amount' => 'Jumlah tidak valid',
    'invalid_provider' => 'Provider tidak valid',
    'invalid_credentials' => 'Kredensial tidak valid',
    'missing_required_fields' => 'Field wajib tidak lengkap',

    // Common Actions
    'view' => 'Lihat',
    'cancel' => 'Batal',
    'confirm' => 'Konfirmasi',
    'close' => 'Tutup',
    'save' => 'Simpan',
    'back' => 'Kembali',
    'refresh' => 'Muat Ulang',
    'retry' => 'Coba Lagi',

    // Common Labels
    'loading' => 'Memuat...',
    'processing' => 'Memproses...',
    'please_wait' => 'Mohon tunggu...',
    'no_data' => 'Tidak ada data',
    'error_occurred' => 'Terjadi kesalahan',
    'try_again' => 'Silakan coba lagi',

    // Currency
    'currency_symbol' => 'Rp',
    'currency_format' => 'Rp :amount',

    // Time
    'minutes' => 'menit',
    'hours' => 'jam',
    'days' => 'hari',
    'ago' => 'yang lalu',
    'remaining' => 'tersisa',

];
