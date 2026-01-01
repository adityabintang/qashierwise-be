<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Product;

// Test user_id = 1 (from logs)
$userId = 1;

$products = Product::where('user_id', $userId)
    ->where('is_active', true)
    ->orderBy('name', 'asc')
    ->limit(10)
    ->get(['id', 'name', 'price']);

echo 'Total products dengan limit(10): '.count($products)."\n\n";

foreach ($products as $index => $product) {
    echo ($index + 1).". {$product->name} - Rp ".number_format($product->price, 0, ',', '.')."\n";
}

echo "\n✅ Limit 10 bekerja dengan baik!\n";
