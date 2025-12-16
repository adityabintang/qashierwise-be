<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PosUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use App\Services\OrderService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for OrderService
 * 
 * Feature: point-of-sale
 */
class OrderServicePropertyTest extends TestCase
{
    use TestTrait;
    use RefreshDatabase;

    protected OrderService $orderService;
    protected Store $store;
    protected PosUser $posUser;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService();
        
        $uniqueId = uniqid();
        
        // Create required entities for testing
        $this->store = Store::create([
            'name' => 'Test Store',
            'code' => 'TST-' . $uniqueId,
            'address' => 'Test Address',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => "test-{$uniqueId}@example.com",
            'password' => bcrypt('password'),
        ]);

        $role = Role::create([
            'name' => 'Cashier-' . $uniqueId,
            'permissions' => ['orders.create', 'orders.update'],
        ]);

        $this->posUser = PosUser::create([
            'user_id' => $user->id,
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . $uniqueId,
            'is_active' => true,
        ]);
    }


    /**
     * Helper to create a product with given price and stock
     */
    protected function createProduct(float $price, int $stock): Product
    {
        static $counter = 0;
        $counter++;
        
        return Product::create([
            'category_id' => $this->category->id,
            'name' => "Product {$counter}",
            'sku' => "SKU-{$counter}-" . uniqid(),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    /**
     * Feature: point-of-sale, Property 3: Order Total Invariant
     * Validates: Requirements 3.2, 3.3, 3.4
     * 
     * For any Order with items, the total SHALL always equal 
     * (sum of all item subtotals) + tax_amount - discount_amount.
     */
    #[Test]
    public function order_total_invariant_holds_for_any_items_and_discount(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of items (1-5)
                Generators::choose(1, 5),
                // Discount percentage (0-50%)
                Generators::choose(0, 50)
            )
            ->then(function (int $numItems, int $discountPercent) {
                // Create order
                $order = $this->orderService->create([
                    'store_id' => $this->store->id,
                    'pos_user_id' => $this->posUser->id,
                ]);

                // Add random items
                $expectedSubtotal = 0;
                for ($i = 0; $i < $numItems; $i++) {
                    // Random price between 1000 and 100000 (in cents, then convert)
                    $price = rand(1000, 100000) / 100;
                    $quantity = rand(1, 5);
                    
                    $product = $this->createProduct($price, 1000);
                    $this->orderService->addItem($order, $product, $quantity);
                    
                    $expectedSubtotal += $price * $quantity;
                }

                $order->refresh();

                // Apply discount if any
                $discountAmount = 0;
                if ($discountPercent > 0 && $order->subtotal > 0) {
                    $discountAmount = round($order->subtotal * ($discountPercent / 100), 2);
                    $this->orderService->applyDiscount($order, $discountAmount);
                    $order->refresh();
                }

                // Calculate expected values
                $taxableAmount = max(0, $order->subtotal - $order->discount_amount);
                $expectedTax = round($taxableAmount * OrderService::DEFAULT_TAX_RATE, 2);
                $expectedTotal = $order->subtotal + $expectedTax - $order->discount_amount;

                // Property: total = subtotal + tax_amount - discount_amount
                $actualTotal = (float) $order->subtotal + (float) $order->tax_amount - (float) $order->discount_amount;
                
                $this->assertEqualsWithDelta(
                    $expectedTotal,
                    (float) $order->total,
                    0.01,
                    "Order total should equal subtotal + tax - discount"
                );

                $this->assertEqualsWithDelta(
                    $actualTotal,
                    (float) $order->total,
                    0.01,
                    "Order total invariant: total = subtotal + tax_amount - discount_amount"
                );
            });
    }

    /**
     * Feature: point-of-sale, Property 5: Inventory Consistency on Order Completion
     * Validates: Requirements 3.5
     * 
     * For any completed Order, the stock_quantity of each product SHALL be 
     * decreased by the ordered quantity.
     */
    #[Test]
    public function inventory_decreases_on_order_completion(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of items (1-3)
                Generators::choose(1, 3),
                // Quantity per item (1-5)
                Generators::choose(1, 5)
            )
            ->then(function (int $numItems, int $baseQuantity) {
                // Create order
                $order = $this->orderService->create([
                    'store_id' => $this->store->id,
                    'pos_user_id' => $this->posUser->id,
                ]);

                // Track initial stock and ordered quantities
                $stockBefore = [];
                $orderedQuantities = [];

                // Add items to order
                for ($i = 0; $i < $numItems; $i++) {
                    $price = rand(1000, 50000) / 100;
                    $initialStock = 1000; // Ensure enough stock
                    $quantity = $baseQuantity + $i; // Vary quantity slightly
                    
                    $product = $this->createProduct($price, $initialStock);
                    $stockBefore[$product->id] = $initialStock;
                    $orderedQuantities[$product->id] = $quantity;
                    
                    $this->orderService->addItem($order, $product, $quantity);
                }

                // Complete the order
                $this->orderService->complete($order);

                // Property: stock should be reduced by ordered quantity for each product
                foreach ($orderedQuantities as $productId => $orderedQty) {
                    $product = Product::find($productId);
                    $expectedStock = $stockBefore[$productId] - $orderedQty;
                    
                    $this->assertEquals(
                        $expectedStock,
                        $product->stock_quantity,
                        "Product {$productId} stock should be reduced by {$orderedQty}"
                    );
                }
            });
    }

    /**
     * Feature: point-of-sale, Property 6: Inventory Restoration on Order Cancellation
     * Validates: Requirements 3.6
     * 
     * For any cancelled Order that was previously completed, the stock_quantity 
     * of each product SHALL be restored to the pre-completion value.
     */
    #[Test]
    public function inventory_restores_on_order_cancellation(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of items (1-3)
                Generators::choose(1, 3),
                // Quantity per item (1-5)
                Generators::choose(1, 5)
            )
            ->then(function (int $numItems, int $baseQuantity) {
                // Create order
                $order = $this->orderService->create([
                    'store_id' => $this->store->id,
                    'pos_user_id' => $this->posUser->id,
                ]);

                // Track initial stock
                $initialStock = [];
                $orderedQuantities = [];

                // Add items to order
                for ($i = 0; $i < $numItems; $i++) {
                    $price = rand(1000, 50000) / 100;
                    $stock = 1000;
                    $quantity = $baseQuantity + $i;
                    
                    $product = $this->createProduct($price, $stock);
                    $initialStock[$product->id] = $stock;
                    $orderedQuantities[$product->id] = $quantity;
                    
                    $this->orderService->addItem($order, $product, $quantity);
                }

                // Complete the order (reduces stock)
                $this->orderService->complete($order);

                // Verify stock was reduced
                foreach ($orderedQuantities as $productId => $orderedQty) {
                    $product = Product::find($productId);
                    $this->assertEquals(
                        $initialStock[$productId] - $orderedQty,
                        $product->stock_quantity,
                        "Stock should be reduced after completion"
                    );
                }

                // Cancel the order (should restore stock)
                $order->refresh();
                $this->orderService->cancel($order);

                // Property: stock should be restored to initial value
                foreach ($initialStock as $productId => $originalStock) {
                    $product = Product::find($productId);
                    
                    $this->assertEquals(
                        $originalStock,
                        $product->stock_quantity,
                        "Product {$productId} stock should be restored to {$originalStock} after cancellation"
                    );
                }
            });
    }

    /**
     * Helper to create a table for the store
     */
    protected function createTable(): Table
    {
        static $tableCounter = 0;
        $tableCounter++;
        
        return Table::create([
            'store_id' => $this->store->id,
            'number' => "T{$tableCounter}",
            'capacity' => 4,
            'status' => Table::STATUS_AVAILABLE,
        ]);
    }

    /**
     * Feature: point-of-sale, Property 12: Table Status State Transition
     * Validates: Requirements 6.2, 6.3
     * 
     * For any Table, assigning an Order SHALL change status to 'occupied', 
     * and completing/cancelling that Order SHALL change status back to 'available'.
     */
    #[Test]
    public function table_status_transitions_correctly_with_orders(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Whether to complete (true) or cancel (false) the order
                Generators::bool()
            )
            ->then(function (bool $shouldComplete) {
                // Create a table
                $table = $this->createTable();
                
                // Verify initial status is available
                $this->assertEquals(
                    Table::STATUS_AVAILABLE,
                    $table->status,
                    "Table should start as available"
                );

                // Create order with table assignment
                $order = $this->orderService->create([
                    'store_id' => $this->store->id,
                    'pos_user_id' => $this->posUser->id,
                    'table_id' => $table->id,
                ]);

                // Property: Table status should be 'occupied' after order creation
                $table->refresh();
                $this->assertEquals(
                    Table::STATUS_OCCUPIED,
                    $table->status,
                    "Table should be occupied after order is assigned"
                );

                // Add an item to the order
                $product = $this->createProduct(100.00, 1000);
                $this->orderService->addItem($order, $product, 1);

                if ($shouldComplete) {
                    // Complete the order
                    $this->orderService->complete($order);
                } else {
                    // Cancel the order
                    $this->orderService->cancel($order);
                }

                // Property: Table status should be 'available' after order completion/cancellation
                $table->refresh();
                $this->assertEquals(
                    Table::STATUS_AVAILABLE,
                    $table->status,
                    "Table should be available after order is " . ($shouldComplete ? "completed" : "cancelled")
                );
            });
    }
}
