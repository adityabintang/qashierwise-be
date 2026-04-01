<?php

return [
    'resources' => [
        'blog_post' => [
            'label' => 'Artikel Blog',
            'plural' => 'Artikel Blog',
            'notifications' => [
                'created_title' => 'Blog Post Berhasil Disimpan',
                'created_body' => 'Artikel ":title" telah dibuat.',
                'updated_title' => 'Blog Post Berhasil Disimpan',
                'updated_body' => 'Artikel ":title" telah diperbarui.',
                'failed_title' => 'Gagal Menyimpan Blog Post',
                'failed_body' => 'Periksa kembali input form yang belum valid.',
                'invalid_publish_date_body' => 'Tanggal publikasi tidak boleh lebih besar dari waktu saat ini untuk status Published.',
                'invalid_schedule_date_body' => 'Tanggal publikasi wajib lebih besar dari waktu saat ini untuk status Scheduled.',
            ],
            'sections' => [
                'content' => 'Konten',
                'media' => 'Media',
                'seo' => 'SEO',
                'publishing' => 'Publikasi',
            ],
            'fields' => [
                'title' => 'Judul',
                'slug' => 'Slug',
                'category' => 'Kategori',
                'excerpt' => 'Ringkasan',
                'content' => 'Konten',
                'featured_image' => 'Gambar Utama',
                'featured_image_helper' => 'Gambar akan otomatis dikonversi ke WebP dan diresize maksimal 1920px lebar.',
                'seo_title' => 'Judul SEO',
                'seo_description' => 'Deskripsi SEO',
                'og_image' => 'Gambar OG',
                'og_image_helper' => 'Gambar untuk preview saat di-share di social media (1200x630px). Jika tidak diupload, akan menggunakan gambar dari Media. Gambar akan otomatis dikonversi ke WebP dan diresize maksimal 1200px lebar.',
                'status' => 'Status',
                'publish_date' => 'Tanggal Publikasi',
                'publish_date_helper' => 'Input menggunakan WIB. Sistem tetap menyimpan waktu dalam UTC.',
                'tags' => 'Tag',
                'author' => 'Penulis',
                'published_at' => 'Dipublikasikan',
                'created_at' => 'Dibuat',
                'updated_at' => 'Diperbarui',
            ],
            'filters' => [
                'status' => 'Status',
                'category' => 'Kategori',
            ],
        ],
        'blog_category' => [
            'label' => 'Kategori Blog',
            'plural' => 'Kategori Blog',
            'sections' => [
                'details' => 'Detail Kategori',
                'statistics' => 'Statistik',
            ],
            'fields' => [
                'name' => 'Nama',
                'slug' => 'Slug',
                'description' => 'Deskripsi',
                'is_active' => 'Aktif',
                'posts_count' => 'Total Artikel',
            ],
        ],
        'blog_tag' => [
            'label' => 'Tag Blog',
            'plural' => 'Tag Blog',
            'sections' => [
                'details' => 'Detail Tag',
                'statistics' => 'Statistik',
            ],
            'fields' => [
                'name' => 'Nama',
                'slug' => 'Slug',
                'posts_count' => 'Total Artikel',
            ],
        ],
    ],
];
