<?php

return [
    // Judul halaman dan deskripsi
    'title' => 'AI Agent',
    'description' => 'Konfigurasi Asisten AI WhatsApp Anda',
    'configure_assistant' => 'Konfigurasi Asisten AI WhatsApp Anda',

    // Status loading
    'loading' => 'Memuat konfigurasi AI Agent...',

    // Persyaratan akun WhatsApp
    'whatsapp_required' => [
        'title' => 'Akun WhatsApp Diperlukan',
        'message' => 'Silakan hubungkan akun WhatsApp Business Anda terlebih dahulu sebelum mengkonfigurasi AI Agent.',
        'button' => 'Hubungkan Akun WhatsApp',
    ],

    // Kartu status
    'status' => [
        'title' => 'Status AI Agent',
        'active' => 'Aktif',
        'inactive' => 'Tidak Aktif',
        'enable' => 'Aktifkan AI Agent',
        'save_to_activate' => 'Simpan konfigurasi untuk mengaktifkan AI Agent',
    ],

    // Bagian identitas bot
    'identity' => [
        'title' => 'Identitas Bot',
        'bot_name' => 'Nama Bot',
        'bot_name_placeholder' => 'Contoh: Assistant Resto Saya',
        'bot_name_help' => 'Nama yang akan digunakan AI untuk memperkenalkan diri',
        'system_prompt' => 'System Prompt',
        'system_prompt_placeholder' => 'Contoh: Kamu adalah asisten virtual untuk restaurant seafood. Tugas utamamu adalah membantu pelanggan dengan ramah dan profesional. Jawab pertanyaan tentang menu, harga, dan jam operasional. Gunakan bahasa Indonesia yang sopan.',
        'system_prompt_help' => 'Instruksi dasar untuk AI Agent. Jelaskan bagaimana bot harus berperilaku, fungsinya, dan gaya bahasa yang digunakan.',
    ],

    // Bagian informasi bisnis
    'business_info' => [
        'title' => 'Informasi Bisnis',
        'description' => 'Informasi ini akan disertakan dalam konteks AI agar bisa menjawab pertanyaan pelanggan',
        'operating_hours' => 'Jam Operasional',
        'operating_hours_placeholder' => 'Contoh: Senin-Jumat 08:00-22:00',
        'phone' => 'Nomor Telepon',
        'phone_placeholder' => 'Contoh: 021-1234567',
        'address' => 'Alamat',
        'address_placeholder' => 'Contoh: Jl. Sudirman No. 123, Jakarta Pusat',
        'business_description' => 'Deskripsi Bisnis',
        'business_description_placeholder' => 'Contoh: Restaurant seafood premium dengan menu andalan kepiting saus padang dan udang bakar madu. Menyediakan private room untuk acara keluarga.',
    ],

    // Bagian fitur order
    'order' => [
        'title' => 'Fitur Order',
        'description' => 'Aktifkan fitur ini agar AI Agent dapat membantu pelanggan melakukan pemesanan',
        'select_store' => 'Pilih Store',
        'select_store_placeholder' => '-- Pilih Store --',
        'select_store_help' => 'Order dari AI Agent akan masuk ke store ini',
        'no_stores' => 'Belum ada store.',
        'create_store_first' => 'Buat store terlebih dahulu',
        'enable_order' => 'Aktifkan Order via Chat',
        'order_description' => 'Pelanggan bisa melihat produk, menambah ke keranjang, dan order langsung via chat',
        'select_store_first' => 'Pilih store terlebih dahulu untuk mengaktifkan fitur ini',
        'order_active' => 'Fitur order aktif! AI Agent dapat:',
        'capabilities' => [
            'search_products' => 'Mencari dan menampilkan produk',
            'add_to_cart' => 'Menambahkan produk ke keranjang',
            'show_summary' => 'Menampilkan ringkasan pesanan',
            'create_order' => 'Membuat order setelah konfirmasi',
        ],
    ],

    // Bagian pembayaran QRIS
    'qris' => [
        'title' => 'Pembayaran QRIS',
        'description' => 'Aktifkan pembayaran QRIS otomatis saat pelanggan konfirmasi pembelian',
        'enable_qris' => 'Aktifkan Pembayaran QRIS',
        'qris_enabled_description' => 'QRIS akan otomatis di-generate saat pelanggan konfirmasi pembelian',
        'qris_disabled_description' => 'Pembayaran manual - pelanggan akan diarahkan ke kasir',
        'qris_active' => 'Pembayaran QRIS aktif! Saat pelanggan konfirmasi pembelian:',
        'qris_capabilities' => [
            'auto_generate' => 'QRIS akan otomatis di-generate dengan total harga pesanan',
            'scan_to_pay' => 'Pelanggan dapat scan QR code untuk bayar',
            'confirmation' => 'Setelah pembayaran sukses, AI akan mengirim konfirmasi dengan Order ID, nama produk, dan total harga',
        ],
        'provider_settings_note' => 'Pastikan payment provider sudah dikonfigurasi di',
        'provider_settings_link' => 'Pengaturan Provider',
        'manual_mode' => 'Mode Manual - Saat pelanggan konfirmasi pembelian:',
        'manual_capabilities' => [
            'send_summary' => 'AI akan mengirim ringkasan pesanan (Order ID, nama produk, total harga)',
            'pending_status' => 'Status pembayaran: Pending',
            'show_to_cashier' => 'Pelanggan diminta menunjukkan pesan tersebut ke kasir untuk diproses',
        ],
    ],

    // Tombol aksi
    'actions' => [
        'test' => 'Test AI Agent',
        'save' => 'Simpan Konfigurasi',
        'saving' => 'Menyimpan...',
        'last_saved' => 'Terakhir disimpan:',
        'not_saved' => 'Konfigurasi belum pernah disimpan',
        'complete_required' => 'Lengkapi Bot Name dan System Prompt untuk menyimpan konfigurasi',
        'enable_to_test' => 'Aktifkan AI Agent dan simpan konfigurasi untuk melakukan test',
    ],

    // Modal test
    'test_modal' => [
        'title' => 'Test AI Agent',
        'description' => 'Kirim pesan test untuk melihat bagaimana AI Agent merespons',
        'message_placeholder' => 'Ketik pesan test Anda...',
        'send' => 'Kirim',
        'sending' => 'Mengirim...',
        'conversation_history' => 'Riwayat Percakapan',
        'reset' => 'Reset Percakapan',
        'close' => 'Tutup',
        'you' => 'Anda',
        'ai' => 'AI',
    ],

    // Riwayat percakapan
    'conversation' => [
        'title' => 'Riwayat Percakapan',
        'no_conversations' => 'Belum ada percakapan',
        'view_details' => 'Lihat Detail',
        'reset_confirm' => 'Apakah Anda yakin ingin mereset percakapan ini?',
        'reset_success' => 'Percakapan berhasil direset',
        'reset_error' => 'Gagal mereset percakapan',
    ],

    // Pesan respons API
    'messages' => [
        'not_connected' => 'Akun WhatsApp tidak terhubung',
        'not_configured' => 'AI Agent belum dikonfigurasi',
        'config_retrieved' => 'Konfigurasi AI Agent berhasil diambil',
        'config_saved' => 'Konfigurasi AI Agent berhasil disimpan',
        'config_save_failed' => 'Gagal menyimpan konfigurasi AI Agent',
        'activated' => 'AI Agent diaktifkan',
        'deactivated' => 'AI Agent dinonaktifkan',
        'toggle_failed' => 'Gagal mengubah status AI Agent',
        'order_enabled' => 'Fitur order diaktifkan',
        'order_disabled' => 'Fitur order dinonaktifkan',
        'order_toggle_failed' => 'Gagal mengubah fitur order',
        'set_store_first' => 'Tidak dapat mengaktifkan fitur order. Silakan atur store default terlebih dahulu.',
        'qris_enabled' => 'Fitur QRIS diaktifkan',
        'qris_disabled' => 'Fitur QRIS dinonaktifkan',
        'qris_toggle_failed' => 'Gagal mengubah fitur QRIS',
        'qris_not_ready' => 'Tidak dapat mengaktifkan fitur QRIS',
        'test_processed' => 'Pesan test berhasil diproses',
        'test_failed' => 'Gagal memproses pesan test',
        'invalid_store' => 'Store tidak valid. Store tidak ditemukan atau bukan milik Anda.',
        'connect_whatsapp_first' => 'Akun WhatsApp tidak terhubung. Silakan hubungkan akun WhatsApp Business Anda terlebih dahulu.',
    ],

    // Pesan error
    'errors' => [
        'general' => 'Terjadi kesalahan. Silakan coba lagi.',
        'network' => 'Kesalahan jaringan. Silakan periksa koneksi Anda.',
        'timeout' => 'Waktu permintaan habis. Silakan coba lagi.',
        'invalid_response' => 'Respons tidak valid dari server.',
        'missing_config' => 'Konfigurasi tidak lengkap.',
        'tool_not_found' => 'Fungsi tidak dikenali.',
        'invalid_parameters' => 'Parameter tidak valid.',
    ],

    // Pengaturan
    'settings' => [
        'title' => 'Pengaturan',
        'advanced' => 'Pengaturan Lanjutan',
        'temperature' => 'Temperature',
        'temperature_help' => 'Mengontrol keacakan dalam respons (0-1)',
        'max_tokens' => 'Max Tokens',
        'max_tokens_help' => 'Panjang maksimum respons',
    ],

    // Validasi
    'validation' => [
        'bot_name_required' => 'Nama bot wajib diisi',
        'system_prompt_required' => 'System prompt wajib diisi',
        'store_required' => 'Store wajib dipilih ketika fitur order diaktifkan',
        'message_required' => 'Pesan wajib diisi',
    ],
];
