<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\SubMerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BankAccountController extends Controller
{
    public function __construct(
        private SubMerchantService $subMerchantService,
    ) {}

    /**
     * Get current bank account info.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $merchant = $this->subMerchantService->findByUserId($user->id);

        if (! $merchant) {
            return ApiResponse::forbidden('You are not registered as a sub-merchant.');
        }

        return ApiResponse::success([
            'bank_account' => $merchant->hasBankAccount() ? [
                'bank_code' => $merchant->bank_code,
                'bank_account_number' => $merchant->bank_account_number,
                'bank_account_name' => $merchant->bank_account_name,
            ] : null,
        ]);
    }

    /**
     * Save or update bank account info.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bank_code' => 'required|string|max:50',
            'bank_account_number' => 'required|string|max:30',
            'bank_account_name' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = $request->user();
        $merchant = $this->subMerchantService->findByUserId($user->id);

        if (! $merchant) {
            return ApiResponse::forbidden('You are not registered as a sub-merchant.');
        }

        $merchant->update([
            'bank_code' => $request->bank_code,
            'bank_account_number' => $request->bank_account_number,
            'bank_account_name' => $request->bank_account_name,
        ]);

        return ApiResponse::success([
            'bank_account' => [
                'bank_code' => $merchant->bank_code,
                'bank_account_number' => $merchant->bank_account_number,
                'bank_account_name' => $merchant->bank_account_name,
            ],
        ], 'Bank account updated successfully.');
    }

    /**
     * List supported banks for withdrawal.
     */
    public function supportedBanks(): JsonResponse
    {
        // Indonesian bank codes supported by Xendit
        $banks = [
            ['code' => 'ID_BCA', 'name' => 'Bank Central Asia (BCA)'],
            ['code' => 'ID_BNI', 'name' => 'Bank Negara Indonesia (BNI)'],
            ['code' => 'ID_BRI', 'name' => 'Bank Rakyat Indonesia (BRI)'],
            ['code' => 'ID_MANDIRI', 'name' => 'Bank Mandiri'],
            ['code' => 'ID_CIMB', 'name' => 'CIMB Niaga'],
            ['code' => 'ID_DANAMON', 'name' => 'Bank Danamon'],
            ['code' => 'ID_PERMATA', 'name' => 'Bank Permata'],
            ['code' => 'ID_BSI', 'name' => 'Bank Syariah Indonesia (BSI)'],
            ['code' => 'ID_BTN', 'name' => 'Bank Tabungan Negara (BTN)'],
            ['code' => 'ID_BTPN', 'name' => 'Bank BTPN'],
            ['code' => 'ID_OCBC', 'name' => 'OCBC NISP'],
            ['code' => 'ID_MAYBANK', 'name' => 'Maybank Indonesia'],
            ['code' => 'ID_PANIN', 'name' => 'Panin Bank'],
            ['code' => 'ID_OVO', 'name' => 'OVO'],
            ['code' => 'ID_DANA', 'name' => 'DANA'],
            ['code' => 'ID_GOPAY', 'name' => 'GoPay'],
            ['code' => 'ID_SHOPEEPAY', 'name' => 'ShopeePay'],
        ];

        return ApiResponse::success(['banks' => $banks]);
    }
}
