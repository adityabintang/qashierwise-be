<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\PosUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReportService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for ReportService
 *
 * Feature: point-of-sale
 */
class ReportServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    protected ReportService $reportService;

    protected OrderService $orderService;

    protected Store $store;

    protected Store $store2;

    protected PosUser $posUser;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reportService = new ReportService;
        $this->orderService = new OrderService;

        $uniqueId = uniqid();

        // Create user first (stores require user_id)
        $user = User::create([
            'name' => 'Test User',
            'email' => "test-{$uniqueId}@example.com",
            'password' => bcrypt('password'),
        ]);

        // Create first store with user_id
        $this->store = Store::create([
            'user_id' => $user->id,
            'name' => 'Test Store 1',
            'code' => 'TST1-'.$uniqueId,
            'address' => 'Test Address 1',
            'is_active' => true,
        ]);

        // Create second store for filtering tests with user_id
        $this->store2 = Store::create([
            'user_id' => $user->id,
            'name' => 'Test Store 2',
            'code' => 'TST2-'.$uniqueId,
            'address' => 'Test Address 2',
            'is_active' => true,
        ]);

        // Get or create Spatie role
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Cashier', 'guard_name' => 'sanctum'],
            []
        );

        // Assign role to user
        $user->syncRoles([$role->name]);

        $this->posUser = PosUser::create([
            'user_id' => $user->id,
            'store_id' => $this->store->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-'.$uniqueId,
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
            'user_id' => $this->posUser->user_id,
            'category_id' => $this->category->id,
            'name' => "Product {$counter}",
            'sku' => "SKU-{$counter}-".uniqid(),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a completed order with items at a specific store and date
     */
    protected function createCompletedOrder(Store $store, Carbon $date, float $total): Order
    {
        $order = new Order([
            'store_id' => $store->id,
            'pos_user_id' => $this->posUser->id,
            'order_number' => 'ORD-'.uniqid(),
            'status' => Order::STATUS_PAID,
            'subtotal' => $total,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => $total,
        ]);

        // Manually set timestamps to the desired date
        $order->created_at = $date;
        $order->updated_at = $date;
        $order->save();

        return $order;
    }

    /**
     * Feature: point-of-sale, Property 15: Report Date Range Aggregation
     * Validates: Requirements 8.1, 8.2
     *
     * For any date range report, the total sales SHALL equal the sum of all
     * individual daily totals within that range.
     */
    #[Test]
    public function date_range_total_equals_sum_of_daily_totals(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of days in range (1-7)
                Generators::choose(1, 7),
                // Number of orders per day (0-3)
                Generators::choose(0, 3)
            )
            ->then(function (int $numDays, int $ordersPerDay) {
                // Clear any existing orders
                Order::query()->delete();

                $startDate = Carbon::now()->subDays($numDays + 1);
                $endDate = $startDate->copy()->addDays($numDays - 1);

                $expectedTotalSales = 0.0;

                // Create orders for each day in the range
                $currentDate = $startDate->copy();
                while ($currentDate->lte($endDate)) {
                    for ($i = 0; $i < $ordersPerDay; $i++) {
                        // Random order total between 10 and 1000
                        $orderTotal = rand(1000, 100000) / 100;
                        $this->createCompletedOrder($this->store, $currentDate->copy(), $orderTotal);
                        $expectedTotalSales += $orderTotal;
                    }
                    $currentDate->addDay();
                }

                // Get the range report
                $rangeReport = $this->reportService->salesByRange($startDate, $endDate);

                // Calculate sum of daily totals from breakdown
                $sumOfDailyTotals = 0.0;
                foreach ($rangeReport['daily_breakdown'] as $dailyReport) {
                    $sumOfDailyTotals += $dailyReport['total_sales'];
                }

                // Property: total_sales should equal sum of daily totals
                $this->assertEqualsWithDelta(
                    $rangeReport['total_sales'],
                    $sumOfDailyTotals,
                    0.01,
                    'Range total sales should equal sum of daily totals'
                );

                // Also verify against expected total
                $this->assertEqualsWithDelta(
                    $expectedTotalSales,
                    $rangeReport['total_sales'],
                    0.01,
                    'Range total sales should equal expected total from created orders'
                );
            });
    }

    /**
     * Feature: point-of-sale, Property 16: Report Store Filter Consistency
     * Validates: Requirements 8.3
     *
     * For any report filtered by store_id, all included transactions SHALL
     * belong to that store.
     */
    #[Test]
    public function store_filter_returns_only_matching_store_transactions(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of orders for store 1 (1-5)
                Generators::choose(1, 5),
                // Number of orders for store 2 (1-5)
                Generators::choose(1, 5)
            )
            ->then(function (int $ordersStore1, int $ordersStore2) {
                // Clear any existing orders
                Order::query()->delete();

                $testDate = Carbon::now();

                $store1Total = 0.0;
                $store2Total = 0.0;

                // Create orders for store 1
                for ($i = 0; $i < $ordersStore1; $i++) {
                    $orderTotal = rand(1000, 50000) / 100;
                    $this->createCompletedOrder($this->store, $testDate, $orderTotal);
                    $store1Total += $orderTotal;
                }

                // Create orders for store 2
                for ($i = 0; $i < $ordersStore2; $i++) {
                    $orderTotal = rand(1000, 50000) / 100;
                    $this->createCompletedOrder($this->store2, $testDate, $orderTotal);
                    $store2Total += $orderTotal;
                }

                // Get report filtered by store 1
                $store1Report = $this->reportService->dailySales($testDate, $this->store->id);

                // Get report filtered by store 2
                $store2Report = $this->reportService->dailySales($testDate, $this->store2->id);

                // Get unfiltered report
                $allStoresReport = $this->reportService->dailySales($testDate);

                // Property: Store 1 filtered report should only include store 1 sales
                $this->assertEqualsWithDelta(
                    $store1Total,
                    $store1Report['total_sales'],
                    0.01,
                    'Store 1 filtered report should only include store 1 sales'
                );

                $this->assertEquals(
                    $this->store->id,
                    $store1Report['store_id'],
                    'Store 1 report should have store_id set to store 1'
                );

                // Property: Store 2 filtered report should only include store 2 sales
                $this->assertEqualsWithDelta(
                    $store2Total,
                    $store2Report['total_sales'],
                    0.01,
                    'Store 2 filtered report should only include store 2 sales'
                );

                $this->assertEquals(
                    $this->store2->id,
                    $store2Report['store_id'],
                    'Store 2 report should have store_id set to store 2'
                );

                // Property: Unfiltered report should include all stores
                $this->assertEqualsWithDelta(
                    $store1Total + $store2Total,
                    $allStoresReport['total_sales'],
                    0.01,
                    'Unfiltered report should include sales from all stores'
                );

                $this->assertNull(
                    $allStoresReport['store_id'],
                    'Unfiltered report should have null store_id'
                );

                // Property: Sum of filtered reports should equal unfiltered total
                $this->assertEqualsWithDelta(
                    $store1Report['total_sales'] + $store2Report['total_sales'],
                    $allStoresReport['total_sales'],
                    0.01,
                    'Sum of store-filtered reports should equal unfiltered total'
                );
            });
    }
}
