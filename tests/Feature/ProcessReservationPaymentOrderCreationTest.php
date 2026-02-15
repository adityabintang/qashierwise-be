<?php

namespace Tests\Feature;

use App\Jobs\ProcessReservationPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\QrisTransaction;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessReservationPaymentOrderCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Table $table;

    protected Product $product1;

    protected Product $product2;

    protected QrisTransaction $transaction;

    protected Reservation $reservation;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->user = User::factory()->create();
        $this->store = Store::factory()->create(['user_id' => $this->user->id]);
        $this->table = Table::factory()->create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
        ]);

        // Create products
        $this->product1 = Product::factory()->create([
            'name' => 'Nasi Goreng',
            'price' => 50000,
        ]);

        $this->product2 = Product::factory()->create([
            'name' => 'Mie Goreng',
            'price' => 45000,
        ]);

        // Create QRIS transaction
        $this->transaction = QrisTransaction::factory()
            ->settled()
            ->create([
                'provider_transaction_id' => 'QRIS-'.uniqid(),
                'order_id' => 'RES-TEST-'.uniqid(),
            ]);
    }

    public function test_order_is_created_when_reservation_payment_succeeds(): void
    {
        // Create reservation with selected products
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'John Doe',
            'phone' => '+6281234567890',
            'email' => 'john@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 4,
            'table_id' => $this->table->id,
            'selected_products' => [
                ['id' => $this->product1->id, 'name' => 'Nasi Goreng', 'price' => 50000, 'quantity' => 2],
                ['id' => $this->product2->id, 'name' => 'Mie Goreng', 'price' => 45000, 'quantity' => 1],
            ],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $this->transaction->id,
            'total_amount' => 145000,
            'paid_amount' => 0,
            'remaining_amount' => 145000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $this->assertDatabaseHas('reservations', [
            'id' => $this->reservation->id,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
        ]);

        // Execute job
        $job = new ProcessReservationPayment($this->reservation, 'success', $this->transaction);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        // Verify order was created
        $order = Order::where('store_id', $this->store->id)
            ->where('source', 'reservation')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_PAID, $order->status);
        $this->assertEquals('John Doe', $order->customer_name);
        $this->assertEquals('+6281234567890', $order->customer_phone);

        // Verify order items were created
        $this->assertCount(2, $order->items);

        $item1 = $order->items()->where('product_id', $this->product1->id)->first();
        $this->assertNotNull($item1);
        $this->assertEquals(2, $item1->quantity);
        $this->assertEquals(50000, $item1->unit_price);
        $this->assertEquals(100000, $item1->subtotal);

        $item2 = $order->items()->where('product_id', $this->product2->id)->first();
        $this->assertNotNull($item2);
        $this->assertEquals(1, $item2->quantity);
        $this->assertEquals(45000, $item2->unit_price);
        $this->assertEquals(45000, $item2->subtotal);

        // Verify totals are calculated correctly
        $subtotal = 100000 + 45000; // 145000
        $tax = round($subtotal * 0.11, 2); // 15950
        $expectedTotal = $subtotal + $tax; // 160950

        $this->assertEquals($subtotal, $order->subtotal);
        $this->assertEquals($tax, $order->tax_amount);
        $this->assertEquals($expectedTotal, $order->total);

        // Verify payment record was created
        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals($this->transaction->id, $payment->qris_transaction_id);
        $this->assertEquals(Payment::METHOD_QRIS, $payment->method);
        $this->assertEquals($expectedTotal, $payment->amount);
        $this->assertEquals(Payment::STATUS_PAID, $payment->status);
    }

    public function test_order_is_not_created_if_reservation_already_confirmed(): void
    {
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'Jane Doe',
            'phone' => '+6289876543210',
            'email' => 'jane@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 2,
            'table_id' => $this->table->id,
            'selected_products' => [
                ['id' => $this->product1->id, 'quantity' => 1],
            ],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $this->transaction->id,
            'total_amount' => 50000,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'status' => Reservation::STATUS_CONFIRMED,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $initialOrderCount = Order::count();

        $job = new ProcessReservationPayment($this->reservation, 'success', $this->transaction);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        // Verify no new order was created
        $this->assertEquals($initialOrderCount, Order::count());
    }

    public function test_order_handles_product_data_with_product_id_key(): void
    {
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'Test User',
            'phone' => '+6281234567890',
            'email' => 'test@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 2,
            'table_id' => $this->table->id,
            'selected_products' => [
                ['product_id' => $this->product1->id, 'qty' => 2],
            ],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $this->transaction->id,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $job = new ProcessReservationPayment($this->reservation, 'success', $this->transaction);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        $order = Order::where('store_id', $this->store->id)
            ->where('source', 'reservation')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $item = $order->items()->first();
        $this->assertEquals(2, $item->quantity);
        $this->assertEquals($this->product1->price, $item->unit_price);
    }

    public function test_order_handles_product_with_integer_id(): void
    {
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'Test User',
            'phone' => '+6281234567890',
            'email' => 'test@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 2,
            'table_id' => $this->table->id,
            'selected_products' => [$this->product1->id, $this->product2->id],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $this->transaction->id,
            'total_amount' => 95000,
            'paid_amount' => 0,
            'remaining_amount' => 95000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $job = new ProcessReservationPayment($this->reservation, 'success', $this->transaction);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        $order = Order::where('store_id', $this->store->id)
            ->where('source', 'reservation')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $this->assertCount(2, $order->items);
    }

    public function test_order_with_dp_payment_type(): void
    {
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'DP Customer',
            'phone' => '+6281234567890',
            'email' => 'dp@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 6,
            'table_id' => $this->table->id,
            'selected_products' => [
                ['id' => $this->product1->id, 'quantity' => 3],
                ['id' => $this->product2->id, 'quantity' => 2],
            ],
            'payment_type' => Reservation::PAYMENT_TYPE_DP,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $this->transaction->id,
            'total_amount' => 240000,
            'paid_amount' => 0,
            'remaining_amount' => 240000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $job = new ProcessReservationPayment($this->reservation, 'success', $this->transaction);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        $order = Order::where('store_id', $this->store->id)
            ->where('source', 'reservation')
            ->latest()
            ->first();

        $this->assertNotNull($order);
        $this->assertEquals(Order::STATUS_PAID, $order->status);
        // Order is created with all items regardless of payment type
        $this->assertCount(2, $order->items);
    }

    public function test_order_not_created_without_qris_transaction(): void
    {
        $this->reservation = Reservation::create([
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
            'customer_name' => 'Test User',
            'phone' => '+6281234567890',
            'email' => 'test@example.com',
            'reservation_date' => now()->addDays(1)->toDateString(),
            'guest_count' => 2,
            'table_id' => $this->table->id,
            'selected_products' => [
                ['id' => $this->product1->id, 'quantity' => 1],
            ],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => null,
            'total_amount' => 50000,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-'.uniqid(),
        ]);

        $job = new ProcessReservationPayment($this->reservation, 'success', null);
        $job->handle(app(ReservationService::class), app(OrderService::class));

        $order = Order::where('store_id', $this->store->id)
            ->where('source', 'reservation')
            ->latest()
            ->first();

        // Order is created but without payment record
        $this->assertNotNull($order);
        $payment = Payment::where('order_id', $order->id)->first();
        $this->assertNull($payment);
    }
}
