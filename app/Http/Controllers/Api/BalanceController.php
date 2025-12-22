<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BalanceService;
use App\Services\SubMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Controller for balance management API endpoints.
 * 
 * Handles balance retrieval, transaction history, and earnings breakdown.
 * Requirements: 4.1, 4.2, 4.3
 */
class BalanceController extends Controller
{
    public function __construct(
        private BalanceService $balanceService,
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Get the current balance for the sub-merchant.
     * 
     * Requirement 4.1: Display current available balance
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 403);
        }

        $balanceHistory = $this->balanceService->getBalanceHistory($subMerchant);

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => [
                    'available' => $balanceHistory['available_balance'],
                    'pending' => $balanceHistory['pending_balance'],
                    'total_earned' => $balanceHistory['total_earned'],
                    'total_withdrawn' => $balanceHistory['total_withdrawn'],
                    'total_balance' => $balanceHistory['total_balance'],
                    'last_updated' => $balanceHistory['last_updated']?->toIso8601String(),
                ],
                'currency' => 'IDR',
            ],
        ]);
    }

    /**
     * Get balance breakdown with detailed information.
     * 
     * Requirement 4.2: Show pending and available amounts separately
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function breakdown(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 403);
        }

        $balanceHistory = $this->balanceService->getBalanceHistory($subMerchant);
        
        // Get withdrawal validation info
        $canWithdraw = $this->balanceService->canWithdraw($subMerchant, $balanceHistory['available_balance']);
        $minimumWithdrawal = \App\Models\MerchantBalance::MINIMUM_WITHDRAWAL;

        return response()->json([
            'success' => true,
            'data' => [
                'balance' => [
                    'available' => [
                        'amount' => $balanceHistory['available_balance'],
                        'description' => 'Funds available for withdrawal',
                    ],
                    'pending' => [
                        'amount' => $balanceHistory['pending_balance'],
                        'description' => 'Funds being processed',
                    ],
                    'total_earned' => [
                        'amount' => $balanceHistory['total_earned'],
                        'description' => 'Total earnings from all transactions',
                    ],
                    'total_withdrawn' => [
                        'amount' => $balanceHistory['total_withdrawn'],
                        'description' => 'Total amount withdrawn',
                    ],
                    'total_balance' => [
                        'amount' => $balanceHistory['total_balance'],
                        'description' => 'Available + Pending balance',
                    ],
                ],
                'withdrawal_info' => [
                    'can_withdraw' => $canWithdraw,
                    'minimum_amount' => $minimumWithdrawal,
                    'available_for_withdrawal' => $balanceHistory['available_balance'],
                ],
                'currency' => 'IDR',
                'last_updated' => $balanceHistory['last_updated']?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get transaction history with fee breakdown.
     * 
     * Requirement 4.3: Provide transaction history with fee breakdowns
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 403);
        }

        $limit = min((int) $request->input('limit', 50), 100);
        $transactions = $this->balanceService->getTransactionHistoryWithFees($subMerchant, $limit);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions->toArray(),
                'count' => $transactions->count(),
                'platform_fee_percentage' => BalanceService::PLATFORM_FEE_PERCENTAGE,
            ],
        ]);
    }

    /**
     * Get earnings summary for a date range.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function earnings(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 403);
        }

        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date'))->startOfDay() 
            : null;
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date'))->endOfDay() 
            : null;

        $summary = $this->balanceService->getEarningsSummary($subMerchant, $startDate, $endDate);

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'transaction_count' => $summary['transaction_count'],
                    'gross_earnings' => $summary['gross_earnings'],
                    'total_fees' => $summary['total_fees'],
                    'net_earnings' => $summary['net_earnings'],
                ],
                'period' => [
                    'start_date' => $startDate?->toIso8601String(),
                    'end_date' => $endDate?->toIso8601String(),
                ],
                'platform_fee_percentage' => BalanceService::PLATFORM_FEE_PERCENTAGE,
                'currency' => 'IDR',
            ],
        ]);
    }

    /**
     * Get daily earnings summary for the current month.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function dailyEarnings(Request $request): JsonResponse
    {
        $user = $request->user();
        $subMerchant = $this->subMerchantService->findByUserId($user->id);

        if ($subMerchant === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NOT_SUB_MERCHANT',
                    'message' => 'User is not registered as a sub-merchant',
                ],
            ], 403);
        }

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth();

        // Get daily breakdown
        $dailyData = $subMerchant->transactions()
            ->where('status', \App\Models\QrisTransaction::STATUS_SETTLEMENT)
            ->whereBetween('settled_at', [$startDate, $endDate])
            ->selectRaw('DATE(settled_at) as date, COUNT(*) as count, SUM(amount) as gross, SUM(platform_fee) as fees, SUM(net_amount) as net')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'transaction_count' => (int) $item->count,
                    'gross_earnings' => (float) $item->gross,
                    'total_fees' => (float) $item->fees,
                    'net_earnings' => (float) $item->net,
                ];
            });

        // Calculate totals
        $totals = [
            'transaction_count' => $dailyData->sum('transaction_count'),
            'gross_earnings' => $dailyData->sum('gross_earnings'),
            'total_fees' => $dailyData->sum('total_fees'),
            'net_earnings' => $dailyData->sum('net_earnings'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'daily' => $dailyData->toArray(),
                'totals' => $totals,
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'start_date' => $startDate->toIso8601String(),
                    'end_date' => $endDate->toIso8601String(),
                ],
                'currency' => 'IDR',
            ],
        ]);
    }

    /**
     * Calculate platform fee for a given amount (utility endpoint).
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function calculateFee(Request $request): JsonResponse
    {
        $amount = (float) $request->input('amount', 0);

        if ($amount <= 0) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_AMOUNT',
                    'message' => 'Amount must be greater than zero',
                ],
            ], 422);
        }

        $platformFee = $this->balanceService->calculatePlatformFee($amount);
        $netAmount = $this->balanceService->calculateNetAmount($amount);

        return response()->json([
            'success' => true,
            'data' => [
                'gross_amount' => $amount,
                'platform_fee' => $platformFee,
                'net_amount' => $netAmount,
                'fee_percentage' => BalanceService::PLATFORM_FEE_PERCENTAGE,
                'currency' => 'IDR',
            ],
        ]);
    }
}
