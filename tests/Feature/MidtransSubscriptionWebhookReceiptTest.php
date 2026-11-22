<?php

namespace Tests\Feature;

use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Notifications\SubscriptionPaymentReceiptNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MidtransSubscriptionWebhookReceiptTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_webhook_sends_receipt_and_records_capture_status(): void
    {
        Notification::fake();

        config([
            'services.midtrans.server_key' => '',
            'subscription.invoice.mode' => 'receipt',
        ]);

        $user = User::factory()->create();
        $transactionTime = now()->format('Y-m-d H:i:s');
        $orderId = 'SUB-3-1_month-1771240125-e802a3af';

        $payload = [
            'transaction_time' => $transactionTime,
            'transaction_status' => 'capture',
            'transaction_id' => 'adf9bdb3-422e-4815-98d9-595abd4ae3ba',
            'status_code' => '200',
            'order_id' => $orderId,
            'gross_amount' => '350000.00',
            'payment_type' => 'credit_card',
            'fraud_status' => 'accept',
            'custom_field1' => 'pro',
            'custom_field2' => '1_month',
            'custom_field3' => (string) $user->id,
        ];

        $response = $this->postJson('/api/webhooks/midtrans/subscription', $payload);

        $response->assertStatus(200);

        $payment = SubscriptionPayment::where('order_id', $orderId)->first();

        $this->assertNotNull($payment);
        $this->assertSame('capture', $payment->status);
        $this->assertSame('credit_card', $payment->payment_type);

        Notification::assertSentTo($user, SubscriptionPaymentReceiptNotification::class);
    }
}
