<?php

namespace Tests\Unit\Models;

use App\Models\Payment;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Property-based tests for Payment model
 *
 * Feature: point-of-sale
 */
class PaymentPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Feature: point-of-sale, Property 9: Payment Serialization Round-Trip
     * Validates: Requirements 4.6, 4.7
     *
     * For any valid Payment object, serializing to JSON and then deserializing back
     * SHALL produce an equivalent Payment object.
     */
    #[Test]
    public function payment_serialization_round_trip_preserves_data(): void
    {
        $methods = [
            Payment::METHOD_CASH,
            Payment::METHOD_CARD,
            Payment::METHOD_TRANSFER,
            Payment::METHOD_QRIS,
            Payment::METHOD_OTHER,
        ];

        $this
            ->limitTo(100)
            ->forAll(
                Generators::pos(),
                Generators::elements($methods),
                Generators::choose(100, 10000000),
                Generators::string()
            )
            ->then(function (int $orderId, string $method, int $amountCents, string $reference) {
                // Convert cents to decimal
                $amount = $amountCents / 100;

                // Create a Payment instance with generated data
                $payment = new Payment([
                    'order_id' => $orderId,
                    'method' => $method,
                    'amount' => $amount,
                    'reference' => $reference,
                    'metadata' => ['test_key' => 'test_value'],
                ]);

                // Serialize to JSON
                $json = $payment->toJson();

                // Deserialize from JSON
                $decoded = json_decode($json, true);

                // Create new Payment from decoded data
                $restoredPayment = new Payment($decoded);

                // Property: All fields should be preserved after round-trip
                $this->assertEquals($payment->order_id, $restoredPayment->order_id, 'order_id should be preserved');
                $this->assertEquals($payment->method, $restoredPayment->method, 'method should be preserved');
                $this->assertEquals($payment->amount, $restoredPayment->amount, 'amount should be preserved');
                $this->assertEquals($payment->reference, $restoredPayment->reference, 'reference should be preserved');
                $this->assertEquals($payment->metadata, $restoredPayment->metadata, 'metadata should be preserved');
            });
    }
}
