<?php

namespace Tests\Feature;

use App\Jobs\ProcessReservationPayment;
use App\Models\CatalogProduct;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

/**
 * When a paid reservation is turned into an Order for POS visibility, line
 * items are snapshotted from the Meta Catalog mirror (no master Product row):
 * product_id stays null while product_name + product_retailer_id are filled.
 */
class ReservationOrderFromCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_items_are_created_from_catalog_products(): void
    {
        $merchant = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $merchant->id]);

        $product = CatalogProduct::factory()->create([
            'user_id' => $merchant->id,
            'name' => 'Iga Bakar',
            'retailer_id' => 'SKU-IGA-001',
            'price' => 75000,
        ]);

        $reservation = Reservation::factory()->create([
            'user_id' => $merchant->id,
            'store_id' => $store->id,
            'customer_name' => 'Andi',
            'selected_products' => [['id' => $product->id, 'quantity' => 2]],
        ]);

        $job = new ProcessReservationPayment($reservation, 'success', null);

        $method = new ReflectionMethod($job, 'createOrderFromReservation');
        $method->setAccessible(true);
        $method->invoke($job, app(OrderService::class));

        $order = Order::where('store_id', $store->id)->where('source', 'reservation')->firstOrFail();
        $item = OrderItem::where('order_id', $order->id)->firstOrFail();

        $this->assertNull($item->product_id);
        $this->assertSame('Iga Bakar', $item->product_name);
        $this->assertSame('SKU-IGA-001', $item->product_retailer_id);
        $this->assertSame(2, $item->quantity);
        $this->assertEquals(75000, (float) $item->unit_price);
        $this->assertEquals(150000, (float) $item->subtotal);
    }
}
