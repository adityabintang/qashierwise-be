<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PromoCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PromoCodeController extends Controller
{
    public function __construct(
        private PromoCodeService $promoCodeService
    ) {}

    /**
     * Validate a promo code.
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50',
            'plan_id' => 'required|string|in:pro',
            'duration' => 'required|string|in:1_month,3_months,1_year',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'Invalid input',
                ],
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $code = $request->input('code');
        $planId = $request->input('plan_id');
        $duration = $request->input('duration');

        // Get plan price
        $plan = config("subscription.plans.{$planId}");
        if (! $plan || ! isset($plan['durations'][$duration])) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PLAN',
                    'message' => 'Invalid plan or duration',
                ],
            ], 422);
        }

        $originalAmount = $plan['durations'][$duration]['price'];

        // Validate promo code
        $result = $this->promoCodeService->validateAndApply($code, $planId, $originalAmount, $user);

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PROMO_CODE',
                    'message' => $result['message'],
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'valid' => true,
                'message' => $result['message'],
                'original_amount' => $originalAmount,
                'discount' => $result['discount'],
                'final_amount' => $result['final_amount'],
                'code' => $code,
            ],
        ]);
    }
}
