<?php

namespace Tests\Unit\Services;

use App\Models\FinancialAuditLog;
use App\Models\MerchantBalance;
use App\Models\PlatformFee;
use App\Models\QrisTransaction;
use App\Models\SubMerchant;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\FinancialAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected FinancialAuditService $auditService;
    protected User $user;
    protected SubMerchant $merchant;
    protected MerchantBalance $balance;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->auditService = new FinancialAuditService();
        
        // Create test user and sub-merchant
        $this->user = User::factory()->create();
        $this->merchant = SubMerchant::create([
            'user_id' => $this->user->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Test User',
            'is_active' => true,
            'verified_at' => now(),
        ]);
        $this->balance = MerchantBalance::create([
            'sub_merchant_id' => $this->merchant->id,
            'available_balance' => 100000,
            'pending_balance' => 0,
            'total_earned' => 100000,
            'total_withdrawn' => 0,
        ]);
    }

    public function test_logs_balance_update(): void
    {
        $log = $this->auditService->logBalanceUpdate(
            $this->merchant,
            50000,
            'payment',
            100000,
            150000,
            'QRIS-TEST-001'
        );

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals($this->merchant->id, $log->sub_merchant_id);
        $this->assertEquals(FinancialAuditLog::ACTION_BALANCE_PAYMENT, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_BALANCE, $log->action_category);
        $this->assertEquals(50000, $log->amount);
        $this->assertEquals(100000, $log->balance_before);
        $this->assertEquals(150000, $log->balance_after);
        $this->assertEquals('QRIS-TEST-001', $log->reference_code);
        $this->assertEquals(FinancialAuditLog::STATUS_SUCCESS, $log->status);
    }


    public function test_logs_withdrawal_request(): void
    {
        $withdrawal = WithdrawalRequest::create([
            'sub_merchant_id' => $this->merchant->id,
            'amount' => 50000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'bank_details' => [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Test User',
            ],
        ]);

        $log = $this->auditService->logWithdrawalRequest(
            $this->merchant,
            $withdrawal,
            100000,
            50000
        );

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_WITHDRAWAL_REQUEST, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_WITHDRAWAL, $log->action_category);
        $this->assertEquals(50000, $log->amount);
        $this->assertEquals(100000, $log->balance_before);
        $this->assertEquals(50000, $log->balance_after);
        $this->assertEquals(FinancialAuditLog::STATUS_PENDING, $log->status);
        $this->assertEquals(WithdrawalRequest::class, $log->reference_type);
        $this->assertEquals($withdrawal->id, $log->reference_id);
    }

    public function test_logs_withdrawal_approval(): void
    {
        $admin = User::factory()->create();
        $withdrawal = WithdrawalRequest::create([
            'sub_merchant_id' => $this->merchant->id,
            'amount' => 50000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'bank_details' => [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Test User',
            ],
        ]);

        $log = $this->auditService->logWithdrawalApproval($withdrawal, $admin, 'Approved for processing');

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_WITHDRAWAL_APPROVAL, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_WITHDRAWAL, $log->action_category);
        $this->assertEquals($admin->id, $log->admin_id);
        $this->assertEquals(FinancialAuditLog::STATUS_SUCCESS, $log->status);
        $this->assertArrayHasKey('admin_notes', $log->metadata);
        $this->assertEquals('Approved for processing', $log->metadata['admin_notes']);
    }

    public function test_logs_withdrawal_rejection(): void
    {
        $admin = User::factory()->create();
        $withdrawal = WithdrawalRequest::create([
            'sub_merchant_id' => $this->merchant->id,
            'amount' => 50000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'bank_details' => [
                'bank_name' => 'BCA',
                'account_number' => '1234567890',
                'account_holder_name' => 'Test User',
            ],
        ]);

        $log = $this->auditService->logWithdrawalRejection(
            $withdrawal,
            $admin,
            'Invalid bank account',
            50000,
            100000
        );

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_WITHDRAWAL_REJECTION, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_WITHDRAWAL, $log->action_category);
        $this->assertEquals($admin->id, $log->admin_id);
        $this->assertEquals(50000, $log->balance_before);
        $this->assertEquals(100000, $log->balance_after);
        $this->assertArrayHasKey('rejection_reason', $log->metadata);
        $this->assertEquals('Invalid bank account', $log->metadata['rejection_reason']);
    }

    public function test_logs_qris_generation(): void
    {
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->merchant->id,
            'order_id' => 'QRIS-TEST-001',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $log = $this->auditService->logQrisGeneration($this->merchant, $transaction);

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_QRIS_GENERATED, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_TRANSACTION, $log->action_category);
        $this->assertEquals(100000, $log->amount);
        $this->assertEquals(2500, $log->fee_amount);
        $this->assertEquals('QRIS-TEST-001', $log->reference_code);
        $this->assertEquals(FinancialAuditLog::STATUS_PENDING, $log->status);
    }

    public function test_logs_qris_settlement(): void
    {
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->merchant->id,
            'order_id' => 'QRIS-TEST-002',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
            'midtrans_transaction_id' => 'MT-12345',
            'settled_at' => now(),
        ]);

        $log = $this->auditService->logQrisSettlement(
            $this->merchant,
            $transaction,
            100000,
            197500
        );

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_QRIS_SETTLED, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_TRANSACTION, $log->action_category);
        $this->assertEquals(100000, $log->balance_before);
        $this->assertEquals(197500, $log->balance_after);
        $this->assertEquals(FinancialAuditLog::STATUS_SUCCESS, $log->status);
        $this->assertArrayHasKey('midtrans_transaction_id', $log->metadata);
    }

    public function test_logs_fee_calculation(): void
    {
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->merchant->id,
            'order_id' => 'QRIS-TEST-003',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
        ]);

        $log = $this->auditService->logFeeCalculation(
            $this->merchant,
            $transaction,
            2500,
            2.5
        );

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_FEE_CALCULATED, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_FEE, $log->action_category);
        $this->assertEquals(100000, $log->amount);
        $this->assertEquals(2500, $log->fee_amount);
        $this->assertArrayHasKey('fee_percentage', $log->metadata);
        $this->assertEquals(2.5, $log->metadata['fee_percentage']);
    }

    public function test_logs_fee_collection(): void
    {
        $transaction = QrisTransaction::create([
            'sub_merchant_id' => $this->merchant->id,
            'order_id' => 'QRIS-TEST-004',
            'amount' => 100000,
            'platform_fee' => 2500,
            'net_amount' => 97500,
            'status' => QrisTransaction::STATUS_SETTLEMENT,
        ]);

        $platformFee = PlatformFee::create([
            'qris_transaction_id' => $transaction->id,
            'fee_percentage' => 2.5,
            'fee_amount' => 2500,
            'collected_at' => now(),
        ]);

        $log = $this->auditService->logFeeCollection($this->merchant, $transaction, $platformFee);

        $this->assertInstanceOf(FinancialAuditLog::class, $log);
        $this->assertEquals(FinancialAuditLog::ACTION_FEE_COLLECTED, $log->action_type);
        $this->assertEquals(FinancialAuditLog::CATEGORY_FEE, $log->action_category);
        $this->assertEquals(2500, $log->fee_amount);
        $this->assertEquals(PlatformFee::class, $log->reference_type);
        $this->assertEquals($platformFee->id, $log->reference_id);
    }

    public function test_gets_logs_for_merchant(): void
    {
        // Create some audit logs
        $this->auditService->logBalanceUpdate($this->merchant, 50000, 'payment', 100000, 150000);
        $this->auditService->logBalanceUpdate($this->merchant, 25000, 'payment', 150000, 175000);

        $logs = $this->auditService->getLogsForMerchant($this->merchant);

        $this->assertCount(2, $logs);
        $this->assertEquals($this->merchant->id, $logs->first()->sub_merchant_id);
    }

    public function test_gets_logs_for_merchant_by_category(): void
    {
        // Create balance and withdrawal logs
        $this->auditService->logBalanceUpdate($this->merchant, 50000, 'payment', 100000, 150000);
        
        $withdrawal = WithdrawalRequest::create([
            'sub_merchant_id' => $this->merchant->id,
            'amount' => 25000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'bank_details' => ['bank_name' => 'BCA'],
        ]);
        $this->auditService->logWithdrawalRequest($this->merchant, $withdrawal, 150000, 125000);

        $balanceLogs = $this->auditService->getLogsForMerchant($this->merchant, FinancialAuditLog::CATEGORY_BALANCE);
        $withdrawalLogs = $this->auditService->getLogsForMerchant($this->merchant, FinancialAuditLog::CATEGORY_WITHDRAWAL);

        $this->assertCount(1, $balanceLogs);
        $this->assertCount(1, $withdrawalLogs);
    }

    public function test_gets_merchant_audit_summary(): void
    {
        // Create various audit logs
        $this->auditService->logBalanceUpdate($this->merchant, 50000, 'payment', 100000, 150000);
        $this->auditService->logBalanceUpdate($this->merchant, 25000, 'payment', 150000, 175000);
        
        $withdrawal = WithdrawalRequest::create([
            'sub_merchant_id' => $this->merchant->id,
            'amount' => 25000,
            'status' => WithdrawalRequest::STATUS_PENDING,
            'bank_details' => ['bank_name' => 'BCA'],
        ]);
        $this->auditService->logWithdrawalRequest($this->merchant, $withdrawal, 175000, 150000);

        $summary = $this->auditService->getMerchantAuditSummary($this->merchant);

        $this->assertEquals(3, $summary['total_logs']);
        $this->assertEquals(2, $summary['balance_updates']);
        $this->assertEquals(1, $summary['withdrawal_actions']);
        $this->assertEquals(2, $summary['successful_actions']);
        $this->assertEquals(1, $summary['pending_actions']);
    }

    public function test_financial_audit_log_model_methods(): void
    {
        $log = $this->auditService->logBalanceUpdate(
            $this->merchant,
            50000,
            'payment',
            100000,
            150000
        );

        // Test balance change calculation
        $this->assertEquals(50000, $log->getBalanceChange());

        // Test status checks
        $this->assertTrue($log->isSuccessful());
        $this->assertFalse($log->isFailed());

        // Test action description
        $this->assertEquals('Payment received', $log->getActionDescription());

        // Test formatted amounts
        $this->assertStringContainsString('50', $log->getFormattedAmount());
    }
}
