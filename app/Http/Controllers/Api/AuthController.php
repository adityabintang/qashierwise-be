<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\PosUser;
use App\Models\Store;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Notifications\SendPasswordResetLinkNotification;
use App\Services\OtpService;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    protected OtpService $otpService;

    protected PasswordResetService $passwordResetService;

    public function __construct(OtpService $otpService, PasswordResetService $passwordResetService)
    {
        $this->otpService = $otpService;
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * Register a new user with optional store creation.
     * If store_name is provided, user becomes master admin for that store.
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'store_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $hasStore = !empty($request->store_name);

        // Create user - mark as master admin if creating a store
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'is_master_admin' => $hasStore,
        ]);

        $store = null;

        // If store_name is provided, create the store and associate with user
        if ($hasStore) {
            // Check if user already has a store with this name
            $existingStore = Store::where('user_id', $user->id)
                ->where('name', $request->store_name)
                ->first();

            if ($existingStore) {
                // Rollback user creation if store validation fails
                $user->delete();

                return ApiResponse::validationError([
                    'store_name' => ['You already have a store with this name.'],
                ]);
            }

            $store = Store::create([
                'user_id' => $user->id,
                'name' => $request->store_name,
                'code' => $this->generateStoreCode($request->store_name),
                'is_active' => true,
            ]);

            \Log::info('New merchant registered with store', [
                'user_id' => $user->id,
                'email' => $user->email,
                'store_id' => $store->id,
                'store_name' => $store->name,
            ]);
        }

        $otp = $this->otpService->generate($user->email, 'email_verification');
        $user->notify(new SendOtpNotification($otp, 'email_verification', (int) config('otp.expiration_minutes')));

        // Create token with expiration (1 month)
        $expirationMinutes = (int) config('sanctum.expiration', 43200);
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => ! is_null($user->email_verified_at),
                'is_master_admin' => $user->is_master_admin,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'store' => $hasStore ? [
                'id' => $store->id,
                'name' => $store->name,
                'code' => $store->code,
                'is_active' => $store->is_active,
            ] : null,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], 'messages.success.created', 201);
    }

    /**
     * Generate a unique store code from store name.
     */
    private function generateStoreCode(string $name): string
    {
        $baseCode = Str::upper(Str::slug($name, ''));
        $code = $baseCode;
        $counter = 1;

        while (Store::where('code', $code)->exists()) {
            $code = $baseCode . $counter;
            $counter++;
        }

        return $code;
    }

    /**
     * Login user and create token
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        if (! Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::unauthorized('messages.error.invalid_credentials');
        }

        $user = User::where('email', $request->email)->firstOrFail();

        // CRITICAL: Clear permission cache to prevent cross-tenant permission leakage
        // This ensures each user session starts with fresh permission data
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Check if user is a POS user and if their account is active
        $posUser = PosUser::where('user_id', $user->id)->first();
        if ($posUser && ! $posUser->is_active) {
            Auth::logout();

            return ApiResponse::error('Your account is inactive. Please contact your administrator.', 403);
        }

        // Create token with expiration (1 month)
        $expirationMinutes = (int) config('sanctum.expiration', 43200);
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => ! is_null($user->email_verified_at),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], 'auth.login_success');
    }

    /**
     * Logout user (Revoke the token)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return ApiResponse::unauthorized('messages.error.unauthorized');
        }

        // CRITICAL: Delete ALL tokens for this user to prevent session leakage
        // This ensures complete logout across all devices/sessions
        $user->tokens()->delete();

        // CRITICAL: Clear permission cache to prevent cross-tenant permission leakage
        // This ensures no cached permission data persists after logout
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Clear admin session cache if applicable
        \App\Http\Middleware\AdminSessionValidation::invalidateSession($user->id);

        // Clear Laravel session
        $request->session()->flush();
        $request->session()->regenerateToken();

        \Log::info('User logged out - all tokens deleted', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return ApiResponse::success(null, 'auth.logout_success');
    }

    /**
     * Get the authenticated User
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => ! is_null($user->email_verified_at),
                'is_super_admin' => $user->isSuperAdmin(),
                'is_master_admin' => $user->isMasterAdmin(),
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
        ]);
    }

    /**
     * Get user permissions based on their role
     */
    public function getUserPermissions(Request $request)
    {
        // CRITICAL: Force reload user from database to prevent permission cache leakage
        // This ensures fresh permission data for each request
        $user = User::find($request->user()->id);

        // Clear any Spatie permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        \Log::info('=== getUserPermissions DEBUG ===');
        \Log::info('User ID: ' . $user->id);
        \Log::info('User Email: ' . $user->email);
        \Log::info('Is Super Admin: ' . ($user->isSuperAdmin() ? 'true' : 'false'));
        \Log::info('Is Master Admin: ' . ($user->isMasterAdmin() ? 'true' : 'false'));

        // Get all available permissions from database
        $allPermissions = \Spatie\Permission\Models\Permission::where('guard_name', 'sanctum')
            ->pluck('name')
            ->sort()
            ->values()
            ->toArray();

        // Super admin has full system access
        if ($user->isSuperAdmin()) {
            \Log::info('Super admin - granting all permissions');
            $response = ApiResponse::success([
                'is_super_admin' => true,
                'is_master_admin' => false,
                'is_admin' => true,
                'permissions' => $allPermissions, // All permissions from database
                'role' => 'super_admin',
            ]);

            return $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                'X-User-ID' => $user->id,
            ]);
        }

        // Master admin has full access to their merchant
        if ($user->isMasterAdmin()) {
            \Log::info('Master admin - granting all permissions');
            $response = ApiResponse::success([
                'is_super_admin' => false,
                'is_master_admin' => true,
                'is_admin' => true,
                'permissions' => $allPermissions, // All permissions from database
                'role' => 'master_admin',
            ]);

            return $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                'X-User-ID' => $user->id,
            ]);
        }

        // Check if user has a POS user record
        $posUser = $user->posUsers()->first();

        // If no POS user and not master admin, return minimal permissions
        if (!$posUser) {
            \Log::info('No POS user found - returning empty permissions');
            $response = ApiResponse::success([
                'is_super_admin' => false,
                'is_master_admin' => false,
                'is_admin' => false,
                'permissions' => [],
                'role' => null,
            ]);

            return $response->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
                'X-User-ID' => $user->id,
            ]);
        }

        // Get user roles and permissions using User model methods
        // These methods bypass JSON column conflict in roles table
        $roles = $user->getRoleNamesViaDirectQuery();
        \Log::info('User roles: ' . json_encode($roles));

        $permissions = $user->getPermissionsViaDirectQuery();

        \Log::info('Final permissions array: ' . json_encode($permissions));
        \Log::info('Final roles: ' . json_encode($roles));

        $response = ApiResponse::success([
            'is_super_admin' => false,
            'is_master_admin' => false,
            'is_admin' => false,
            'permissions' => $permissions,
            'roles' => $roles,
        ]);

        // Add cache-control headers to prevent browser/API caching
        return $response->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Thu, 01 Jan 1970 00:00:00 GMT',
            'X-User-ID' => $user->id, // Debug header to verify correct user
        ]);
    }

    /**
     * Check if user has a specific permission
     */
    public function checkPermission(Request $request)
    {
        $request->validate([
            'permission' => 'required|string',
        ]);

        $user = $request->user();
        $permission = $request->input('permission');

        // hasPermissionTo works correctly even with JSON column conflict
        // It queries the database directly
        $hasPermission = $user->hasPermissionTo($permission, 'sanctum');

        return ApiResponse::success([
            'has_permission' => $hasPermission,
            'permission' => $permission,
        ]);
    }

    /**
     * Send OTP for email verification or password reset
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type' => 'nullable|in:email_verification,password_reset',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $type = $request->input('type', 'email_verification');
        $email = $request->email;

        if ($this->otpService->isRateLimited($email, $type)) {
            $remainingTime = $this->otpService->getTimeUntilNextAttempt($email, $type);

            return ApiResponse::error('Too many OTP requests. Please try again in '.$remainingTime.' seconds.', 429);
        }

        $this->otpService->recordAttempt($email, $type);

        $otp = $this->otpService->generate($email, $type);

        $notifiable = new class($email)
        {
            public $email;

            public function __construct($email)
            {
                $this->email = $email;
            }

            public function routeNotificationForMail($notification)
            {
                return $this->email;
            }
        };

        $notifiable->notify(new SendOtpNotification($otp, $type, (int) config('otp.expiration_minutes')));

        return ApiResponse::success([
            'message' => 'OTP sent successfully',
            'type' => $type,
            'expires_in_minutes' => (int) config('otp.expiration_minutes'),
        ], 'OTP sent successfully');
    }

    /**
     * Verify OTP code
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'type' => 'required|in:email_verification,password_reset',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $email = $request->email;
        $code = $request->code;
        $type = $request->type;

        $isValid = $this->otpService->verify($email, $code, $type);

        if (! $isValid) {
            return ApiResponse::error('Invalid or expired OTP code.', 400);
        }

        if ($type === 'email_verification') {
            $user = User::where('email', $email)->first();
            if ($user) {
                return ApiResponse::success([
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'email_verified_at' => $user->email_verified_at,
                        'is_email_verified' => ! is_null($user->email_verified_at),
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ],
                    'message' => 'Email verified successfully',
                ], 'Email verified successfully');
            }
        }

        return ApiResponse::success([
            'message' => 'OTP verified successfully',
        ], 'OTP verified successfully');
    }

    /**
     * Resend OTP
     */
    public function resendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'type' => 'nullable|in:email_verification,password_reset',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $type = $request->input('type', 'email_verification');
        $email = $request->email;

        if ($this->otpService->isRateLimited($email, $type)) {
            $remainingTime = $this->otpService->getTimeUntilNextAttempt($email, $type);

            return ApiResponse::error('Too many OTP requests. Please try again in '.$remainingTime.' seconds.', 429);
        }

        $this->otpService->recordAttempt($email, $type);

        $otp = $this->otpService->generate($email, $type);

        $notifiable = new class($email)
        {
            public $email;

            public function __construct($email)
            {
                $this->email = $email;
            }

            public function routeNotificationForMail($notification)
            {
                return $this->email;
            }
        };

        $notifiable->notify(new SendOtpNotification($otp, $type, (int) config('otp.expiration_minutes')));

        return ApiResponse::success([
            'message' => 'OTP resent successfully',
            'type' => $type,
            'expires_in_minutes' => (int) config('otp.expiration_minutes'),
        ], 'OTP resent successfully');
    }

    /**
     * Forgot password - send reset link to email
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $email = $request->email;

        if (! $this->passwordResetService->isEmailRegistered($email)) {
            return ApiResponse::error('Email yang disebutkan atau diinputkan tidak terdaftar di aplikasi', 400);
        }

        $token = $this->passwordResetService->createToken($email);

        $notifiable = new class($email)
        {
            use \Illuminate\Notifications\Notifiable;

            public $email;

            public function __construct($email)
            {
                $this->email = $email;
            }

            public function routeNotificationForMail($notification)
            {
                return $this->email;
            }
        };

        $notifiable->notify(new SendPasswordResetLinkNotification($token));

        return ApiResponse::success(null, 'Password reset link sent to your email');
    }

    /**
     * Reset password using the token
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $token = $request->token;
        $password = $request->password;

        // Verify token and get email
        $email = $this->passwordResetService->verifyToken($token);

        if (! $email) {
            return ApiResponse::error('Invalid or expired reset token.', 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return ApiResponse::error('User not found.', 404);
        }

        $user->password = Hash::make($password);
        $user->save();

        // Delete the used token
        $this->passwordResetService->deleteToken($token);

        return ApiResponse::success(null, 'Password reset successfully');
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'language_preference' => 'nullable|string|in:en,id',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = $request->user();

        $user->name = $request->name;

        if ($request->has('language_preference')) {
            $user->language_preference = $request->language_preference;
        }

        $user->save();

        return ApiResponse::success([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'language_preference' => $user->language_preference,
                'updated_at' => $user->updated_at,
            ],
        ], 'Profile updated successfully');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return ApiResponse::error('Current password is incorrect.', 400);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        // Revoke all tokens for security
        $user->tokens()->delete();

        return ApiResponse::success(null, 'Password changed successfully. Please login again.');
    }
}
