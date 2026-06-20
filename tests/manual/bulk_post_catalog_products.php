#!/usr/bin/env php
<?php

/**
 * Bulk-post 5 random Indonesian food products to each of 7 catalogs.
 * Uses curated Unsplash food image URLs (HEAD-verified 200 OK).
 *
 * Run: php tests/manual/bulk_post_catalog_products.php
 */

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$account = \App\Models\WhatsAppAccount::withoutGlobalScopes()->find(1);
if (! $account) {
    fwrite(STDERR, "No WhatsApp account found.\n");
    exit(1);
}
$token = $account->access_token;

$catalogs = [
    '760951533674082'  => 'qashierwise Catalog',
    '878155518634150'  => 'Anla Catalog (1)',
    '914665348248282'  => 'Anla Catalog (2)',
    '1026187050087138' => 'Catalogue_Products',
    '1759647168503815' => 'new_catalog',
    '2797709597258197' => 'Catalogue_Products2',
    '3203965079786789' => 'makanan sehat',
];

// HEAD-verified working Unsplash food image URLs
$images = [
    'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800&q=80', // pizza
    'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&q=80', // pasta
    'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80',    // bowl
    'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=800&q=80', // pancakes
    'https://images.unsplash.com/photo-1551782450-a2132b4ba21d?w=800&q=80',    // burger
    'https://images.unsplash.com/photo-1565958011703-44f9829ba187?w=800&q=80', // sushi
    'https://images.unsplash.com/photo-1572441713132-c542fc4fe282?w=800&q=80', // food
    'https://images.unsplash.com/photo-1606755962773-d324e0a13086?w=800&q=80', // donut
    'https://images.unsplash.com/photo-1432139509613-5c4255815697?w=800&q=80', // steak
    'https://images.unsplash.com/photo-1604908176997-125f25cc6f3d?w=800&q=80', // chicken
    'https://images.unsplash.com/photo-1565299585323-38d6b0865b47?w=800&q=80', // dessert
];

// 35 meaningful Indonesian food products
$products = [
    ['Nasi Goreng Spesial',      'Nasi goreng dengan telur, ayam suwir, dan kerupuk udang khas Indonesia.',     25000,  'Makanan Utama'],
    ['Sate Ayam Madura',         'Sate ayam bumbu kacang khas Madura, disajikan dengan lontong dan acar.',     28000,  'Makanan Utama'],
    ['Soto Betawi',              'Soto khas Betawi dengan kuah santan gurih, daging sapi empuk, dan emping.',  32000,  'Makanan Utama'],
    ['Rendang Padang',           'Daging sapi dimasak lambat dengan rempah Padang autentik selama 8 jam.',     45000,  'Makanan Utama'],
    ['Gado-Gado Jakarta',        'Sayuran segar dengan bumbu kacang creamy dan kerupuk emping.',               22000,  'Makanan Utama'],
    ['Bakso Malang Komplit',     'Bakso urat dengan mie, tahu, pangsit goreng, dan kuah kaldu sapi.',          25000,  'Makanan Utama'],
    ['Mie Ayam Pangsit',         'Mie kuning dengan ayam suwir, pangsit goreng dan kuah kaldu ayam.',          20000,  'Makanan Utama'],
    ['Ayam Geprek Sambal Matah', 'Ayam crispy digeprek dengan sambal matah Bali yang segar dan pedas.',        24000,  'Makanan Utama'],
    ['Nasi Padang Komplit',      'Nasi dengan rendang, ayam pop, daun singkong, dan sambal hijau.',            35000,  'Makanan Utama'],
    ['Pempek Palembang',         'Pempek kapal selam berisi telur dengan kuah cuko khas Palembang.',           28000,  'Makanan Utama'],
    ['Rawon Daging Sapi',        'Sup daging sapi hitam khas Jawa Timur dengan kluwek dan tauge.',             30000,  'Makanan Utama'],
    ['Gudeg Jogja Komplit',      'Gudeg manis dengan ayam kampung, telur, krecek, dan sambal goreng.',         33000,  'Makanan Utama'],
    ['Soto Lamongan',            'Soto ayam khas Lamongan dengan koya bubuk kerupuk udang khas.',              22000,  'Makanan Utama'],
    ['Iga Bakar Madu',           'Iga sapi bakar marinasi madu dan kecap, empuk dan harum.',                   55000,  'Makanan Utama'],
    ['Es Cendol Durian',         'Es cendol tradisional dengan santan, gula merah, dan topping durian.',       18000,  'Minuman'],
    ['Pizza Margherita',         'Pizza klasik Italia dengan tomat, mozzarella segar, dan basil.',             65000,  'Makanan Internasional'],
    ['Burger Beef Cheese',       'Burger sapi 200gr dengan keju cheddar leleh dan saus barbeque.',             45000,  'Makanan Internasional'],
    ['Sushi Salmon Roll',        'Sushi roll dengan salmon segar, alpukat, dan saus spicy mayo.',              48000,  'Makanan Internasional'],
    ['Steak Tenderloin',         'Steak tenderloin sapi premium dengan saus mushroom dan kentang.',            85000,  'Makanan Internasional'],
    ['Pancake Maple Syrup',      'Pancake fluffy dengan maple syrup asli dan butter Anchor.',                  35000,  'Dessert'],
    ['Donut Coklat',             'Donut empuk dengan glaze coklat Belgia dan topping kacang.',                 15000,  'Dessert'],
    ['Es Krim Vanilla Bean',     'Es krim vanilla bean Madagascar premium dengan saus karamel.',               22000,  'Dessert'],
    ['Capcay Seafood',           'Tumis sayuran segar dengan udang, cumi, dan bakso ikan.',                    32000,  'Makanan Utama'],
    ['Nasi Uduk Komplit',        'Nasi uduk gurih dengan ayam goreng, telur dadar, dan sambal kacang.',        25000,  'Makanan Utama'],
    ['Lumpia Semarang',          'Lumpia basah berisi rebung dan ayam, sajian khas Semarang.',                 20000,  'Snack'],
    ['Ketoprak Jakarta',         'Lontong, tahu, dan bihun dengan bumbu kacang khas Jakarta.',                 18000,  'Makanan Utama'],
    ['Sop Buntut Bakar',         'Buntut sapi bakar dengan kuah kaldu rempah dan perkedel kentang.',           65000,  'Makanan Utama'],
    ['Ayam Bakar Taliwang',      'Ayam bakar pedas khas Lombok dengan plecing kangkung.',                      38000,  'Makanan Utama'],
    ['Pecel Lele Sambal Terasi', 'Lele goreng renyah dengan sambal terasi mentah dan lalapan.',                20000,  'Makanan Utama'],
    ['Nasi Liwet Solo',          'Nasi liwet gurih dengan ayam suwir, telur pindang, dan sayur labu.',         28000,  'Makanan Utama'],
    ['Tahu Tempe Bacem',         'Tahu dan tempe bacem manis gurih dengan nasi hangat dan sambal.',            15000,  'Makanan Utama'],
    ['Tongseng Kambing',         'Tongseng kambing dengan kuah santan gurih dan kol segar.',                   42000,  'Makanan Utama'],
    ['Sate Lilit Bali',          'Sate lilit ikan dengan bumbu Bali, dibakar di atas serai harum.',            32000,  'Makanan Utama'],
    ['Cumi Saus Padang',         'Cumi segar dengan saus Padang pedas, manis dan gurih.',                      38000,  'Makanan Utama'],
    ['Es Doger Bandung',         'Es doger khas Bandung dengan kelapa, alpukat, dan tape singkong.',           17000,  'Minuman'],
];

function post(string $url, array $form, string $token): array {
    $form['access_token'] = $token;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $code, 'body' => json_decode($resp, true)];
}

$productIdx = 0;
$imageIdx = 0;
$totalSuccess = 0;
$totalFail = 0;
$timestamp = time();

foreach ($catalogs as $catalogId => $catalogName) {
    echo str_repeat('=', 70) . PHP_EOL;
    echo "Catalog $catalogId — $catalogName" . PHP_EOL;
    echo str_repeat('=', 70) . PHP_EOL;

    for ($i = 0; $i < 5; $i++) {
        $p = $products[$productIdx % count($products)];
        $img = $images[$imageIdx % count($images)];
        $productIdx++;
        $imageIdx++;

        [$name, $description, $price, $category] = $p;

        $retailerId = sprintf('BULK-%s-%s-%d',
            substr($catalogId, -6),
            substr($timestamp, -6),
            $i + 1
        );

        $payload = [
            'retailer_id'  => $retailerId,
            'name'         => $name,
            'description'  => $description,
            'price'        => $price * 100, // Meta expects minor units; IDR has no minor unit, so this is conventional for the controller-compatible payload
            'currency'     => 'IDR',
            'image_url'    => $img,
            'url'          => 'https://qashierwise.com/menu',
            'availability' => 'in stock',
            'condition'    => 'new',
            'brand'        => 'Kafe Qashierwise',
            'category'     => $category,
        ];

        $result = post("https://graph.facebook.com/v21.0/$catalogId/products", $payload, $token);

        if ($result['status'] === 200 && isset($result['body']['id'])) {
            echo sprintf("  ✓ [%d/5] %-30s id=%s\n", $i + 1, $name, $result['body']['id']);
            $totalSuccess++;
        } else {
            $err = $result['body']['error']['message'] ?? json_encode($result['body']);
            echo sprintf("  ✗ [%d/5] %-30s ERROR: %s\n", $i + 1, $name, substr($err, 0, 100));
            $totalFail++;
        }

        usleep(200000); // 200ms rate-limit cushion
    }
    echo PHP_EOL;
}

echo str_repeat('=', 70) . PHP_EOL;
echo "DONE — success: $totalSuccess, fail: $totalFail" . PHP_EOL;
echo str_repeat('=', 70) . PHP_EOL;
