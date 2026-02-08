<?php

namespace App\Services;

use App\Models\MerchantBalance;
use App\Models\SubMerchant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class SubMerchantService
{
    /**
     * Register a user as a sub-merchant.
     *
     * @param  User  $user  The user to register as sub-merchant
     * @param  array  $details  Optional details (business_name)
     * @return SubMerchant The created sub-merchant
     *
     * @throws InvalidArgumentException If user is already a sub-merchant
     */
    public function registerSubMerchant(User $user, array $details = []): SubMerchant
    {
        // Check if user is already a sub-merchant
        if ($user->subMerchant !== null) {
            throw new InvalidArgumentException('User is already registered as a sub-merchant');
        }

        return DB::transaction(function () use ($user, $details) {
            // Create sub-merchant record
            $subMerchant = SubMerchant::create([
                'user_id' => $user->id,
                'business_name' => $details['business_name'] ?? $user->name,
                'is_active' => true,
                'verified_at' => null,
            ]);

            // Initialize balance to zero
            MerchantBalance::create([
                'sub_merchant_id' => $subMerchant->id,
                'available_balance' => 0.00,
                'pending_balance' => 0.00,
                'total_earned' => 0.00,
                'total_withdrawn' => 0.00,
                'last_updated' => now(),
            ]);

            Log::info('Sub-merchant registered', [
                'user_id' => $user->id,
                'sub_merchant_id' => $subMerchant->id,
            ]);

            return $subMerchant->fresh(['balance']);
        });
    }

    /**
     * Activate a sub-merchant.
     *
     * @param  SubMerchant  $merchant  The sub-merchant to activate
     * @return bool True if activation was successful
     */
    public function activateSubMerchant(SubMerchant $merchant): bool
    {
        if ($merchant->is_active) {
            return true; // Already active
        }

        $merchant->is_active = true;
        $result = $merchant->save();

        if ($result) {
            Log::info('Sub-merchant activated', [
                'sub_merchant_id' => $merchant->id,
            ]);
        }

        return $result;
    }

    /**
     * Deactivate a sub-merchant.
     *
     * @param  SubMerchant  $merchant  The sub-merchant to deactivate
     * @return bool True if deactivation was successful
     */
    public function deactivateSubMerchant(SubMerchant $merchant): bool
    {
        if (! $merchant->is_active) {
            return true; // Already inactive
        }

        $merchant->is_active = false;
        $result = $merchant->save();

        if ($result) {
            Log::info('Sub-merchant deactivated', [
                'sub_merchant_id' => $merchant->id,
            ]);
        }

        return $result;
    }

    /**
     * Verify a sub-merchant (admin action).
     *
     * @param  SubMerchant  $merchant  The sub-merchant to verify
     * @return bool True if verification was successful
     */
    public function verifySubMerchant(SubMerchant $merchant): bool
    {
        if ($merchant->isVerified()) {
            return true; // Already verified
        }

        $merchant->verified_at = now();
        $result = $merchant->save();

        if ($result) {
            Log::info('Sub-merchant verified', [
                'sub_merchant_id' => $merchant->id,
            ]);
        }

        return $result;
    }

    /**
     * Find a sub-merchant by ID.
     *
     * @param  int  $id  Sub-merchant ID
     */
    public function find(int $id): ?SubMerchant
    {
        return SubMerchant::find($id);
    }

    /**
     * Find a sub-merchant by user ID.
     *
     * @param  int  $userId  User ID
     */
    public function findByUserId(int $userId): ?SubMerchant
    {
        return SubMerchant::where('user_id', $userId)->first();
    }

    /**
     * Get sub-merchant with balance information.
     *
     * @param  SubMerchant  $merchant  The sub-merchant
     * @return array Sub-merchant data with balance
     */
    public function getWithBalance(SubMerchant $merchant): array
    {
        $merchant->load('balance');

        return [
            'sub_merchant' => $merchant,
            'balance' => $merchant->balance,
            'can_accept_payments' => $merchant->canAcceptPayments(),
        ];
    }

    /**
     * Check if a user can become a sub-merchant.
     *
     * @param  User  $user  The user to check
     * @return bool True if user can become a sub-merchant
     */
    public function canBecomeSubMerchant(User $user): bool
    {
        return $user->subMerchant === null;
    }
}
