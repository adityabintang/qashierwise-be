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
    public function __construct(
        private XenPlatformService $xenPlatformService,
    ) {}

    /**
     * Register a user as a sub-merchant.
     * Creates the sub-merchant record, initializes balance, and creates a XenPlatform sub-account.
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
                'xendit_account_status' => 'pending',
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

            // Create XenPlatform sub-account (OWNED)
            // Throws RuntimeException on failure, which rolls back the transaction
            $xenditAccount = $this->xenPlatformService->createSubAccount($subMerchant);

            $subMerchant->update([
                'xendit_account_id' => $xenditAccount['id'],
                'xendit_account_status' => 'active',
            ]);

            Log::info('XenPlatform sub-account created', [
                'sub_merchant_id' => $subMerchant->id,
                'xendit_account_id' => $xenditAccount['id'],
            ]);

            return $subMerchant->fresh(['balance']);
        });
    }

    /**
     * Retry creating a XenPlatform sub-account for a merchant that failed initially.
     */
    public function retryXenPlatformAccount(SubMerchant $merchant): bool
    {
        if ($merchant->hasXenditAccount()) {
            return true; // Already has an active account
        }

        try {
            $xenditAccount = $this->xenPlatformService->createSubAccount($merchant);

            $merchant->update([
                'xendit_account_id' => $xenditAccount['id'],
                'xendit_account_status' => 'active',
            ]);

            Log::info('XenPlatform sub-account created on retry', [
                'sub_merchant_id' => $merchant->id,
                'xendit_account_id' => $xenditAccount['id'],
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Retry failed: XenPlatform sub-account creation', [
                'sub_merchant_id' => $merchant->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Activate a sub-merchant.
     */
    public function activateSubMerchant(SubMerchant $merchant): bool
    {
        if ($merchant->is_active) {
            return true;
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
     */
    public function deactivateSubMerchant(SubMerchant $merchant): bool
    {
        if (! $merchant->is_active) {
            return true;
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
     */
    public function verifySubMerchant(SubMerchant $merchant): bool
    {
        if ($merchant->isVerified()) {
            return true;
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
     */
    public function find(int $id): ?SubMerchant
    {
        return SubMerchant::find($id);
    }

    /**
     * Find a sub-merchant by user ID.
     */
    public function findByUserId(int $userId): ?SubMerchant
    {
        return SubMerchant::where('user_id', $userId)->first();
    }

    /**
     * Get sub-merchant with balance information.
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
     */
    public function canBecomeSubMerchant(User $user): bool
    {
        return $user->subMerchant === null;
    }
}
