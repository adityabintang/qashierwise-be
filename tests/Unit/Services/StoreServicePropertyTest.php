<?php

namespace Tests\Unit\Services;

use App\Models\PosUser;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use App\Services\StoreService;
use Eris\Generators;
use Eris\TestTrait;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Property-based tests for StoreService
 *
 * Feature: point-of-sale
 */
class StoreServicePropertyTest extends TestCase
{
    use RefreshDatabase;
    use TestTrait;

    protected StoreService $storeService;

    protected OrderService $orderService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storeService = new StoreService;
        $this->orderService = new OrderService;
    }

    /**
     * Helper to create a PosUser for a store
     */
    protected function createPosUser(Store $store, ?User $user = null): PosUser
    {
        // Use existing user if provided, otherwise create a new one
        if ($user === null) {
            $uniqueId = uniqid();
            $user = User::create([
                'name' => 'Test User',
                'email' => "test-{$uniqueId}@example.com",
                'password' => bcrypt('password'),
            ]);
        }

        // Get or create Spatie role
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Cashier', 'guard_name' => 'sanctum'],
            []
        );

        // Assign role to user
        $user->syncRoles([$role->name]);

        return PosUser::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    /**
     * Feature: point-of-sale, Property 11: Inactive Store Order Rejection
     * Validates: Requirements 5.3
     *
     * For any Store with is_active = false, attempting to create a new Order
     * SHALL be rejected.
     */
    #[Test]
    public function inactive_store_rejects_order_creation(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Store name (non-empty string)
                Generators::suchThat(
                    fn ($s) => strlen(trim($s)) > 0 && strlen($s) <= 100,
                    Generators::string()
                ),
                // Store address
                Generators::string()
            )
            ->then(function (string $name, string $address) {
                // Create a user first (stores require user_id)
                $user = User::create([
                    'name' => 'Test User',
                    'email' => 'test-'.uniqid().'@example.com',
                    'password' => bcrypt('password'),
                ]);

                // Create an active store first with user_id
                $store = $this->storeService->create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'address' => $address,
                    'is_active' => true,
                ]);

                // Create a PosUser for the store, reusing the user
                $posUser = $this->createPosUser($store, $user);

                // Deactivate the store
                $this->storeService->deactivate($store);
                $store->refresh();

                // Property: Store should be inactive
                $this->assertFalse(
                    $store->is_active,
                    'Store should be inactive after deactivation'
                );

                // Property: canAcceptOrders should return false
                $this->assertFalse(
                    $this->storeService->canAcceptOrders($store),
                    'Inactive store should not accept orders'
                );

                // Property: validateForOrderCreation should throw exception
                $exceptionThrown = false;
                try {
                    $this->storeService->validateForOrderCreation($store);
                } catch (InvalidArgumentException $e) {
                    $exceptionThrown = true;
                    $this->assertEquals(
                        'Cannot create order at inactive store',
                        $e->getMessage()
                    );
                }
                $this->assertTrue(
                    $exceptionThrown,
                    'validateForOrderCreation should throw exception for inactive store'
                );

                // Property: OrderService.create should throw exception for inactive store
                $orderExceptionThrown = false;
                try {
                    $this->orderService->create([
                        'store_id' => $store->id,
                        'pos_user_id' => $posUser->id,
                    ]);
                } catch (InvalidArgumentException $e) {
                    $orderExceptionThrown = true;
                    $this->assertEquals(
                        'Cannot create order at inactive store',
                        $e->getMessage()
                    );
                }
                $this->assertTrue(
                    $orderExceptionThrown,
                    'OrderService.create should throw exception for inactive store'
                );
            });
    }

    /**
     * Additional property test: Active stores should accept orders
     * This is the inverse property to ensure the system works correctly
     */
    #[Test]
    public function active_store_accepts_order_creation(): void
    {
        $this
            ->limitTo(100)
            ->forAll(
                // Store name (non-empty string)
                Generators::suchThat(
                    fn ($s) => strlen(trim($s)) > 0 && strlen($s) <= 100,
                    Generators::string()
                ),
                // Store address
                Generators::string()
            )
            ->then(function (string $name, string $address) {
                // Create a user first (stores require user_id)
                $user = User::create([
                    'name' => 'Test User',
                    'email' => 'test-'.uniqid().'@example.com',
                    'password' => bcrypt('password'),
                ]);

                // Create an active store with user_id
                $store = $this->storeService->create([
                    'user_id' => $user->id,
                    'name' => $name,
                    'address' => $address,
                    'is_active' => true,
                ]);

                // Create a PosUser for the store, reusing the user
                $posUser = $this->createPosUser($store, $user);

                // Property: Store should be active
                $this->assertTrue(
                    $store->is_active,
                    'Store should be active'
                );

                // Property: canAcceptOrders should return true
                $this->assertTrue(
                    $this->storeService->canAcceptOrders($store),
                    'Active store should accept orders'
                );

                // Property: validateForOrderCreation should not throw exception
                $result = $this->storeService->validateForOrderCreation($store);
                $this->assertTrue($result, 'validateForOrderCreation should return true for active store');

                // Property: OrderService.create should succeed for active store
                $order = $this->orderService->create([
                    'store_id' => $store->id,
                    'pos_user_id' => $posUser->id,
                ]);

                $this->assertNotNull($order, 'Order should be created for active store');
                $this->assertEquals($store->id, $order->store_id, 'Order should belong to the store');
            });
    }
}
