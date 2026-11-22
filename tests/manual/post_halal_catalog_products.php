#!/usr/bin/env php
<?php
/**
 * Post 30 products to catalog 954663720663102
 * Categories: Tiket, Halal Mart, Halal Food
 * Stock: 10 each
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$CATALOG_ID = '878155518634150';

// Token dari command/credential.json — berlaku di local & production
$credFile = __DIR__ . '/../../command/credential.json';
$cred     = json_decode(file_get_contents($credFile), true);
$token    = $cred['token'] ?? null;

if (! $token) {
    fwrite(STDERR, "Token tidak ditemukan di command/credential.json\n");
    exit(1);
}

echo "Account  : {$cred['display_number']}\n";
echo "Catalog  : {$CATALOG_ID}\n\n";

$timestamp = time();

// 30 products — 10 per category
$products = [
    // ── Tiket (10) ──────────────────────────────────────────────────────────
    ['category' => 'Tiket', 'name' => 'Tiket Konser Musik Nusantara',     'desc' => 'Tiket masuk konser musik live artis Indonesia pilihan.',              'price' => 150000, 'img' => 'https://images.unsplash.com/photo-1540039155733-5bb30b4f5a1d?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Festival Kuliner Halal',     'desc' => 'Tiket festival makanan halal terbesar se-Indonesia.',                'price' => 50000,  'img' => 'https://images.unsplash.com/photo-1555939594-58d7cb561ad1?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Pameran Produk Halal',       'desc' => 'Tiket pameran dan expo produk halal bersertifikat MUI.',             'price' => 35000,  'img' => 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Seminar Keuangan Syariah',   'desc' => 'Seminar investasi dan keuangan berbasis syariah Islam.',             'price' => 200000, 'img' => 'https://images.unsplash.com/photo-1505373877841-8d25f7d46678?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Workshop Memasak Halal',     'desc' => 'Workshop memasak hidangan halal bersama chef profesional.',         'price' => 125000, 'img' => 'https://images.unsplash.com/photo-1556909114-f6e7ad7d3136?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Wisata Religi Walisongo',    'desc' => 'Paket tour ziarah Walisongo 3 hari 2 malam all-inclusive.',         'price' => 850000, 'img' => 'https://images.unsplash.com/photo-1519817650390-64a93db51149?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Kajian Islam Nasional',      'desc' => 'Tiket kajian ilmu Islam bersama ustadz nasional ternama.',           'price' => 75000,  'img' => 'https://images.unsplash.com/photo-1585776245991-cf89dd7fc73a?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Bazaar Ramadan',             'desc' => 'Tiket masuk bazaar Ramadan dengan 200+ tenant halal.',              'price' => 25000,  'img' => 'https://images.unsplash.com/photo-1576867757603-05b134ebc379?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Lomba Tilawah Al-Quran',     'desc' => 'Tiket penonton lomba MTQ tingkat nasional.',                        'price' => 30000,  'img' => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=800&q=80'],
    ['category' => 'Tiket', 'name' => 'Tiket Talkshow UMKM Syariah',      'desc' => 'Talkshow peluang bisnis UMKM berbasis nilai-nilai syariah.',         'price' => 100000, 'img' => 'https://images.unsplash.com/photo-1475721027785-f74eccf877e2?w=800&q=80'],

    // ── Halal Mart (10) ─────────────────────────────────────────────────────
    ['category' => 'Halal Mart', 'name' => 'Madu Hutan Asli 500ml',          'desc' => 'Madu hutan murni tanpa campuran, bersertifikat halal MUI.',         'price' => 95000,  'img' => 'https://images.unsplash.com/photo-1587049352846-4a222e784d38?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Kurma Medjool Premium 500gr',    'desc' => 'Kurma Medjool ukuran jumbo impor langsung dari Madinah.',           'price' => 120000, 'img' => 'https://images.unsplash.com/photo-1563746924237-f81d9a89e5c4?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Sabun Mandi Halal Aloe Vera',    'desc' => 'Sabun batang halal dengan ekstrak aloe vera organik sertifikat MUI.','price' => 25000,  'img' => 'https://images.unsplash.com/photo-1600857062241-98e5dba7f786?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Sampo Herbal Halal 200ml',       'desc' => 'Sampo berbahan herbal alami, bebas alkohol, halal bersertifikat.',  'price' => 35000,  'img' => 'https://images.unsplash.com/photo-1585751119414-ef2636f8aede?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Parfum Non-Alkohol Oud 50ml',    'desc' => 'Parfum premium berbahan dasar oud tanpa kandungan alkohol.',        'price' => 185000, 'img' => 'https://images.unsplash.com/photo-1541643600914-78b084683702?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Kopi Arabika Halal 250gr',       'desc' => 'Biji kopi arabika single origin Aceh Gayo, disertifikasi halal.',  'price' => 65000,  'img' => 'https://images.unsplash.com/photo-1559056199-641a0ac8b55e?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Teh Hijau Organik Halal 100gr',  'desc' => 'Teh hijau organik pilihan, bebas pestisida, halal MUI.',            'price' => 45000,  'img' => 'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Susu Kambing Etawa 1L',          'desc' => 'Susu kambing etawa segar halal, kaya nutrisi dan omega-3.',         'price' => 55000,  'img' => 'https://images.unsplash.com/photo-1550583724-b2692b85b150?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Suplemen Vitamin Halal',         'desc' => 'Suplemen multivitamin harian bersertifikat halal, tanpa gelatin babi.','price' => 75000, 'img' => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=800&q=80'],
    ['category' => 'Halal Mart', 'name' => 'Snack Kacang Mete Halal 250gr',  'desc' => 'Kacang mete panggang original tanpa MSG, halal bersertifikat.',     'price' => 55000,  'img' => 'https://images.unsplash.com/photo-1599599810769-bcde5a160d32?w=800&q=80'],

    // ── Halal Food (10) ─────────────────────────────────────────────────────
    ['category' => 'Halal Food', 'name' => 'Nasi Box Ayam Bakar',            'desc' => 'Nasi putih dengan ayam bakar bumbu rempah, lalapan, dan sambal.',   'price' => 28000,  'img' => 'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Rendang Sapi Kemasan 300gr',     'desc' => 'Rendang sapi autentik Padang, dikemas steril tahan 7 hari.',        'price' => 65000,  'img' => 'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Bakso Sapi Premium 10 Biji',     'desc' => 'Bakso sapi asli tanpa babi, kadar daging 80%, halal MUI.',          'price' => 35000,  'img' => 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Ayam Geprek Halal Porsi Jumbo',  'desc' => 'Ayam crispy geprek pedas dengan nasi dan lalapan segar halal.',     'price' => 30000,  'img' => 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Soto Ayam Kampung',              'desc' => 'Soto ayam kampung kuah bening dengan soun, telur, dan kerupuk.',    'price' => 22000,  'img' => 'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Martabak Telur Halal',           'desc' => 'Martabak telur sapi dengan daun bawang dan bumbu rempah halal.',    'price' => 40000,  'img' => 'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Pempek Kapal Selam Halal',       'desc' => 'Pempek Palembang berisi telur dengan cuko pedas manis.',            'price' => 30000,  'img' => 'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Nasi Goreng Seafood Halal',      'desc' => 'Nasi goreng udang dan cumi segar dengan bumbu istimewa halal.',     'price' => 32000,  'img' => 'https://images.unsplash.com/photo-1572441713132-c542fc4fe282?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Sate Kambing Muda',              'desc' => 'Sate daging kambing muda empuk dengan bumbu kecap dan sambal.',     'price' => 45000,  'img' => 'https://images.unsplash.com/photo-1432139509613-5c4255815697?w=800&q=80'],
    ['category' => 'Halal Food', 'name' => 'Gulai Ikan Kakap Halal',         'desc' => 'Gulai ikan kakap segar dengan santan dan rempah Minang.',           'price' => 38000,  'img' => 'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&q=80'],
];

function postProduct(string $catalogId, array $p, string $token, int $idx, int $ts): void
{
    $retailerId = sprintf('HALAL-%s-%d-%d', substr($catalogId, -6), $ts, $idx);

    $payload = [
        'retailer_id'  => $retailerId,
        'name'         => $p['name'],
        'description'  => $p['desc'],
        'price'        => $p['price'] * 100, // Meta requires minor units (IDR × 100)
        'currency'     => 'IDR',
        'image_url'    => $p['img'],
        'url'          => 'https://qashierwise.com/menu',
        'availability' => 'in stock',
        'condition'    => 'new',
        'brand'        => 'Halal Store',
        'category'     => $p['category'],
    ];

    $ch = curl_init("https://graph.facebook.com/v21.0/{$catalogId}/products");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    // Auth via Bearer header, NOT form body
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$token}"]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($resp, true);
    $ok   = ($code === 200 && isset($body['id']));

    if ($ok) {
        // Save to local catalog_products table (stock=10)
        // Resolve user_id from the WhatsApp account for RLS
        static $userId = null;
        if ($userId === null) {
            $userId = \App\Models\WhatsAppAccount::withoutGlobalScopes()
                ->where('is_active', true)->latest('id')->value('user_id') ?? 1;
        }

        \App\Models\CatalogProduct::updateOrCreate(
            ['retailer_id' => $retailerId],
            [
                'user_id'        => $userId,
                'catalog_id'     => $catalogId,
                'meta_product_id'=> $body['id'],
                'retailer_id'    => $retailerId,
                'name'           => $p['name'],
                'price'          => $p['price'],
                'currency'       => 'IDR',
                'category'       => $p['category'],
                'availability'   => 'in stock',
                'stock_quantity' => 10,
                'is_available'   => true,
            ]
        );

        printf("  ✓ [%2d] %-40s  id=%s\n", $idx, $p['name'], $body['id']);
    } else {
        $err = $body['error']['message'] ?? json_encode($body);
        printf("  ✗ [%2d] %-40s  ERROR: %s\n", $idx, $p['name'], substr($err, 0, 120));
    }

    usleep(250000); // 250ms rate-limit cushion
}

// $token already set above from credential.json
$ok     = 0;
$fail   = 0;

echo str_repeat('═', 70) . PHP_EOL;
printf("  %-3s  %-40s  %s\n", '#', 'Produk', 'Kategori');
echo str_repeat('─', 70) . PHP_EOL;

foreach ($products as $i => $p) {
    $before = $ok;
    ob_start();
    postProduct($CATALOG_ID, $p, $token, $i + 1, $timestamp);
    $line = ob_get_clean();
    echo $line;
    if (str_contains($line, '✓')) $ok++; else $fail++;
}

echo str_repeat('═', 70) . PHP_EOL;
printf("  SELESAI — ✓ %d berhasil   ✗ %d gagal\n", $ok, $fail);
echo str_repeat('═', 70) . PHP_EOL;
