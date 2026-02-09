<?php

namespace Tests\Unit\Services;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PosUser;
use App\Models\Product;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for PaymentService
 *
 * Feature: point-of-sale
 */
class PaymentServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    protected PaymentService $paymentService;

    protected OrderService $orderService;

    protected Store $store;

    protected PosUser $posUser;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderService = new OrderService;

        // PaymentService requires OrderService, SubMerchantService, and BalanceService
        $this->paymentService = new PaymentService(
            $this->orderService,
            app(\App\Services\SubMerchantService::class),
            app(\App\Services\BalanceService::class)
        );

        $uniqueId = uniqid();

        // Create user first, then store (since stores require user_id)
        $user = User::create([
            'name' => 'Test User',
            'email' => "test-{$uniqueId}@example.com",
            'password' => bcrypt('password'),
        ]);

        // Create required entities for testing
        $this->store = Store::create([
            'user_id' => $user->id,
            'name' => 'Test Store',
            'code' => 'TST-'.$uniqueId,
            'address' => 'Test Address',
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
            'category_id' => $this->category->id,
            'name' => "Product {$counter}",
            'sku' => "SKU-{$counter}-".uniqid(),
            'price' => $price,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create an order with items
     */
    protected function createOrderWithItems(int $numItems = 1): Order
    {
        $order = $this->orderService->create([
            'store_id' => $this->store->id,
            'pos_user_id' => $this->posUser->id,
        ]);

        for ($i = 0; $i < $numItems; $i++) {
            $price = rand(1000, 50000) / 100;
            $product = $this->createProduct($price, 1000);
            $this->orderService->addItem($order, $product, rand(1, 3));
        }

        return $order->fresh();
    }

    /**
     * Feature: point-of-sale, Property 7: Payment Change Calculation
     * Validates: Requirements 4.2
     *
     * For any cash Payment where amount_paid >= order_total,
     * the calculated change SHALL equal amount_paid - order_total.
     */
    #[Test]
    public function change_calculation_equals_amount_paid_minus_order_total(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Order total in cents (1000 to 1000000 = $10 to $10000)
                Generators::choose(1000, 1000000),
                // Extra amount paid in cents (0 to 100000 = $0 to $1000)
                Generators::choose(0, 100000)
            )
            ->then(function (int $orderTotalCents, int $extraCents) {
                $orderTotal = $orderTotalCents / 100;
                $amountPaid = $orderTotal + ($extraCents / 100);

                $change = $this->paymentService->calculateChange($amountPaid, $orderTotal);

                // Property: change = amount_paid - order_total
                $expectedChange = round($amountPaid - $orderTotal, 2);

                $this->assertEqualsWithDelta(
                    $expectedChange,
                    $change,
                    0.01,
                    "Change should equal amount_paid ({$amountPaid}) - order_total ({$orderTotal})"
                );

                // Additional invariant: change should never be negative
                $this->assertGreaterThanOrEqual(
                    0,
                    $change,
                    'Change should never be negative when amount_paid >= order_total'
                );
            });
    }

    /**
     * Feature: point-of-sale, Property 8: Split Payment Total Invariant
     * Validates: Requirements 4.4, 4.5
     *
     * For any Order with split payments, the sum of all payment amounts
     * SHALL equal or exceed the order total.
     */
    #[Test]
    public function split_payment_total_equals_or_exceeds_order_total(): void
    {
        $methods = [
            Payment::METHOD_CASH,
            Payment::METHOD_CARD,
            Payment::METHOD_TRANSFER,
            Payment::METHOD_QRIS,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                // Number of split payments (2-4)
                Generators::choose(2, 4),
                // Number of items in order (1-3)
                Generators::choose(1, 3)
            )
            ->then(function (int $numPayments, int $numItems) use ($methods) {
                // Create order with items
                $order = $this->createOrderWithItems($numItems);
                $orderTotal = (float) $order->total;

                // Skip if order total is zero
                if ($orderTotal <= 0) {
                    return;
                }

                // Generate split payments that cover the order total
                $payments = [];
                $remainingAmount = $orderTotal;

                for ($i = 0; $i < $numPayments; $i++) {
                    $isLastPayment = ($i === $numPayments - 1);

                    if ($isLastPayment) {
                        // Last payment covers remaining amount (plus possible extra)
                        $amount = $remainingAmount + (rand(0, 1000) / 100);
                    } else {
                        // Random portion of remaining amount
                        $maxPortion = $remainingAmount / ($numPayments - $i);
                        $amount = max(0.01, rand(1, (int) ($maxPortion * 100)) / 100);
                        $remainingAmount -= $amount;
                    }

                    $payments[] = [
                        'method' => $methods[array_rand($methods)],
                        'amount' => round($amount, 2),
                    ];
                }

                // Process split payment
                $createdPayments = $this->paymentService->splitPayment($order, $payments);

                // Calculate total paid
                $totalPaid = $createdPayments->sum('amount');

                // Property: sum of all payment amounts >= order total
                // Use bccomp for precise decimal comparison to avoid floating point issues
                $comparison = bccomp((string) $totalPaid, (string) $orderTotal, 2);
                $this->assertGreaterThanOrEqual(
                    0,
                    $comparison,
                    "Total paid ({$totalPaid}) should be >= order total ({$orderTotal})"
                );

                // Verify order status is paid
                $order->refresh();
                $this->assertEquals(
                    Order::STATUS_PAID,
                    $order->status,
                    'Order should be marked as paid when payments cover total'
                );

                // Verify number of payments created
                $this->assertCount(
                    $numPayments,
                    $createdPayments,
                    "Should create exactly {$numPayments} payment records"
                );
            });
    }
}
