<?php

namespace Tests\Feature;

use App\Jobs\ProcessQrisPayment;
use App\Jobs\ProcessReservationPayment;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\SubMerchant;
use App\Models\Table;
use App\Models\User;
use App\Services\AiAgentService;
use App\Services\BalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessQrisPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_payment_is_queued_when_qris_is_settled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $store = Store::factory()->create(['user_id' => $user->id]);
        $table = Table::factory()->create([
            'user_id' => $user->id,
            'store_id' => $store->id,
        ]);
        $subMerchant = SubMerchant::factory()->create(['user_id' => $user->id]);
        $transaction = QrisTransaction::factory()
            ->settled()
            ->create([
                'sub_merchant_id' => $subMerchant->id,
                'provider' => QrisTransaction::PROVIDER_XENDIT,
                'order_id' => 'QRIS-TEST-RES-001',
            ]);

        PlatformFee::createForTransaction($transaction);

        Reservation::create([
            'user_id' => $user->id,
            'store_id' => $store->id,
            'customer_name' => 'Adi Test',
            'phone' => '+6281234567890',
            'email' => 'adi@example.com',
            'reservation_date' => now()->toDateString(),
            'guest_count' => 2,
            'table_id' => $table->id,
            'selected_products' => [],
            'payment_type' => Reservation::PAYMENT_TYPE_FULL,
            'payment_method' => Reservation::PAYMENT_METHOD_QRIS,
            'qris_transaction_id' => $transaction->id,
            'total_amount' => 100000,
            'paid_amount' => 0,
            'remaining_amount' => 100000,
            'status' => Reservation::STATUS_PENDING_PAYMENT,
            'order_id' => 'RSV-TEST-001',
        ]);

        $job = new ProcessQrisPayment($transaction);
        $job->handle(app(BalanceService::class), app(AiAgentService::class));

        Queue::assertPushed(ProcessReservationPayment::class);
    }
}
