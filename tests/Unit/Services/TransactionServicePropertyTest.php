<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\TransactionService;
use Carbon\Carbon;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for TransactionService
 *
 * Feature: point-of-sale
 */
class TransactionServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    protected TransactionService $transactionService;

    protected Store $store;

    protected PosUser $posUser;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transactionService = new TransactionService;

        $uniqueId = uniqid();

        // Create store
        $this->store = Store::create([
            'name' => 'Test Store',
            'code' => 'TST-'.$uniqueId,
            'address' => 'Test Address',
            'is_active' => true,
        ]);

        $user = User::create([
            'name' => 'Test User',
            'email' => "test-{$uniqueId}@example.com",
            'password' => bcrypt('password'),
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
     * Helper to create a completed order with a specific order number and date
     */
    protected function createCompletedOrder(string $orderNumber, Carbon $date, float $total): Order
    {
        $order = new Order([
            'store_id' => $this->store->id,
            'pos_user_id' => $this->posUser->id,
            'order_number' => $orderNumber,
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
     * Feature: point-of-sale, Property 17: Transaction Search Accuracy
     * Validates: Requirements 9.2
     *
     * For any transaction search by order_number, all returned results SHALL
     * contain the exact order_number.
     */
    #[Test]
    public function search_by_order_number_returns_only_matching_transactions(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of matching orders (1-5)
                Generators::choose(1, 5),
                // Number of non-matching orders (1-5)
                Generators::choose(1, 5)
            )
            ->then(function (int $matchingCount, int $nonMatchingCount) {
                // Clear any existing orders
                Order::query()->delete();

                $testDate = Carbon::now();
                $searchPattern = 'SEARCH-'.uniqid();

                // Create orders that should match the search
                $matchingOrderNumbers = [];
                for ($i = 0; $i < $matchingCount; $i++) {
                    $orderNumber = $searchPattern.'-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT);
                    $this->createCompletedOrder($orderNumber, $testDate, rand(1000, 50000) / 100);
                    $matchingOrderNumbers[] = $orderNumber;
                }

                // Create orders that should NOT match the search
                for ($i = 0; $i < $nonMatchingCount; $i++) {
                    $orderNumber = 'OTHER-'.uniqid().'-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT);
                    $this->createCompletedOrder($orderNumber, $testDate, rand(1000, 50000) / 100);
                }

                // Search for transactions with the pattern
                $results = $this->transactionService->searchByOrderNumber($searchPattern);

                // Property: All returned results should contain the search pattern
                $this->assertCount(
                    $matchingCount,
                    $results,
                    "Search should return exactly {$matchingCount} matching transactions"
                );

                foreach ($results as $transaction) {
                    $this->assertStringContainsString(
                        $searchPattern,
                        $transaction->order_number,
                        'All returned transactions should contain the search pattern in order_number'
                    );

                    $this->assertContains(
                        $transaction->order_number,
                        $matchingOrderNumbers,
                        'Returned transaction order_number should be in the list of matching orders'
                    );
                }
            });
    }

    /**
     * Feature: point-of-sale, Property 18: Transaction Date Filter Consistency
     * Validates: Requirements 9.3
     *
     * For any transaction filter by date, all returned transactions SHALL have
     * created_at within the specified date.
     */
    #[Test]
    public function filter_by_date_returns_only_transactions_from_that_date(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Number of orders on target date (1-5)
                Generators::choose(1, 5),
                // Number of orders on other dates (1-5)
                Generators::choose(1, 5)
            )
            ->then(function (int $targetDateCount, int $otherDateCount) {
                // Clear any existing orders
                Order::query()->delete();

                $targetDate = Carbon::now()->subDays(3);
                $otherDate1 = Carbon::now()->subDays(5);
                $otherDate2 = Carbon::now()->subDays(1);

                // Create orders on the target date
                for ($i = 0; $i < $targetDateCount; $i++) {
                    $orderNumber = 'TARGET-'.uniqid().'-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT);
                    $this->createCompletedOrder($orderNumber, $targetDate, rand(1000, 50000) / 100);
                }

                // Create orders on other dates
                for ($i = 0; $i < $otherDateCount; $i++) {
                    $orderNumber = 'OTHER-'.uniqid().'-'.str_pad($i + 1, 4, '0', STR_PAD_LEFT);
                    // Alternate between two other dates
                    $date = ($i % 2 === 0) ? $otherDate1 : $otherDate2;
                    $this->createCompletedOrder($orderNumber, $date, rand(1000, 50000) / 100);
                }

                // Filter transactions by target date
                $results = $this->transactionService->filterByDate($targetDate);

                // Property: All returned results should have created_at on the target date
                $this->assertCount(
                    $targetDateCount,
                    $results,
                    "Filter should return exactly {$targetDateCount} transactions from target date"
                );

                foreach ($results as $transaction) {
                    $this->assertEquals(
                        $targetDate->toDateString(),
                        $transaction->created_at->toDateString(),
                        'All returned transactions should have created_at on the target date'
                    );
                }
            });
    }
}
