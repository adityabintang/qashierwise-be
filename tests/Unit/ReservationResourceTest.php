<?php

namespace Tests\Unit;

use App\Http\Resources\ReservationResource;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_uses_dp_confirmed_label_for_confirmed_dp_payment(): void
    {
        $reservation = Reservation::factory()->create([
            'payment_type' => Reservation::PAYMENT_TYPE_DP,
            'status' => Reservation::STATUS_CONFIRMED,
        ]);

        $resource = new ReservationResource($reservation);
        $data = $resource->toArray(request());

        $this->assertSame('DP Confirmed', $data['status_label']);
    }

    public function test_resource_maps_table_number_and_selected_products(): void
    {
        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $table = Table::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'number' => 'A1',
        ]);
        $product = Product::factory()->create([
            'user_id' => $user->id,
            'price' => 12000,
        ]);

        $reservation = Reservation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'customer_name' => 'Adi',
            'phone' => '+6281234567890',
            'email' => 'adi@example.com',
            'reservation_date' => now()->toDateString(),
            'guest_count' => 2,
            'table_id' => $table->id,
            'selected_products' => [$product->id],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'total_amount' => 12000,
            'paid_amount' => 0,
            'remaining_amount' => 12000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-TEST-DETAIL-001',
        ]);

        $resource = new ReservationResource($reservation->load('table'));
        $data = $resource->toArray(request());

        $this->assertSame('A1', $data['table']['name']);
        $this->assertCount(1, $data['selected_products']);
        $this->assertSame($product->id, $data['selected_products'][0]['id']);
        $this->assertSame($product->name, $data['selected_products'][0]['name']);
        $this->assertEquals($product->price, $data['selected_products'][0]['price']);
        $this->assertSame(1, $data['selected_products'][0]['quantity']);
    }
}
