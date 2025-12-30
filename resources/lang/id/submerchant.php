<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sub-Merchant Management Language Lines (Indonesian)
    |--------------------------------------------------------------------------
    |
    | Baris bahasa berikut digunakan untuk fitur manajemen sub-merchant
    | termasuk daftar, pendaftaran, manajemen saldo, transaksi,
    | penarikan, dan notifikasi.
    |
    */

    // Page Titles
    'dashboard_title' => 'Dashboard Sub-Merchant - QashierWise',
    'register_title' => 'Daftar sebagai Sub-Merchant - QashierWise',
    'balance_title' => 'Manajemen Saldo - QashierWise',
    'transactions_title' => 'Riwayat Transaksi - QashierWise',
    'qris_title' => 'Buat QRIS - QashierWise',
    'provider_settings_title' => 'Pengaturan Provider - QashierWise',
    'migrate_title' => 'Migrasi ke BYOK - QashierWise',

    // Dashboard
    'dashboard' => 'Dashboard Sub-Merchant',
    'welcome' => 'Selamat Datang di Sub-Merchant',
    'overview' => 'Ringkasan',
    'quick_actions' => 'Aksi Cepat',
    'recent_activity' => 'Aktivitas Terkini',

    // Registration
    'register_as_submerchant' => 'Daftar sebagai Sub-Merchant',
    'start_accepting_qris' => 'Mulai menerima pembayaran QRIS dari pelanggan Anda',
    'already_registered' => 'Sudah Terdaftar',
    'already_registered_message' => 'Anda sudah terdaftar sebagai sub-merchant.',
    'go_to_dashboard' => 'Ke Dashboard',
    'not_registered' => 'Belum Terdaftar',
    'not_registered_message' => 'Anda perlu mendaftar sebagai sub-merchant untuk membuat kode QRIS.',
    'register_now' => 'Daftar Sekarang',

    // Registration Form
    'business_name' => 'Nama Bisnis',
    'business_name_placeholder' => 'Masukkan nama bisnis Anda',
    'business_address' => 'Alamat Bisnis',
    'business_address_placeholder' => 'Masukkan alamat bisnis Anda',
    'business_phone' => 'Telepon Bisnis',
    'business_phone_placeholder' => 'Masukkan nomor telepon bisnis',
    'business_email' => 'Email Bisnis',
    'business_email_placeholder' => 'Masukkan email bisnis',
    'bank_name' => 'Nama Bank',
    'bank_name_placeholder' => 'Pilih bank Anda',
    'account_number' => 'Nomor Rekening',
    'account_number_placeholder' => 'Masukkan nomor rekening',
    'account_holder_name' => 'Nama Pemegang Rekening',
    'account_holder_name_placeholder' => 'Masukkan nama pemegang rekening',

    // Terms and Conditions
    'accept_terms' => 'Saya menerima syarat dan ketentuan',
    'terms_message' => 'Saya memahami dan menyetujui bahwa <strong>biaya platform 2,5%</strong> akan dipotong dari setiap transaksi yang berhasil.',
    'platform_fee_notice' => 'Biaya platform 2,5% dipotong dari setiap transaksi yang berhasil',

    // Registration Steps
    'registration_steps' => 'Cara kerjanya:',
    'step_register' => 'Daftar sebagai sub-merchant untuk mulai menerima pembayaran QRIS',
    'step_configure' => 'Konfigurasi kredensial provider pembayaran Anda di Pengaturan Provider',
    'step_generate' => 'Buat kode QRIS untuk pelanggan Anda',
    'step_fee' => 'Biaya platform 2,5% dipotong dari setiap transaksi yang berhasil',

    // Balance Management
    'balance' => 'Saldo',
    'balance_overview' => 'Ringkasan Saldo',
    'available_balance' => 'Saldo Tersedia',
    'pending_balance' => 'Saldo Tertunda',
    'total_balance' => 'Total Saldo',
    'total_earnings' => 'Total Pendapatan',
    'total_withdrawn' => 'Total Ditarik',
    'balance_history' => 'Riwayat Saldo',
    'balance_details' => 'Detail Saldo',

    // Withdrawals
    'withdraw' => 'Tarik',
    'withdraw_funds' => 'Tarik Dana',
    'withdrawal' => 'Penarikan',
    'withdrawal_request' => 'Permintaan Penarikan',
    'withdrawal_amount' => 'Jumlah Penarikan',
    'withdrawal_history' => 'Riwayat Penarikan',
    'request_withdrawal' => 'Ajukan Penarikan',
    'withdrawal_requested' => 'Penarikan berhasil diajukan',
    'withdrawal_approved' => 'Penarikan disetujui',
    'withdrawal_rejected' => 'Penarikan ditolak',
    'withdrawal_pending' => 'Penarikan menunggu',
    'withdrawal_processing' => 'Memproses penarikan',
    'withdrawal_completed' => 'Penarikan selesai',
    'withdrawal_failed' => 'Penarikan gagal',
    'minimum_withdrawal' => 'Jumlah penarikan minimum adalah :amount',
    'insufficient_balance' => 'Saldo tidak mencukupi',
    'withdrawal_fee' => 'Biaya Penarikan',
    'net_withdrawal' => 'Jumlah Penarikan Bersih',

    // Bank Account
    'bank_account' => 'Rekening Bank',
    'bank_account_info' => 'Informasi Rekening Bank',
    'bank_details' => 'Detail Bank',
    'account_holder' => 'Pemegang Rekening',
    'update_bank_account' => 'Perbarui Rekening Bank',

    // Transactions
    'transactions' => 'Transaksi',
    'transaction_history' => 'Riwayat Transaksi',
    'transaction_details' => 'Detail Transaksi',
    'recent_transactions' => 'Transaksi Terkini',
    'all_transactions' => 'Semua Transaksi',
    'no_transactions' => 'Belum ada transaksi',
    'no_transactions_message' => 'Buat QRIS pertama Anda untuk memulai',
    'transaction_id' => 'ID Transaksi',
    'order_id' => 'ID Pesanan',
    'reference_id' => 'ID Referensi',
    'amount' => 'Jumlah',
    'gross_amount' => 'Jumlah Kotor',
    'net_amount' => 'Jumlah Bersih',
    'platform_fee' => 'Biaya Platform',
    'status' => 'Status',
    'date' => 'Tanggal',
    'time' => 'Waktu',
    'created_at' => 'Dibuat',
    'paid_at' => 'Dibayar Pada',
    'expires_at' => 'Kedaluwarsa Pada',
    'provider' => 'Provider',

    // Transaction Status
    'status_pending' => 'Menunggu',
    'status_settlement' => 'Selesai',
    'status_success' => 'Berhasil',
    'status_paid' => 'Dibayar',
    'status_failed' => 'Gagal',
    'status_expired' => 'Kedaluwarsa',
    'status_cancelled' => 'Dibatalkan',
    'status_active' => 'Aktif',

    // Transaction Filters
    'filter_all' => 'Semua',
    'filter_today' => 'Hari Ini',
    'filter_week' => 'Minggu Ini',
    'filter_month' => 'Bulan Ini',
    'filter_pending' => 'Menunggu',
    'filter_success' => 'Berhasil',
    'filter_failed' => 'Gagal',

    // QRIS Generation
    'generate_qris' => 'Buat QRIS',
    'qris_generation' => 'Pembuatan QRIS',
    'qris_code' => 'Kode QRIS',
    'qris_history' => 'Riwayat QRIS',
    'new_qris' => 'QRIS Baru',
    'create_qris' => 'Buat QRIS',
    'qris_details' => 'Detail QRIS',
    'qris_generated' => 'QRIS berhasil dibuat',
    'qris_generation_failed' => 'Pembuatan QRIS gagal',
    'scan_to_pay' => 'Scan untuk Bayar',
    'valid_until' => 'Berlaku Hingga',
    'can_be_used' => 'Dapat digunakan',
    'cannot_be_used' => 'Tidak dapat digunakan',

    // QRIS Form
    'customer_name' => 'Nama Pelanggan',
    'customer_name_placeholder' => 'Masukkan nama pelanggan (opsional)',
    'customer_email' => 'Email Pelanggan',
    'customer_email_placeholder' => 'Masukkan email pelanggan (opsional)',
    'amount_placeholder' => 'Masukkan jumlah',
    'description' => 'Deskripsi',
    'description_placeholder' => 'Masukkan deskripsi (opsional)',
    'expiry_minutes' => 'Menit Kedaluwarsa',
    'expiry_minutes_placeholder' => 'Default: 60 menit',

    // Provider Settings
    'provider_settings' => 'Pengaturan Provider',
    'manage_providers' => 'Kelola Provider Pembayaran',
    'manage_provider_credentials' => 'Kelola kredensial provider pembayaran Anda',
    'active_provider' => 'Provider Aktif',
    'no_active_provider' => 'Tidak Ada Provider Aktif',
    'all_qris_use_provider' => 'Semua pembuatan QRIS akan menggunakan provider ini',
    'configure_provider_message' => 'Silakan konfigurasi dan aktifkan provider untuk membuat QRIS',
    'provider_configured' => 'Provider berhasil dikonfigurasi',
    'provider_activated' => 'Provider berhasil diaktifkan',
    'provider_validation_success' => 'Provider berhasil divalidasi',
    'provider_validation_failed' => 'Validasi provider gagal',

    // Migration
    'migrate_to_byok' => 'Migrasi ke BYOK',
    'byok_migration' => 'Migrasi Bring Your Own Key',
    'migration_description' => 'Migrasikan kredensial Anda yang ada ke sistem BYOK baru',
    'migration_status' => 'Status Migrasi',
    'migration_complete' => 'Migrasi berhasil diselesaikan',
    'migration_failed' => 'Migrasi gagal',
    'start_migration' => 'Mulai Migrasi',

    // Notifications
    'payment_received' => 'Pembayaran diterima',
    'payment_received_message' => 'Pembayaran sebesar :amount telah diterima untuk pesanan :order_id',
    'qris_expired' => 'QRIS kedaluwarsa',
    'qris_expired_message' => 'Kode QRIS untuk pesanan :order_id telah kedaluwarsa',
    'withdrawal_approved_message' => 'Permintaan penarikan Anda sebesar :amount telah disetujui',
    'withdrawal_rejected_message' => 'Permintaan penarikan Anda sebesar :amount telah ditolak',
    'withdrawal_completed_message' => 'Penarikan Anda sebesar :amount telah selesai',
    'balance_updated' => 'Saldo diperbarui',
    'balance_updated_message' => 'Saldo Anda telah diperbarui',

    // Actions
    'view_details' => 'Lihat Detail',
    'download_qr' => 'Unduh QR',
    'share_link' => 'Bagikan Link',
    'copy_link' => 'Salin Link',
    'cancel_transaction' => 'Batalkan Transaksi',
    'refresh' => 'Muat Ulang',
    'export' => 'Ekspor',
    'print' => 'Cetak',
    'configure' => 'Konfigurasi',
    'edit' => 'Edit',
    'save' => 'Simpan',
    'cancel' => 'Batal',
    'submit' => 'Kirim',
    'close' => 'Tutup',
    'back' => 'Kembali',
    'next' => 'Selanjutnya',
    'confirm' => 'Konfirmasi',
    'revalidate' => 'Validasi Ulang',
    'set_as_active' => 'Jadikan Aktif',
    'currently_active' => 'Sedang Aktif',

    // Status Messages
    'loading' => 'Memuat...',
    'saving' => 'Menyimpan...',
    'processing' => 'Memproses...',
    'generating' => 'Membuat...',
    'please_wait' => 'Mohon tunggu...',
    'success' => 'Berhasil',
    'error' => 'Kesalahan',
    'warning' => 'Peringatan',

    // Error Messages
    'error_occurred' => 'Terjadi kesalahan',
    'try_again' => 'Silakan coba lagi',
    'invalid_amount' => 'Jumlah tidak valid',
    'amount_required' => 'Jumlah wajib diisi',
    'amount_must_positive' => 'Jumlah harus positif',
    'merchant_not_active' => 'Sub-merchant tidak aktif',
    'merchant_not_found' => 'Sub-merchant tidak ditemukan',
    'transaction_not_found' => 'Transaksi tidak ditemukan',
    'only_pending_can_cancel' => 'Hanya transaksi yang menunggu yang dapat dibatalkan',
    'provider_not_configured' => 'Provider tidak dikonfigurasi',
    'no_active_provider_configured' => 'Tidak ada provider pembayaran aktif yang dikonfigurasi',
    'registration_failed' => 'Pendaftaran gagal',
    'validation_failed' => 'Validasi gagal',
    'missing_required_fields' => 'Field wajib tidak lengkap',

    // Success Messages
    'registration_success' => 'Pendaftaran berhasil',
    'update_success' => 'Berhasil diperbarui',
    'transaction_cancelled' => 'Transaksi berhasil dibatalkan',
    'link_copied' => 'Link berhasil disalin',
    'qr_downloaded' => 'Kode QR berhasil diunduh',

    // Empty States
    'no_data' => 'Tidak ada data',
    'no_balance_history' => 'Belum ada riwayat saldo',
    'no_withdrawals' => 'Belum ada permintaan penarikan',
    'no_providers' => 'Belum ada provider yang dikonfigurasi',

    // Common Labels
    'total' => 'Total',
    'subtotal' => 'Subtotal',
    'fee' => 'Biaya',
    'net' => 'Bersih',
    'gross' => 'Kotor',
    'currency' => 'Rp',
    'optional' => 'Opsional',
    'required' => 'Wajib',
    'yes' => 'Ya',
    'no' => 'Tidak',

    // Time
    'minutes' => 'menit',
    'hours' => 'jam',
    'days' => 'hari',
    'ago' => 'yang lalu',
    'remaining' => 'tersisa',
    'never' => 'Tidak Pernah',

    // Provider Settings Page
    'coming_soon' => 'Segera Hadir',
    'integration_coming_soon' => 'Integrasi segera hadir',
    'not_configured_yet' => 'Belum dikonfigurasi',
    'last_validated' => 'Terakhir Divalidasi',
    'api_key' => 'API Key',
    'callback_token' => 'Callback Token',
    'server_key' => 'Server Key',
    'client_key' => 'Client Key',
    'enter_api_key' => 'Masukkan API Key',
    'enter_callback_token' => 'Masukkan Callback Token',
    'enter_server_key' => 'Masukkan Server Key',
    'enter_client_key' => 'Masukkan Client Key',
    'save_validate' => 'Simpan & Validasi',
    'provider_save_success' => 'Provider berhasil dikonfigurasi dan divalidasi!',
    'provider_save_failed' => 'Gagal menyimpan kredensial provider',
    'provider_not_found' => 'Provider tidak ditemukan',
    'set_active_confirm' => 'Jadikan :provider sebagai provider aktif? Semua pembuatan QRIS akan menggunakan provider ini.',
    'set_active_failed' => 'Gagal mengatur provider aktif',
    'validation_error' => 'Terjadi kesalahan saat validasi',
    'status_valid' => 'Valid',
    'status_invalid' => 'Tidak Valid',
    'status_pending_validation' => 'Menunggu',

];
