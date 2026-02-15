<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Category;
use App\Models\Product;
use App\Models\User;

echo "=== CHECKING KASIR USER DATA ACCESS ===\n\n";

$kasir = User::where('email', 'kasir@gmail.com')->first();
echo "Kasir User ID: {$kasir->id}\n";
echo 'Is Master Admin: '.($kasir->is_master_admin ? 'YES' : 'NO')."\n";

$posUser = $kasir->posUsers()->with('store')->first();
if ($posUser) {
    echo "POS User ID: {$posUser->id}\n";
    echo "POS User Store ID: {$posUser->store_id}\n";
    if ($posUser->store) {
        echo "Store ID: {$posUser->store->id}\n";
        echo "Store User ID (Master Admin): {$posUser->store->user_id}\n";
        echo "Store Name: {$posUser->store->name}\n";
    } else {
        echo "❌ NO STORE FOUND!\n";
    }
} else {
    echo "❌ NO POS USER RECORD!\n";
}

echo "\nEffective User ID: ".$kasir->getEffectiveUserId()."\n";

$admin = User::where('email', 'admin@qashierwise.com')->first();
echo "\nAdmin User ID: {$admin->id}\n";

$productCount = Product::where('user_id', $admin->id)->count();
$categoryCount = Category::where('user_id', $admin->id)->count();

echo "\nProducts owned by Admin (ID {$admin->id}): {$productCount}\n";
echo "Categories owned by Admin (ID {$admin->id}): {$categoryCount}\n";

echo "\n=== TESTING DATA ACCESS FOR KASIR ===\n";
$effectiveId = $kasir->getEffectiveUserId();
echo "Using Effective User ID: {$effectiveId}\n";

$kasirProducts = Product::where('user_id', $effectiveId)->count();
$kasirCategories = Category::where('user_id', $effectiveId)->count();

echo "Products visible to Kasir: {$kasirProducts}\n";
echo "Categories visible to Kasir: {$kasirCategories}\n";

if ($kasirProducts === 0 || $kasirCategories === 0) {
    echo "\n⚠️ PROBLEM: Kasir cannot see data!\n";
    echo "Expected: Effective User ID ({$effectiveId}) should match Admin ID ({$admin->id})\n";
    if ($effectiveId !== $admin->id) {
        echo "❌ MISMATCH! Effective User ID is wrong!\n";
    }
}
