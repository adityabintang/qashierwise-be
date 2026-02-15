<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosUser;
use App\Models\Role;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class PosUserController extends Controller
{
    protected OtpService $otpService;

    public function __construct(OtpService $otpService)
    {
        $this->otpService = $otpService;
    }

    /**
     * Display a listing of POS users.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PosUser::with(['user', 'store', 'role']);

        // Use effective user ID (master admin ID for sub-accounts)
        $effectiveUserId = $user->getEffectiveUserId();

        // Show POS users from stores owned by the effective user (master admin)
        $query->whereHas('store', function ($q) use ($effectiveUserId) {
            $q->where('user_id', $effectiveUserId);
        });

        $posUsers = $query
            ->when($request->input('store_id'), fn ($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->boolean('active_only', false), fn ($q) => $q->where('is_active', true))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $posUsers,
        ]);
    }

    /**
     * Create a new user with email verification.
     */
    public function createUser(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'is_master_admin' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => ! is_null($user->email_verified_at),
            ],
        ], 201);
    }

    /**
     * Send OTP for email verification.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users',
        ]);

        $email = $validated['email'];
        $type = 'email_verification';

        if ($this->otpService->isRateLimited($email, $type)) {
            $remainingTime = $this->otpService->getTimeUntilNextAttempt($email, $type);

            return response()->json([
                'success' => false,
                'message' => 'Too many OTP requests. Please try again in '.$remainingTime.' seconds.',
            ], 429);
        }

        $this->otpService->recordAttempt($email, $type);

        $otp = $this->otpService->generate($email, $type);

        $notifiable = new class($email)
        {
            use Notifiable;

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

        $notifiable->notify(new \App\Notifications\SendOtpNotification($otp, $type, (int) config('otp.expiration_minutes')));

        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in_minutes' => (int) config('otp.expiration_minutes'),
        ]);
    }

    /**
     * Verify OTP for email.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users',
            'code' => 'required|string|size:6',
        ]);

        $email = $validated['email'];
        $code = $validated['code'];
        $type = 'email_verification';

        $isValid = $this->otpService->verify($email, $code, $type);

        if (! $isValid) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP code.',
            ], 400);
        }

        $user = User::where('email', $email)->first();

        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => ! is_null($user->email_verified_at),
            ],
        ]);
    }

    /**
     * Get available users (for assignment).
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $effectiveUserId = $currentUser->getEffectiveUserId();

        // Get stores owned by the effective user (master admin)
        $storeIds = \App\Models\Store::where('user_id', $effectiveUserId)->pluck('id');

        // Get user IDs that are already assigned as POS users in these stores
        $assignedUserIds = PosUser::whereIn('store_id', $storeIds)
            ->pluck('user_id')
            ->unique()
            ->values();

        // Only show users that are already assigned to this tenant's stores
        $users = User::select('id', 'name', 'email', 'email_verified_at')
            ->whereIn('id', $assignedUserIds)
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_email_verified' => ! is_null($user->email_verified_at),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Store a newly created POS user.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        $effectiveUserId = $currentUser->getEffectiveUserId();

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        // Verify that the store belongs to the current user (tenant isolation)
        $store = \App\Models\Store::where('id', $validated['store_id'])
            ->where('user_id', $effectiveUserId)
            ->first();

        if (! $store) {
            return response()->json([
                'success' => false,
                'message' => 'Store not found or you do not have permission to access it.',
            ], 403);
        }

        // STRICT OWNERSHIP CONSTRAINT: Verify user belongs to same merchant
        // A user can only be assigned as POS user for stores owned by the same merchant
        // that either created them or already owns them through the ownership chain
        $userToAssign = User::find($validated['user_id']);

        if (! $userToAssign) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        // If the user is a master admin, they can only be assigned to their own stores
        if ($userToAssign->isMasterAdmin() && $userToAssign->id !== $effectiveUserId) {
            \Log::warning('Strict ownership violation: attempting to assign another merchant\'s master admin', [
                'attempted_by' => $currentUser->id,
                'target_user_id' => $userToAssign->id,
                'target_user_email' => $userToAssign->email,
                'store_id' => $store->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Cannot assign users from another merchant.',
            ], 403);
        }

        // If the user is already assigned to another store in a different merchant, deny
        $existing = PosUser::where('user_id', $validated['user_id'])
            ->with('store')
            ->first();

        if ($existing) {
            $storeOwner = \App\Models\Store::find($existing->store_id);
            if ($storeOwner && $storeOwner->user_id !== $effectiveUserId) {
                \Log::warning('Strict ownership violation: user already owned by different merchant', [
                    'attempted_by' => $currentUser->id,
                    'target_user_id' => $userToAssign->id,
                    'existing_store_id' => $existing->store_id,
                    'existing_store_owner' => $storeOwner->user_id,
                    'attempted_store_id' => $store->id,
                    'attempted_store_owner' => $effectiveUserId,
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'User is already assigned to a different merchant.',
                ], 400);
            }

            // Same merchant, different store - check if duplicate
            if ($existing->store_id === (int) $validated['store_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'User already assigned to this store',
                ], 400);
            }
        }

        $posUser = PosUser::create($validated);

        // Assign role to user via Spatie with proper guard
        // CRITICAL: Use App\Models\Role (not Spatie\Permission\Models\Role) for consistency with config
        $role = Role::where('id', $validated['role_id'])
            ->where('guard_name', 'sanctum')
            ->first();

        if ($role) {
            $user = $posUser->user;
            // Ensure guard is set before assigning role
            $user->guard_name = 'sanctum';
            $user->assignRole($role->name);

            // CRITICAL: Clear permission cache so new role assignment is immediately effective
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            \Log::info('Role assigned to POS user', [
                'pos_user_id' => $posUser->id,
                'user_id' => $user->id,
                'role_id' => $role->id,
                'role_name' => $role->name,
                'permissions_count' => $role->permissions->count(),
            ]);
        } else {
            \Log::warning('Role not found for POS user assignment', [
                'pos_user_id' => $posUser->id,
                'role_id' => $validated['role_id'],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'POS user created successfully',
            'data' => $posUser->load(['user', 'store', 'role']),
        ], 201);
    }

    /**
     * Display the specified POS user.
     */
    public function show(PosUser $posUser, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($posUser->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $posUser->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Update the specified POS user.
     */
    public function update(Request $request, PosUser $posUser): JsonResponse
    {
        $effectiveUserId = auth()->user()->getEffectiveUserId();

        if ($posUser->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $validated = $request->validate([
            'store_id' => 'sometimes|exists:stores,id',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ]);

        // If role_id is being updated, sync with Spatie
        if (isset($validated['role_id'])) {
            // CRITICAL: Use App\Models\Role (not Spatie\Permission\Models\Role) for consistency with config
            $role = Role::where('id', $validated['role_id'])
                ->where('guard_name', 'sanctum')
                ->first();

            if ($role) {
                $user = $posUser->user;
                // Ensure guard is set before syncing role
                $user->guard_name = 'sanctum';
                $user->syncRoles([$role->name]);

                // CRITICAL: Clear permission cache so role update is immediately effective
                app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

                \Log::info('Role updated for POS user', [
                    'pos_user_id' => $posUser->id,
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'role_name' => $role->name,
                ]);
            } else {
                \Log::warning('Role not found for POS user update', [
                    'pos_user_id' => $posUser->id,
                    'role_id' => $validated['role_id'],
                ]);
            }
        }

        $posUser->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'POS user updated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Deactivate the specified POS user.
     */
    public function deactivate(PosUser $posUser, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($posUser->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $posUser->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'POS user deactivated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Activate the specified POS user.
     */
    public function activate(PosUser $posUser, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($posUser->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $posUser->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'POS user activated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Remove the specified POS user.
     * Also deletes the associated User account so they cannot login anymore.
     */
    public function destroy(PosUser $posUser, Request $request): JsonResponse
    {
        $effectiveUserId = $request->user()->getEffectiveUserId();

        if ($posUser->store->user_id !== $effectiveUserId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        $user = $posUser->user;

        // Delete POS user first
        $posUser->delete();

        // Then delete the User account (if not master admin)
        if ($user && ! $user->isMasterAdmin()) {
            $user->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'POS user and associated account deleted successfully',
        ]);
    }
}
