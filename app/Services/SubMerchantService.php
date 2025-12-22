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
     * Register a user as a sub-merchant with bank account details.
     *
     * @param User $user The user to register as sub-merchant
     * @param array $bankDetails Bank account details (bank_name, account_number, account_holder_name)
     * @return SubMerchant The created sub-merchant
     * @throws InvalidArgumentException If validation fails or user is already a sub-merchant
     */
    public function registerSubMerchant(User $user, array $bankDetails): SubMerchant
    {
        // Check if user is already a sub-merchant
        if ($user->subMerchant !== null) {
            throw new InvalidArgumentException('User is already registered as a sub-merchant');
        }

        // Validate bank account details
        $validationErrors = SubMerchant::validateBankAccount($bankDetails);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException(
                'Invalid bank account details: ' . implode(', ', $validationErrors)
            );
        }

        return DB::transaction(function () use ($user, $bankDetails) {
            // Create sub-merchant record
            $subMerchant = SubMerchant::create([
                'user_id' => $user->id,
                'bank_name' => $bankDetails['bank_name'],
                'account_number' => $bankDetails['account_number'],
                'account_holder_name' => $bankDetails['account_holder_name'],
                'is_active' => true,
                'verified_at' => null,
            ]);

            // Initialize balance to zero (Requirement 1.5)
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
     * Update bank account details for a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant to update
     * @param array $bankDetails New bank account details
     * @return bool True if update was successful
     * @throws InvalidArgumentException If validation fails
     */
    public function updateBankAccount(SubMerchant $merchant, array $bankDetails): bool
    {
        // Validate bank account details
        $validationErrors = SubMerchant::validateBankAccount($bankDetails);
        if (!empty($validationErrors)) {
            throw new InvalidArgumentException(
                'Invalid bank account details: ' . implode(', ', $validationErrors)
            );
        }

        $updated = $merchant->update([
            'bank_name' => $bankDetails['bank_name'],
            'account_number' => $bankDetails['account_number'],
            'account_holder_name' => $bankDetails['account_holder_name'],
        ]);

        if ($updated) {
            Log::info('Sub-merchant bank account updated', [
                'sub_merchant_id' => $merchant->id,
            ]);
        }

        return $updated;
    }

    /**
     * Validate bank account details without creating a sub-merchant.
     *
     * @param array $bankDetails Bank account details to validate
     * @return bool True if valid
     */
    public function validateBankAccount(array $bankDetails): bool
    {
        return SubMerchant::isValidBankAccount($bankDetails);
    }

    /**
     * Get validation errors for bank account details.
     *
     * @param array $bankDetails Bank account details to validate
     * @return array<string, string> Validation errors (empty if valid)
     */
    public function getBankAccountValidationErrors(array $bankDetails): array
    {
        return SubMerchant::validateBankAccount($bankDetails);
    }

    /**
     * Activate a sub-merchant.
     *
     * @param SubMerchant $merchant The sub-merchant to activate
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
     * @param SubMerchant $merchant The sub-merchant to deactivate
     * @return bool True if deactivation was successful
     */
    public function deactivateSubMerchant(SubMerchant $merchant): bool
    {
        if (!$merchant->is_active) {
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
     * @param SubMerchant $merchant The sub-merchant to verify
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
     * Unverify a sub-merchant (admin action).
     *
     * @param SubMerchant $merchant The sub-merchant to unverify
     * @return bool True if unverification was successful
     */
    public function unverifySubMerchant(SubMerchant $merchant): bool
    {
        if (!$merchant->isVerified()) {
            return true; // Already unverified
        }

        $merchant->verified_at = null;
        $result = $merchant->save();

        if ($result) {
            Log::info('Sub-merchant unverified', [
                'sub_merchant_id' => $merchant->id,
            ]);
        }

        return $result;
    }

    /**
     * Find a sub-merchant by ID.
     *
     * @param int $id Sub-merchant ID
     * @return SubMerchant|null
     */
    public function find(int $id): ?SubMerchant
    {
        return SubMerchant::find($id);
    }

    /**
     * Find a sub-merchant by user ID.
     *
     * @param int $userId User ID
     * @return SubMerchant|null
     */
    public function findByUserId(int $userId): ?SubMerchant
    {
        return SubMerchant::where('user_id', $userId)->first();
    }

    /**
     * Get sub-merchant with balance information.
     *
     * @param SubMerchant $merchant The sub-merchant
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
     * @param User $user The user to check
     * @return bool True if user can become a sub-merchant
     */
    public function canBecomeSubMerchant(User $user): bool
    {
        return $user->subMerchant === null;
    }
}
