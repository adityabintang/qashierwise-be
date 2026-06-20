#!/usr/bin/env php
<?php

/**
 * Experiment: which fields make Meta actually review a product?
 *
 * Posts 4 test products to catalog 760951533674082, each with a different
 * combination of "identification" fields (brand / identifier_exists), then
 * fetches them back to see which got assigned a non-empty review_status.
 *
 * Usage:
 *   php tests/manual/test_catalog_review_experiment.php
 *
 * Cleanup:
 *   Products are tagged retailer_id "EXP_<timestamp>_<n>" so they can be
 *   identified and deleted later via Commerce Manager or the API.
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WhatsAppAccount;
use App\Services\CatalogService;

const CATALOG_ID = '760951533674082';
const ACCOUNT_ID = 1;

$account = WhatsAppAccount::withoutGlobalScopes()->find(ACCOUNT_ID);
if (! $account) {
    fwrite(STDERR, "Account id=" . ACCOUNT_ID . " not found\n");
    exit(1);
}

$svc = app(CatalogService::class);
$ts = time();

// 4 publicly-accessible HTTPS food images from Unsplash (no auth required).
$images = [
    'https://images.unsplash.com/photo-1565299624946-b28f40a0ae38?w=800&q=80', // pizza
    'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800&q=80', // bowl
    'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&q=80',    // salad
    'https://images.unsplash.com/photo-1567620905732-2d1ec7ab7445?w=800&q=80', // pancake
];

// 4 variants — same base payload, differing only in identification fields.
$basePayload = [
    'name'         => null, // set per variant
    'description'  => 'Makanan sehat berbahan dasar segar, cocok untuk diet harian. Produk ini adalah produk eksperimen untuk uji review Meta.',
    'price'        => 2500000, // Meta expects minor units; Rp25,000 → 2,500,000 (controller divides by 100 on store)
    'currency'     => 'IDR',
    'image_url'    => null, // set per variant
    'url'          => 'https://qashierwise.com/menu',
    'availability' => 'in stock',
    'condition'    => 'new',
];

$variants = [
    [
        'label'       => 'A — minimal (no brand, no identifier_exists)',
        'retailer_id' => "EXP_{$ts}_A",
        'extra'       => [], // control: current code behavior
    ],
    [
        'label'       => 'B — +brand only',
        'retailer_id' => "EXP_{$ts}_B",
        'extra'       => ['brand' => 'Qashier Test Kitchen'],
    ],
    [
        'label'       => 'C — +identifier_exists=false only',
        'retailer_id' => "EXP_{$ts}_C",
        'extra'       => ['identifier_exists' => 'false'],
    ],
    [
        'label'       => 'D — +brand AND identifier_exists=false',
        'retailer_id' => "EXP_{$ts}_D",
        'extra'       => [
            'brand'             => 'Qashier Test Kitchen',
            'identifier_exists' => 'false',
        ],
    ],
];

echo "==============================================================\n";
echo " Catalog Review Field Experiment\n";
echo " catalog_id: " . CATALOG_ID . "\n";
echo " timestamp:  " . date('Y-m-d H:i:s', $ts) . "\n";
echo "==============================================================\n\n";

$created = [];

foreach ($variants as $i => $v) {
    $payload = array_merge($basePayload, $v['extra'], [
        'retailer_id' => $v['retailer_id'],
        'name'        => "EXP {$ts} {$v['retailer_id']}",
        'image_url'   => $images[$i],
    ]);

    echo "→ Posting variant {$v['label']}\n";
    echo "  retailer_id: {$payload['retailer_id']}\n";
    echo "  extra fields: " . json_encode($v['extra']) . "\n";

    $result = $svc->createProduct($account, CATALOG_ID, $payload);

    if (! ($result['success'] ?? false)) {
        echo "  ✗ FAILED: {$result['error']} (code: {$result['error_code']})\n\n";
        continue;
    }

    echo "  ✓ created, meta product_id={$result['id']}\n\n";
    $created[] = [
        'label'       => $v['label'],
        'retailer_id' => $v['retailer_id'],
        'product_id'  => $result['id'],
    ];
}

if (empty($created)) {
    echo "No products created. Aborting verification step.\n";
    exit(1);
}

echo "Waiting 10s before fetching review_status...\n\n";
sleep(10);

// Clear cache so we get fresh data.
$svc->clearProductsCache(CATALOG_ID);

$fetch = $svc->getCatalogProducts($account, CATALOG_ID, 50);

if (! ($fetch['success'] ?? false)) {
    echo "Failed to fetch products: " . ($fetch['error'] ?? 'unknown') . "\n";
    exit(1);
}

$byRetailerId = [];
foreach ($fetch['products'] as $p) {
    if (isset($p['retailer_id'])) {
        $byRetailerId[$p['retailer_id']] = $p;
    }
}

echo "==============================================================\n";
echo " Results (10s after creation)\n";
echo "==============================================================\n";
printf("%-55s | %-15s | %s\n", "variant", "review_status", "rejection_reasons");
echo str_repeat('-', 110) . "\n";

foreach ($created as $c) {
    $p = $byRetailerId[$c['retailer_id']] ?? null;
    if (! $p) {
        printf("%-55s | %-15s | %s\n", $c['label'], '(not found)', '-');
        continue;
    }
    $status = $p['review_status'] ?? '(missing field)';
    $reasons = $p['review_rejection_reasons'] ?? [];
    $reasonsStr = is_array($reasons) ? implode(', ', array_map(
        fn ($r) => is_array($r) ? json_encode($r) : (string) $r,
        $reasons
    )) : (string) $reasons;
    printf(
        "%-55s | %-15s | %s\n",
        $c['label'],
        $status === '' ? '"" (empty)' : $status,
        $reasonsStr === '' ? '-' : $reasonsStr
    );
}

echo "\nNote: Meta review can take minutes to 24h. Re-run the fetch later:\n";
echo "  php tests/manual/test_catalog_review_experiment.php --refetch\n\n";

if (in_array('--refetch', $argv, true)) {
    echo "(--refetch mode: skipping creation, just printed current state above)\n";
}

echo "Created retailer_ids for cleanup:\n";
foreach ($created as $c) {
    echo "  - {$c['retailer_id']} (product_id={$c['product_id']})\n";
}
