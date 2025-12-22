<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Additional authentication middleware for withdrawal operations.
 * 
 * Requires password confirmation for sensitive withdrawal operations.
 * Requirement 9.4: Require additional authentication for withdrawals
 */
class WithdrawalAuthentication
{
    /**
     * Session key for storing password confirmation timestamp.
     */
    public const PASSWORD_CONFIRMED_AT = 'withdrawal_password_confirmed_at';

    /**
     * Time in seconds that password confirmation is valid.
     * Default: 15 minutes (900 seconds)
     */
    public const PASSWORD_CONFIRMATION_TIMEOUT = 900;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication required',
                ],
            ], 401);
        }

        // Check if password is provided in the request
        $password = $request->input('password');

        if ($password === null || $password === '') {
            // Check if we have a recent password confirmation in session/cache
            if (!$this->hasRecentPasswordConfirmation($user->id)) {
                return $this->requirePasswordConfirmation();
            }
        } else {
            // Validate the provided password
            if (!$this->validatePassword($user, $password)) {
                Log::warning('Withdrawal authentication failed - invalid password', [
                    'user_id' => $user->id,
                    'ip' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PASSWORD',
                        'message' => 'The provided password is incorrect',
                    ],
                ], 403);
            }

            // Store password confirmation timestamp
            $this->storePasswordConfirmation($user->id);

            Log::info('Withdrawal password confirmed', [
                'user_id' => $user->id,
            ]);
        }

        return $next($request);
    }

    /**
     * Check if the user has a recent password confirmation.
     */
    private function hasRecentPasswordConfirmation(int $userId): bool
    {
        $cacheKey = $this->getPasswordConfirmationCacheKey($userId);
        $confirmedAt = cache()->get($cacheKey);

        if ($confirmedAt === null) {
            return false;
        }

        $expiresAt = $confirmedAt + self::PASSWORD_CONFIRMATION_TIMEOUT;
        
        return time() < $expiresAt;
    }

    /**
     * Store the password confirmation timestamp.
     */
    private function storePasswordConfirmation(int $userId): void
    {
        $cacheKey = $this->getPasswordConfirmationCacheKey($userId);
        cache()->put($cacheKey, time(), self::PASSWORD_CONFIRMATION_TIMEOUT);
    }

    /**
     * Get the cache key for password confirmation.
     */
    private function getPasswordConfirmationCacheKey(int $userId): string
    {
        return "withdrawal_password_confirmed:{$userId}";
    }

    /**
     * Validate the user's password.
     */
    private function validatePassword($user, string $password): bool
    {
        return Hash::check($password, $user->password);
    }

    /**
     * Return response requiring password confirmation.
     */
    private function requirePasswordConfirmation(): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'PASSWORD_CONFIRMATION_REQUIRED',
                'message' => 'Please confirm your password to proceed with this withdrawal operation',
            ],
            'requires_password' => true,
        ], 403);
    }

    /**
     * Clear password confirmation for a user (useful for testing or logout).
     */
    public static function clearPasswordConfirmation(int $userId): void
    {
        $cacheKey = "withdrawal_password_confirmed:{$userId}";
        cache()->forget($cacheKey);
    }

    /**
     * Get remaining time for password confirmation validity.
     */
    public static function getRemainingConfirmationTime(int $userId): int
    {
        $cacheKey = "withdrawal_password_confirmed:{$userId}";
        $confirmedAt = cache()->get($cacheKey);

        if ($confirmedAt === null) {
            return 0;
        }

        $expiresAt = $confirmedAt + self::PASSWORD_CONFIRMATION_TIMEOUT;
        $remaining = $expiresAt - time();

        return max(0, $remaining);
    }
}
