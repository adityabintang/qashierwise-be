<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\PromoCodeUsage;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class PromoCodeService
{
    /**
     * Validate and apply promo code.
     *
     * @param string $code
     * @param string $planId
     * @param float $amount
     * @param User $user
     * @return array{valid: bool, message: string, discount?: float, final_amount?: float, promo_code?: PromoCode}
     */
    public function validateAndApply(string $code, string $planId, float $amount, User $user): array
    {
        $promoCode = PromoCode::where('code', strtoupper($code))->first();

        if (!$promoCode) {
            return [
                'valid' => false,
                'message' => 'Kode promo tidak ditemukan',
            ];
        }

        // Check if valid
        if (!$promoCode->isValid()) {
            if ($promoCode->valid_until && now()->isAfter($promoCode->valid_until)) {
                return [
                    'valid' => false,
                    'message' => 'Kode promo sudah kadaluarsa',
                ];
            }

            if ($promoCode->max_uses && $promoCode->used_count >= $promoCode->max_uses) {
                return [
                    'valid' => false,
                    'message' => 'Kode promo sudah mencapai batas penggunaan',
                ];
            }

            return [
                'valid' => false,
                'message' => 'Kode promo tidak valid',
            ];
        }

        // Check if applicable to plan
        if (!$promoCode->isApplicableToPlan($planId)) {
            return [
                'valid' => false,
                'message' => 'Kode promo tidak berlaku untuk paket ini',
            ];
        }

        // Check minimum purchase
        if ($promoCode->min_purchase && $amount < $promoCode->min_purchase) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Minimum pembelian Rp %s untuk menggunakan kode promo ini',
                    number_format($promoCode->min_purchase, 0, ',', '.')
                ),
            ];
        }

        // Calculate discount
        $discount = $promoCode->calculateDiscount($amount);
        $finalAmount = $promoCode->calculateFinalAmount($amount);

        Log::info('Promo code validated successfully', [
            'code' => $code,
            'user_id' => $user->id,
            'plan_id' => $planId,
            'original_amount' => $amount,
            'discount' => $discount,
            'final_amount' => $finalAmount,
        ]);

        return [
            'valid' => true,
            'message' => sprintf(
                'Kode promo berhasil! Hemat Rp %s',
                number_format($discount, 0, ',', '.')
            ),
            'discount' => $discount,
            'final_amount' => $finalAmount,
            'promo_code' => $promoCode,
        ];
    }

    /**
     * Record promo code usage.
     *
     * @param PromoCode $promoCode
     * @param User $user
     * @param float $originalAmount
     * @param float $discountAmount
     * @param float $finalAmount
     * @param int|null $subscriptionId
     * @return PromoCodeUsage
     */
    public function recordUsage(
        PromoCode $promoCode,
        User $user,
        float $originalAmount,
        float $discountAmount,
        float $finalAmount,
        ?int $subscriptionId = null
    ): PromoCodeUsage {
        $usage = PromoCodeUsage::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $user->id,
            'subscription_id' => $subscriptionId,
            'original_amount' => $originalAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => $finalAmount,
        ]);

        $promoCode->incrementUsage();

        Log::info('Promo code usage recorded', [
            'promo_code_id' => $promoCode->id,
            'code' => $promoCode->code,
            'user_id' => $user->id,
            'discount_amount' => $discountAmount,
        ]);

        return $usage;
    }
}
