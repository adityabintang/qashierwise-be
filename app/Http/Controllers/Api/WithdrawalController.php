<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SubMerchantService;
use App\Services\WithdrawalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    public function __construct(
        private WithdrawalService $withdrawalService,
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Request a withdrawal.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:10000',
        ], [
            'amount.min' => 'Minimum withdrawal amount is Rp 10,000',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = $request->user();
        $merchant = $this->subMerchantService->findByUserId($user->id);

        if (! $merchant) {
            return ApiResponse::forbidden('You are not registered as a sub-merchant.');
        }

        try {
            $withdrawal = $this->withdrawalService->requestWithdrawal(
                $merchant,
                (float) $request->amount
            );

            return ApiResponse::success([
                'withdrawal' => [
                    'id' => $withdrawal->id,
                    'amount' => (float) $withdrawal->amount,
                    'bank_code' => $withdrawal->bank_code,
                    'bank_account_number' => $withdrawal->bank_account_number,
                    'bank_account_name' => $withdrawal->bank_account_name,
                    'status' => $withdrawal->status,
                    'reference_id' => $withdrawal->reference_id,
                    'created_at' => $withdrawal->created_at->toIso8601String(),
                ],
            ], 'Withdrawal request submitted successfully.');

        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        } catch (\RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 500);
        }
    }

    /**
     * List withdrawal history.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->subMerchantService->findByUserId($user->id);

        if (! $merchant) {
            return ApiResponse::forbidden('You are not registered as a sub-merchant.');
        }

        $limit = min((int) ($request->query('limit', 50)), 100);
        $withdrawals = $this->withdrawalService->getWithdrawalHistory($merchant, $limit);

        return ApiResponse::success([
            'withdrawals' => $withdrawals->map(fn ($w) => [
                'id' => $w->id,
                'amount' => (float) $w->amount,
                'bank_code' => $w->bank_code,
                'bank_account_number' => $w->bank_account_number,
                'bank_account_name' => $w->bank_account_name,
                'status' => $w->status,
                'reference_id' => $w->reference_id,
                'failure_reason' => $w->failure_reason,
                'completed_at' => $w->completed_at?->toIso8601String(),
                'created_at' => $w->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Show withdrawal detail.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->subMerchantService->findByUserId($user->id);

        if (! $merchant) {
            return ApiResponse::forbidden('You are not registered as a sub-merchant.');
        }

        $withdrawal = $this->withdrawalService->findForMerchant($merchant, $id);

        if (! $withdrawal) {
            return ApiResponse::error('Withdrawal not found.', 404);
        }

        return ApiResponse::success([
            'withdrawal' => [
                'id' => $withdrawal->id,
                'amount' => (float) $withdrawal->amount,
                'bank_code' => $withdrawal->bank_code,
                'bank_account_number' => $withdrawal->bank_account_number,
                'bank_account_name' => $withdrawal->bank_account_name,
                'status' => $withdrawal->status,
                'reference_id' => $withdrawal->reference_id,
                'xendit_payout_id' => $withdrawal->xendit_payout_id,
                'failure_reason' => $withdrawal->failure_reason,
                'completed_at' => $withdrawal->completed_at?->toIso8601String(),
                'created_at' => $withdrawal->created_at->toIso8601String(),
            ],
        ]);
    }
}
