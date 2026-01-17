<?php

return [
    // Meta dan SEO
    'meta_title' => 'QashierWise - AI Chatbot WhatsApp untuk Restoran | Reservasi & Order Otomatis',
    'meta_description' => 'Platform AI Chatbot WhatsApp untuk restoran dengan integrasi QRIS. Kelola reservasi, pesanan, delivery, dan pembayaran dalam satu dashboard. Coba gratis 14 hari!',
    'meta_keywords' => 'chatbot whatsapp restoran, AI chatbot restoran, reservasi restoran otomatis, order whatsapp, QRIS restoran, POS restoran, manajemen restoran, WhatsApp Business API restoran',

    // Navigasi
    'nav' => [
        'how_it_works' => 'Cara Kerja',
        'features' => 'Fitur',
        'pricing' => 'Harga',
        'about' => 'Tentang Kami',
        'faq' => 'FAQ',
        'dashboard' => 'Dashboard',
        'view_demo' => 'Lihat Demo',
        'try_free' => 'Coba Gratis 14 Hari',
    ],

    // Bagian Hero
    'hero' => [
        'badge' => 'Didukung AI + Terintegrasi QRIS',
        'subtitle' => 'Chatbot untuk WhatsApp + Sistem QRIS',
        'title' => 'Respon lebih cepat, jual lebih',
        'title_highlight' => 'banyak',
        'description' => 'QashierWise menghadirkan Chatbot WhatsApp yang restoran-first dengan satu layar admin: Inbox, Pesanan, Reservasi, Menu, dan CRM.',
        'cta_primary' => 'Coba Gratis 14 Hari',
        'cta_secondary' => 'Lihat Demo',
        'benefits' => [
            'reservations_orders' => 'Reservasi & Order via WhatsApp tanpa ribet',
            'manage_orders' => 'Kelola pesanan + laporan dengan mudah',
            'no_workflow_change' => 'Tanpa perubahan workflow (bayar di tempat)',
            'auto_reports' => 'Laporan Order & penjualan otomatis',
        ],
        'floating_messages' => 'Pesan Masuk',
        'floating_messages_count' => '+128 hari ini',
        'floating_sales' => 'Penjualan',
        'floating_sales_trend' => '↑ 24% bulan ini',
    ],

    // Bagian Cara Kerja
    'how_it_works' => [
        'title' => 'Cara kerja otomatis dengan AI + QRIS',
        'subtitle' => 'Semuanya untuk bisnis restoran: cepat, ringkas, dan akurat.',
        'steps' => [
            [
                'title' => 'Terima chat di WhatsApp',
                'description' => 'Balas manual atau otomatis, sesuaikan auto-reply, jam operasional, dan menu.',
            ],
            [
                'title' => 'AI merespons otomatis',
                'description' => 'Terkirim konfirmasi & menu, dan pelanggan terkelola dengan baik.',
            ],
            [
                'title' => 'Bayar dengan QRIS',
                'description' => 'Kirim link QRIS statis. Semua disimpan, status berubah otomatis.',
            ],
            [
                'title' => 'Karyawan pantau di Console',
                'description' => 'Lihat inbox pesan pesanan, dan kelola semuanya dalam satu layar.',
            ],
        ],
    ],

    // Bagian Fitur
    'features' => [
        'title' => 'Fitur utama yang restoran butuhkan',
        'subtitle' => 'Semua alat & aplikasi dibuat untuk memaksimalkan pemasukan restoran',
        'list' => [
            [
                'title' => 'AI Chatbot WhatsApp',
                'description' => 'Balas cerdas dengan model AI, terima order, Talk to Staff, handover ke live agent langsung.',
            ],
            [
                'title' => 'Console Satu Layar',
                'description' => 'Inbox pesan, pesanan, kelola reservasi, dan semuanya dalam satu dashboard.',
            ],
            [
                'title' => 'Order Delivery & Pickup',
                'description' => 'Penjadwalan, order & delivery bisa terhubung, pembayaran QRIS terintegrasi.',
            ],
            [
                'title' => 'Reservasi Pintar',
                'description' => 'Slot ketersediaan, jam buka, kapasitas, dan notifikasi staff. Pengingat T-24h & T+7hr.',
            ],
            [
                'title' => 'Pembayaran QRIS',
                'description' => 'Kirim invoice QRIS atau berikan QR statis via chat, semua tercatat otomatis.',
            ],
            [
                'title' => 'Laporan & CRM',
                'description' => 'CRM, repeat rate, tag pelanggan, dan export CSV (Pro).',
            ],
        ],
    ],

    // Bagian Pratinjau Aplikasi
    'app_preview' => [
        'title' => 'Satu dashboard untuk semua',
        'subtitle' => 'Kelola chat, pesanan, reservasi, dan laporan dalam satu tampilan yang simpel',
        'sidebar' => [
            'inbox' => 'Inbox',
            'orders' => 'Orders',
            'reservations' => 'Reservations',
        ],
        'recent_conversations' => 'Recent Conversations',
        'new_badge' => ':count new',
        'time_ago' => ':time ago',
    ],

    // Bagian Integrasi
    'integrations' => [
        'title' => 'Integrasi yang didukung',
        'subtitle' => 'Sambungkan QashierWise untuk bisnis Anda—tanpa ribet.',
        'list' => [
            'whatsapp_business' => 'WhatsApp Business API',
            'ai_llm' => 'AI LLM (OpenAI/Claude)',
            'qris_midtrans' => 'QRIS Midtrans',
            'qris_xendit' => 'QRIS Xendit',
            'qris_okeoce' => 'QRIS OkeOce',
        ],
    ],

    // Bagian Harga
    'pricing' => [
        'title' => 'Harga sederhana, tumbuh bersama Anda',
        'subtitle' => 'Mulai gratis - upgrade kapan saja.',
        'error_message' => 'Gagal membuat sesi checkout. Silakan coba lagi.',
        'current_plan_badge' => 'Paket Saat Ini',
        'popular_badge' => 'POPULER',
        'per_month' => '/bln',
        'start_free' => 'Mulai Gratis',
        'select_plan' => 'Pilih :plan',
        'active_plan' => 'Paket Aktif',
        'processing' => 'Memproses...',
        
        'plans' => [
            'basic' => [
                'name' => 'Basic',
                'description' => '1 outlet, No admin + pickup',
                'price' => 'Rp0',
                'features' => [
                    'AI chatbot dasar (% pesan/bulan)',
                    'Reservasi & pickup orders',
                    'Watermark menu digital',
                    'Tanpa pembayaran online',
                    '1 user staf + Email support',
                ],
            ],
            'standard' => [
                'name' => 'Standard',
                'description' => 'Hingga 2 outlet, delivery + QRIS',
                'price' => 'Rp249.000',
                'features' => [
                    'Delivery + antrean & biaya',
                    'Pembayaran QRIS unlimited',
                    'Pengingat & auto confirm',
                    'XX pesan/bulan + Chat support (2hr)',
                    'Customer Base',
                ],
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Tim unlimited, Analytic & API',
                'price' => 'Rp2.990.000',
                'features' => [
                    'Analytic + ekspor CSV',
                    'Webhook & API',
                    'Multi-outlet & branding',
                    'Priority routing & handover',
                    'SLA + dedicated support',
                ],
            ],
        ],
    ],

    // Bagian Tentang Kami
    'about' => [
        'title' => 'Tentang Kami',
        'subtitle' => 'Kenali lebih dekat QashierWise dan tim di belaknya',
        'what_is_title' => 'Apa itu QashierWise?',
        'what_is_description_1' => 'QashierWise adalah solusi modern yang menghadirkan AI Chatbot WhatsApp dengan sistem POS (Point of Sale) untuk restoran dan manajemen untuk proses reservasi dan pemesanan melalui WhatsApp. Serta menyajikan operasional bisnis dengan integrasi QRIS.',
        'what_is_description_2' => 'Dengan QashierWise restoran dapat mengelola operasional lewat API, mengubah pesan secara otomatis, memiliki pembayaran QRIS, dan meningkatkan bisnis yang kompetitif - semua dalam satu platform yang mudah digunakan.',
        'founder_title' => 'Founder',
        'founder_name' => 'Aditya Bintang Fadila',
        'founder_description' => 'QashierWise dibuat oleh Aditya Bintang Fadila, yang berkomitmen untuk menghadirkan solusi teknologi terbaik bagi industri F&B di Indonesia.',
        'vision_title' => 'Visi Kami',
        'vision_description' => 'Menjadi platform terdepan dalam transformasi digital restoran di Indonesia, membantu bisnis F&B berkembang dengan teknologi yang mudah, modern dan terjangkau.',
    ],

    // Bagian FAQ
    'faq' => [
        'title' => 'Pertanyaan yang sering diajukan',
        'subtitle' => 'Semua dalam Bahasa Indonesia.',
        'items' => [
            [
                'question' => 'Apa itu QashierWise?',
                'answer' => 'QashierWise adalah platform AI Chatbot WhatsApp yang dirancang khusus untuk restoran, membantu mengelola reservasi, pesanan, dan pembayaran QRIS dalam satu dashboard.',
            ],
            [
                'question' => 'Apakah perlu aplikasi terpisah untuk pelanggan?',
                'answer' => 'Tidak! Pelanggan cukup menggunakan WhatsApp yang sudah mereka miliki. Tidak perlu download aplikasi tambahan.',
            ],
            [
                'question' => 'Bagaimana pembayaran dilakukan?',
                'answer' => 'Pembayaran bisa dilakukan melalui QRIS yang terintegrasi dengan Midtrans, Xendit, atau penyedia QRIS lainnya. Pelanggan juga bisa bayar di tempat.',
            ],
            [
                'question' => 'Apakah bisa multi-outlet dan multi nomor?',
                'answer' => 'Ya! Paket Standard mendukung hingga 2 outlet, dan paket Pro mendukung unlimited outlet dengan fitur multi-branding.',
            ],
        ],
    ],

    // Footer
    'footer' => [
        'company_description' => 'Coba gratis 14 hari, QashierWise membantu restoran menerima reservasi & order via WhatsApp dengan cepat.',
        'address_title' => 'Alamat',
        'address' => 'Jl. Widosari No. 55, Tegalrejo Raya<br>Salatiga, Jawa Tengah, Indonesia<br>50733',
        'navigation_title' => 'Navigasi',
        'legal_title' => 'Legal',
        'product_title' => 'Produk',
        'privacy_policy' => 'Kebijakan Privasi',
        'terms_of_service' => 'Ketentuan Layanan',
        'console' => 'QashierWise Console',
        'chatbot' => 'Chatbot WhatsApp',
        'copyright' => '&copy; 2025 QashierWise by Aditya Bintang Fadila. All Rights Reserved.',
    ],

    // Call to Action
    'cta' => [
        'try_now' => 'Coba Sekarang',
        'get_started' => 'Mulai Sekarang',
        'learn_more' => 'Pelajari Lebih Lanjut',
        'contact_us' => 'Hubungi Kami',
        'sign_up' => 'Daftar',
    ],
];
