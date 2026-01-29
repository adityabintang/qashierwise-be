<?php

namespace Tests\Unit\Models;

use App\Models\Order;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Order model
 *
 * Feature: point-of-sale
 */
class OrderPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 4: Order Serialization Round-Trip
     * Validates: Requirements 3.7, 3.8
     *
     * For any valid Order object with items, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Order object with identical calculated totals.
     */
    #[Test]
    public function order_serialization_round_trip_preserves_data(): void
    {
        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_PAID,
            Order::STATUS_CANCELLED,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::pos(),
                Generators::pos(),
                Generators::suchThat(
                    fn ($s) => strlen($s) > 0 && strlen($s) <= 20,
                    Generators::string()
                ),
                Generators::elements($statuses),
                Generators::choose(0, 10000000),
                Generators::choose(0, 1000000),
                Generators::choose(0, 1000000)
            )
            ->then(function (int $storeId, int $posUserId, string $orderNumber, string $status, int $subtotalCents, int $taxCents, int $discountCents) {
                // Convert cents to decimal
                $subtotal = $subtotalCents / 100;
                $taxAmount = $taxCents / 100;
                $discountAmount = $discountCents / 100;
                $total = $subtotal + $taxAmount - $discountAmount;

                // Create an Order instance with generated data
                $order = new Order([
                    'store_id' => $storeId,
                    'table_id' => null,
                    'pos_user_id' => $posUserId,
                    'order_number' => $orderNumber,
                    'status' => $status,
                    'subtotal' => $subtotal,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => $discountAmount,
                    'total' => $total,
                ]);

                // Serialize to JSON
                $json = $order->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Order from decoded data
                $restoredOrder = new Order($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($order->store_id, $restoredOrder->store_id, 'store_id should be preserved');
                $this->assertEquals($order->pos_user_id, $restoredOrder->pos_user_id, 'pos_user_id should be preserved');
                $this->assertEquals($order->order_number, $restoredOrder->order_number, 'order_number should be preserved');
                $this->assertEquals($order->status, $restoredOrder->status, 'status should be preserved');
                $this->assertEquals($order->subtotal, $restoredOrder->subtotal, 'subtotal should be preserved');
                $this->assertEquals($order->tax_amount, $restoredOrder->tax_amount, 'tax_amount should be preserved');
                $this->assertEquals($order->discount_amount, $restoredOrder->discount_amount, 'discount_amount should be preserved');
                $this->assertEquals($order->total, $restoredOrder->total, 'total should be preserved');
            });
    }
}
