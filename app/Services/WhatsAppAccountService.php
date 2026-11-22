<?php

namespace App\Services;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Exceptions\WhatsAppTokenExpiredException;
use App\Exceptions\WhatsAppTokenInvalidException;
use App\Models\WhatsAppAccount;
use Netflie\WhatsAppCloudApi\WhatsAppCloudApi;

/**
 * Service class for managing WhatsApp account operations.
 *
 * This service provides methods to retrieve WhatsApp clients configured
 * with user-specific credentials, supporting multi-tenant WhatsApp messaging.
 */
class WhatsAppAccountService
{
    /**
     * Get a WhatsApp Cloud API client configured for a specific user.
     *
     * @param  int  $userId  User ID
     *
     * @throws \App\Exceptions\WhatsAppNotConnectedException
     */
    public function getClientForUser(int $userId): WhatsAppCloudApi
    {
        $account = $this->getActiveAccount($userId);

        if (! $account) {
            throw new \App\Exceptions\WhatsAppNotConnectedException(
                'No connected WhatsApp account found for this user.'
            );
        }

        return new WhatsAppCloudApi([
            'from_phone_number_id' => $account->phone_number_id,
            'access_token' => $account->access_token,
        ]);
    }

    /**
     * Get the active WhatsApp account for a user.
     *
     * @param  int  $userId  User ID
     */
    public function getActiveAccount(int $userId): ?WhatsAppAccount
    {
        return WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if a user has a connected WhatsApp account.
     *
     * @param  int  $userId  User ID
     */
    public function hasConnectedAccount(int $userId): bool
    {
        return WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Get account by phone number ID.
     * Used for webhook routing to identify the correct user.
     *
     * Note: This method bypasses the global scope because webhooks
     * are not authenticated and need to find accounts by phone_number_id.
     *
     * @param  string  $phoneNumberId  Phone number ID from webhook payload
     */
    public function getAccountByPhoneNumberId(string $phoneNumberId): ?WhatsAppAccount
    {
        // Bypass global scope for webhook routing (no authenticated user during webhooks)
        return WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('phone_number_id', $phoneNumberId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Deactivate a user's WhatsApp account (disconnect).
     *
     * @param  int  $userId  User ID
     * @return bool True if account was deactivated, false if no account found
     */
    public function deactivateAccount(int $userId): bool
    {
        $account = $this->getActiveAccount($userId);

        if (! $account) {
            return false;
        }

        $account->is_active = false;
        $account->save();

        return true;
    }

    /**
     * Get account status information for display.
     *
     * @param  int  $userId  User ID
     * @return array|null Account status data or null if no account
     */
    public function getAccountStatus(int $userId): ?array
    {
        $account = WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('user_id', $userId)
            ->first();

        if (! $account) {
            return null;
        }

        return [
            'phone_number' => $account->display_phone_number,
            'display_name' => $account->display_phone_number,
            'verified_name' => $account->name,
            'quality_rating' => $account->quality_rating,
            'is_active' => $account->is_active,
            'coexistence_enabled' => $account->coexistence_enabled,
            'connection_method' => $account->connection_method,
            'waba_id' => $account->waba_id,
        ];
    }

    /**
     * Validate a user's WhatsApp account token status.
     *
     * This method checks if the user's token is valid and not expired.
     * It throws appropriate exceptions for different error conditions.
     *
     * @param  int  $userId  User ID
     * @return WhatsAppAccount The validated account
     *
     * @throws WhatsAppNotConnectedException If user has no connected account
     * @throws WhatsAppTokenExpiredException If the access token has expired
     * @throws WhatsAppTokenInvalidException If the access token is invalid
     */
    public function validateAccountToken(int $userId): WhatsAppAccount
    {
        $account = $this->getActiveAccount($userId);

        if (! $account) {
            throw new WhatsAppNotConnectedException(
                'No connected WhatsApp account found for this user.'
            );
        }

        // Check if token has expired based on stored expiration time
        if ($account->token_expires_at !== null && $account->token_expires_at->isPast()) {
            throw new WhatsAppTokenExpiredException(
                'Your WhatsApp access token has expired. Please re-authenticate.'
            );
        }

        // Check if access token is empty or null (invalid)
        if (empty($account->access_token)) {
            throw new WhatsAppTokenInvalidException(
                'Your WhatsApp access token is invalid. Please re-authenticate.'
            );
        }

        return $account;
    }

    /**
     * Check if a token validation result indicates an invalid token.
     *
     * This is used to process API responses and throw appropriate exceptions.
     *
     * @param  array  $validationResult  Result from EmbeddedSignupService::validateToken
     * @param  bool  $isValid  Whether the token is valid
     * @param  bool  $isExpired  Whether the token is expired
     *
     * @throws WhatsAppTokenExpiredException If the token is expired
     * @throws WhatsAppTokenInvalidException If the token is invalid
     */
    public function handleTokenValidationResult(bool $isValid, bool $isExpired = false): void
    {
        if ($isExpired) {
            throw new WhatsAppTokenExpiredException(
                'Your WhatsApp access token has expired. Please re-authenticate.'
            );
        }

        if (! $isValid) {
            throw new WhatsAppTokenInvalidException(
                'Your WhatsApp access token is invalid. Please re-authenticate.'
            );
        }
    }
}
