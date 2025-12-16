<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Testing POS Services ===\n\n";

// 1. Create Category first
echo "1. Creating Category...\n";
$category = \App\Models\Category::firstOrCreate(
    ['name' => 'Test Category'],
    ['slug' => 'test-category', 'is_active' => true]
);
echo "   Category ID: {$category->id}, Name: {$category->name}, Slug: {$category->slug}\n\n";

// 2. Test ProductService
echo "2. Testing ProductService...\n";
$productService = app(\App\Services\ProductService::class);
$product = $productService->create([
    'name' => 'Test Product ' . time(),
    'category_id' => $category->id,
    'price' => 15000,
    'stock_quantity' => 50
]);
echo "   Product created - ID: {$product->id}, Name: {$product->name}, SKU: {$product->sku}, Price: {$product->price}, Stock: {$product->stock_quantity}\n\n";

// 3. Create Store and PosUser for OrderService
echo "3. Creating Store and PosUser...\n";
$store = \App\Models\Store::firstOrCreate(
    ['code' => 'TST001'],
    ['name' => 'Test Store', 'address' => 'Test Address', 'is_active' => true]
);
echo "   Store ID: {$store->id}, Name: {$store->name}, Code: {$store->code}\n";

$role = \App\Models\Role::firstOrCreate(
    ['name' => 'Cashier'],
    ['permissions' => json_encode(['orders.create', 'orders.view'])]
);
echo "   Role ID: {$role->id}, Name: {$role->name}\n";

$user = \App\Models\User::first();
if (!$user) {
    $user = \App\Models\User::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password')
    ]);
}
echo "   User ID: {$user->id}, Name: {$user->name}\n";

$posUser = \App\Models\PosUser::firstOrCreate(
    ['user_id' => $user->id, 'store_id' => $store->id],
    ['role_id' => $role->id, 'is_active' => true]
);
echo "   PosUser ID: {$posUser->id}\n\n";

// 4. Test OrderService
echo "4. Testing OrderService...\n";
$orderService = app(\App\Services\OrderService::class);
$order = $orderService->create([
    'store_id' => $store->id,
    'pos_user_id' => $posUser->id
]);
echo "   Order created - ID: {$order->id}, Order Number: {$order->order_number}, Status: {$order->status}\n";

$orderService->addItem($order, $product, 2);
$order->refresh();
echo "   Added 2x {$product->name} to order\n";

$totals = $orderService->calculateTotals($order);
echo "   Totals - Subtotal: {$totals['subtotal']}, Tax: {$totals['tax_amount']}, Total: {$totals['total']}\n\n";

// 5. Test PaymentService
echo "5. Testing PaymentService...\n";
$paymentService = app(\App\Services\PaymentService::class);
$change = $paymentService->calculateChange(50000, 30000);
echo "   Change calculation: Paid 50000, Total 30000, Change: {$change}\n\n";

// 6. Verify Inventory Updates
echo "6. Testing Inventory Updates...\n";
$initialStock = $product->stock_quantity;
echo "   Initial stock: {$initialStock}\n";

$orderService->complete($order);
$product->refresh();
$order->refresh();
echo "   Order completed - Status: {$order->status}\n";
echo "   Stock after completion: {$product->stock_quantity}\n";
echo "   Stock reduced by: " . ($initialStock - $product->stock_quantity) . " (expected: 2)\n\n";

echo "=== All Service Tests Completed Successfully! ===\n";
