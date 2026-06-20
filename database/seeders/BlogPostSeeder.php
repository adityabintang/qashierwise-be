<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogPostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get or create super admin user
        $superAdmin = User::where('email', User::SUPER_ADMIN_EMAIL)->first();

        if (! $superAdmin) {
            $this->command->error('Super admin not found. Please create super admin first.');

            return;
        }

        // Create categories if not exist
        $categories = [
            ['name' => 'Technology', 'description' => 'Latest technology trends and innovations'],
            ['name' => 'Business', 'description' => 'Business insights and strategies'],
            ['name' => 'Tutorial', 'description' => 'Step-by-step guides and tutorials'],
            ['name' => 'News', 'description' => 'Latest news and updates'],
        ];

        foreach ($categories as $categoryData) {
            BlogCategory::firstOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                $categoryData + ['is_active' => true]
            );
        }

        // Create tags if not exist
        $tagNames = ['QRIS', 'Payment', 'POS', 'E-commerce', 'Digital', 'Innovation', 'Guide', 'Tips', 'Business', 'Technology'];

        foreach ($tagNames as $tagName) {
            BlogTag::firstOrCreate(
                ['slug' => Str::slug($tagName)],
                ['name' => $tagName]
            );
        }

        // Blog post data
        $posts = [
            [
                'title' => 'Panduan Lengkap Menggunakan QRIS untuk Bisnis Anda',
                'excerpt' => 'Pelajari cara mengintegrasikan QRIS ke dalam sistem pembayaran bisnis Anda dengan mudah dan efisien.',
                'content' => '<h2>Apa itu QRIS?</h2><p>QRIS (Quick Response Code Indonesian Standard) adalah standar pembayaran menggunakan QR Code yang dikembangkan oleh Bank Indonesia. Dengan QRIS, pelanggan dapat melakukan pembayaran dengan mudah menggunakan berbagai aplikasi e-wallet dan mobile banking.</p><h2>Keuntungan Menggunakan QRIS</h2><ul><li>Satu QR Code untuk semua metode pembayaran digital</li><li>Transaksi lebih cepat dan efisien</li><li>Biaya operasional lebih rendah</li><li>Meningkatkan kepuasan pelanggan</li></ul><h2>Cara Implementasi</h2><p>Implementasi QRIS di bisnis Anda sangat mudah. Anda hanya perlu mendaftar melalui penyedia layanan payment gateway yang mendukung QRIS, seperti QashierWise.</p>',
                'category' => 'Tutorial',
                'tags' => ['QRIS', 'Payment', 'Guide'],
            ],
            [
                'title' => 'Transformasi Digital: Mengapa Bisnis Anda Membutuhkan Sistem POS Modern',
                'excerpt' => 'Sistem POS modern bukan hanya tentang kasir digital, tetapi solusi lengkap untuk mengelola bisnis Anda.',
                'content' => '<h2>Era Digital dalam Retail</h2><p>Bisnis retail modern membutuhkan lebih dari sekadar mesin kasir. Sistem POS (Point of Sale) modern mengintegrasikan inventory management, customer relationship, dan analytics dalam satu platform.</p><h2>Fitur-Fitur Penting POS Modern</h2><ul><li>Real-time inventory tracking</li><li>Multi-channel sales integration</li><li>Customer data management</li><li>Detailed sales analytics</li><li>Cloud-based accessibility</li></ul><h2>ROI dari Investasi POS</h2><p>Investasi dalam sistem POS modern dapat memberikan return yang signifikan melalui efisiensi operasional, pengurangan kesalahan, dan peningkatan customer experience.</p>',
                'category' => 'Business',
                'tags' => ['POS', 'Digital', 'Business'],
            ],
            [
                'title' => '10 Tips Meningkatkan Penjualan dengan E-commerce',
                'excerpt' => 'Strategi praktis untuk meningkatkan konversi dan penjualan di toko online Anda.',
                'content' => '<h2>Optimasi Pengalaman Pelanggan</h2><p>Pengalaman pelanggan adalah kunci sukses e-commerce. Pastikan website Anda mudah dinavigasi, loading cepat, dan mobile-friendly.</p><h2>10 Tips Praktis</h2><ol><li>Gunakan foto produk berkualitas tinggi</li><li>Tulis deskripsi produk yang detail dan menarik</li><li>Tawarkan berbagai metode pembayaran</li><li>Berikan free shipping untuk pembelian tertentu</li><li>Implementasikan sistem review dan rating</li><li>Gunakan email marketing untuk follow-up</li><li>Optimalkan SEO untuk organic traffic</li><li>Manfaatkan social media marketing</li><li>Berikan customer service yang responsif</li><li>Analisis data untuk continuous improvement</li></ol>',
                'category' => 'Business',
                'tags' => ['E-commerce', 'Tips', 'Business'],
            ],
            [
                'title' => 'Keamanan Transaksi Digital: Panduan untuk Merchant',
                'excerpt' => 'Lindungi bisnis dan pelanggan Anda dengan praktik keamanan transaksi digital yang tepat.',
                'content' => '<h2>Pentingnya Keamanan Transaksi</h2><p>Dalam era digital, keamanan transaksi adalah prioritas utama. Pelanggan harus merasa aman saat melakukan pembayaran di platform Anda.</p><h2>Best Practices Keamanan</h2><ul><li>Gunakan SSL/TLS encryption</li><li>Implementasi two-factor authentication</li><li>Regular security audits</li><li>PCI DSS compliance</li><li>Secure payment gateway integration</li></ul><h2>Edukasi Pelanggan</h2><p>Edukasi pelanggan tentang cara bertransaksi yang aman juga penting untuk mencegah fraud dan meningkatkan kepercayaan.</p>',
                'category' => 'Technology',
                'tags' => ['Payment', 'Technology', 'Guide'],
            ],
            [
                'title' => 'Cara Mengelola Inventory dengan Efisien',
                'excerpt' => 'Teknik dan strategi untuk mengoptimalkan manajemen inventory bisnis Anda.',
                'content' => '<h2>Tantangan Inventory Management</h2><p>Manajemen inventory yang buruk dapat menyebabkan stockout, overstock, dan kerugian finansial. Sistem yang tepat dapat membantu mengoptimalkan inventory Anda.</p><h2>Strategi Efektif</h2><ul><li>Implementasi sistem real-time tracking</li><li>Gunakan ABC analysis untuk prioritas</li><li>Set up automatic reorder points</li><li>Regular inventory audits</li><li>Integrate dengan sistem POS</li></ul><h2>Teknologi Pendukung</h2><p>Teknologi modern seperti barcode scanning, RFID, dan cloud-based inventory systems dapat meningkatkan akurasi dan efisiensi.</p>',
                'category' => 'Tutorial',
                'tags' => ['POS', 'Business', 'Guide'],
            ],
            [
                'title' => 'Tren Payment Technology 2024',
                'excerpt' => 'Eksplorasi tren terbaru dalam teknologi pembayaran yang akan mengubah landscape bisnis.',
                'content' => '<h2>Evolusi Payment Technology</h2><p>Teknologi pembayaran terus berkembang dengan cepat. Dari contactless payment hingga cryptocurrency, bisnis harus siap beradaptasi.</p><h2>Tren Utama 2024</h2><ul><li>Biometric authentication</li><li>Buy Now Pay Later (BNPL)</li><li>Embedded finance</li><li>Central Bank Digital Currency (CBDC)</li><li>AI-powered fraud detection</li></ul><h2>Persiapan untuk Masa Depan</h2><p>Bisnis yang ingin tetap kompetitif harus mulai mengadopsi teknologi pembayaran terbaru dan mempersiapkan infrastruktur yang fleksibel.</p>',
                'category' => 'Technology',
                'tags' => ['Payment', 'Technology', 'Innovation'],
            ],
            [
                'title' => 'Membangun Customer Loyalty Program yang Efektif',
                'excerpt' => 'Strategi menciptakan program loyalitas yang meningkatkan retention dan lifetime value pelanggan.',
                'content' => '<h2>Pentingnya Customer Loyalty</h2><p>Mempertahankan pelanggan existing lebih murah daripada mencari pelanggan baru. Program loyalitas yang efektif dapat meningkatkan repeat purchase hingga 80%.</p><h2>Elemen Program Loyalitas</h2><ul><li>Point-based rewards system</li><li>Tiered membership levels</li><li>Exclusive perks dan benefits</li><li>Personalized offers</li><li>Gamification elements</li></ul><h2>Implementasi dengan POS</h2><p>Sistem POS modern dapat mengintegrasikan program loyalitas secara seamless, tracking points dan rewards secara otomatis.</p>',
                'category' => 'Business',
                'tags' => ['Business', 'Tips', 'POS'],
            ],
            [
                'title' => 'Integrasi Multi-Channel: Omnichannel Strategy untuk Retail',
                'excerpt' => 'Panduan lengkap mengimplementasikan strategi omnichannel untuk meningkatkan customer experience.',
                'content' => '<h2>Apa itu Omnichannel?</h2><p>Omnichannel adalah strategi yang mengintegrasikan semua channel penjualan (online dan offline) untuk memberikan pengalaman yang seamless kepada pelanggan.</p><h2>Keuntungan Omnichannel</h2><ul><li>Consistent customer experience</li><li>Increased sales opportunities</li><li>Better customer insights</li><li>Improved inventory management</li><li>Higher customer satisfaction</li></ul><h2>Langkah Implementasi</h2><p>Mulai dengan mengintegrasikan sistem POS, e-commerce, dan inventory management. Pastikan data tersinkronisasi real-time di semua channel.</p>',
                'category' => 'Business',
                'tags' => ['E-commerce', 'POS', 'Business'],
            ],
            [
                'title' => 'Analisis Data untuk Keputusan Bisnis yang Lebih Baik',
                'excerpt' => 'Manfaatkan data analytics untuk membuat keputusan bisnis yang data-driven dan profitable.',
                'content' => '<h2>Era Data-Driven Decision</h2><p>Bisnis modern tidak bisa lagi mengandalkan intuisi semata. Data analytics memberikan insights yang objektif untuk pengambilan keputusan.</p><h2>Metrics Penting untuk Retail</h2><ul><li>Sales per square foot</li><li>Inventory turnover ratio</li><li>Customer acquisition cost</li><li>Average transaction value</li><li>Customer lifetime value</li></ul><h2>Tools dan Teknologi</h2><p>Sistem POS modern dilengkapi dengan dashboard analytics yang comprehensive, memberikan real-time insights tentang performa bisnis Anda.</p>',
                'category' => 'Technology',
                'tags' => ['Technology', 'Business', 'Tips'],
            ],
            [
                'title' => 'Cara Memulai Bisnis Online dari Nol',
                'excerpt' => 'Panduan step-by-step untuk memulai bisnis online Anda dengan modal minimal.',
                'content' => '<h2>Memulai Perjalanan E-commerce</h2><p>Memulai bisnis online tidak harus mahal atau rumit. Dengan strategi yang tepat, Anda bisa memulai dengan modal minimal.</p><h2>Langkah-Langkah Awal</h2><ol><li>Tentukan niche dan target market</li><li>Riset kompetitor dan market demand</li><li>Pilih platform e-commerce yang tepat</li><li>Setup payment gateway (QRIS, transfer, dll)</li><li>Buat konten produk yang menarik</li><li>Implementasi strategi marketing</li><li>Berikan customer service yang excellent</li></ol><h2>Tips Sukses</h2><p>Fokus pada customer satisfaction, build trust melalui reviews, dan terus belajar dari data analytics untuk improve bisnis Anda.</p>',
                'category' => 'Tutorial',
                'tags' => ['E-commerce', 'Guide', 'Business'],
            ],
        ];

        $this->command->info('Creating blog posts...');

        foreach ($posts as $index => $postData) {
            $category = BlogCategory::where('slug', Str::slug($postData['category']))->first();

            $post = BlogPost::create([
                'user_id' => $superAdmin->id,
                'blog_category_id' => $category?->id,
                'title' => $postData['title'],
                'slug' => Str::slug($postData['title']),
                'excerpt' => $postData['excerpt'],
                'content' => $postData['content'],
                'status' => PostStatus::Published,
                'published_at' => now()->subDays(10 - $index),
                'seo_title' => $postData['title'],
                'seo_description' => $postData['excerpt'],
            ]);

            // Attach tags
            $tags = BlogTag::whereIn('slug', array_map(fn ($tag) => Str::slug($tag), $postData['tags']))->get();
            $post->tags()->attach($tags);

            $this->command->info('Created: '.$post->title);
        }

        $this->command->info('Blog posts seeded successfully!');
    }
}
