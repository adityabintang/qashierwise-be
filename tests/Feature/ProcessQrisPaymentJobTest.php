<?php

namespace Tests\Feature;

use App\Jobs\ProcessQrisPayment;
use App\Models\MerchantBalance;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessQrisPaymentJobTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SubMerchant $subMerchant;
    private MerchantBalance $balance;
    private QrisTransaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        // Create user and sub-merchant
        $this->user = User::factory()->create();
        
        $this->subMerchant = SubMerchant::create([
            'user_id' => $this->user->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Test User',
            'is_active' => true,
        ]);

        $this->balance = MerchantBalance::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'available_balance' => 0,
            'pending_balance' => 0,
            'total_earned' => 0,
            'total_withdrawn' => 0,
        ]);

        // Create a settled transaction
        $this->transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-TEST1234',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function test_job_updates_merchant_balance(): void
    {
        $job = new ProcessQrisPayment($this->transaction, []);
        $job->handle(app(\App\Services\BalanceService::class));

        // Refresh balance
        $this->balance->refresh();

        // Verify balance was updated with net amount
        $this->assertEquals(97500, $this->balance->available_balance);
        $this->assertEquals(97500, $this->balance->total_earned);
    }

    public function test_job_creates_platform_fee_record(): void
    {
        $job = new ProcessQrisPayment($this->transaction, []);
        $job->handle(app(\App\Services\BalanceService::class));

        // Verify platform fee record was created
        $platformFee = PlatformFee::where('qris_transaction_id', $this->transaction->id)->first();
        
        $this->assertNotNull($platformFee);
        $this->assertEquals(2.5, $platformFee->fee_percentage);
        $this->assertEquals(2500, $platformFee->fee_amount);
    }

    public function test_job_skips_non_settled_transaction(): void
    {
        // Change transaction to pending
        $this->transaction->status = QrisTransaction::STATUS_PENDING;
        $this->transaction->settled_at = null;
        $this->transaction->save();

        $job = new ProcessQrisPayment($this->transaction, []);
        $job->handle(app(\App\Services\BalanceService::class));

        // Refresh balance
        $this->balance->refresh();

        // Verify balance was NOT updated
        $this->assertEquals(0, $this->balance->available_balance);
    }

    public function test_job_prevents_duplicate_processing(): void
    {
        // Process once
        $job1 = new ProcessQrisPayment($this->transaction, []);
        $job1->handle(app(\App\Services\BalanceService::class));

        // Refresh balance
        $this->balance->refresh();
        $firstBalance = $this->balance->available_balance;

        // Process again (should be skipped)
        $job2 = new ProcessQrisPayment($this->transaction, []);
        $job2->handle(app(\App\Services\BalanceService::class));

        // Refresh balance
        $this->balance->refresh();

        // Verify balance was NOT updated again
        $this->assertEquals($firstBalance, $this->balance->available_balance);
        
        // Verify only one platform fee record exists
        $feeCount = PlatformFee::where('qris_transaction_id', $this->transaction->id)->count();
        $this->assertEquals(1, $feeCount);
    }

    public function test_job_handles_multiple_transactions(): void
    {
        // Process first transaction
        $job1 = new ProcessQrisPayment($this->transaction, []);
        $job1->handle(app(\App\Services\BalanceService::class));

        // Create and process second transaction
        $transaction2 = QrisTransaction::create([
            'sub_merchant_id' => $this->subMerchant->id,
            'order_id' => 'QRIS-' . now()->format('YmdHis') . '-TEST5678',
            'amount' => 50000,
            'platform_fee' => 1250,
            'net_amount' => 48750,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'settled_at' => now(),
            'expires_at' => now()->addMinutes(30),
        ]);

        $job2 = new ProcessQrisPayment($transaction2, []);
        $job2->handle(app(\App\Services\BalanceService::class));

        // Refresh balance
        $this->balance->refresh();

        // Verify balance includes both transactions
        $expectedBalance = 97500 + 48750; // 146250
        $this->assertEquals($expectedBalance, $this->balance->available_balance);
    }
}
