<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\PosUser;
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
        $posUsers = PosUser::with(['user', 'store', 'role'])
            ->when($request->input('store_id'), fn($q, $storeId) => $q->where('store_id', $storeId))
            ->when($request->boolean('active_only', false), fn($q) => $q->where('is_active', true))
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
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at,
                'is_email_verified' => !is_null($user->email_verified_at),
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

        if (!$isValid) {
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
                'is_email_verified' => !is_null($user->email_verified_at),
            ],
        ]);
    }

    /**
     * Get available users (for assignment).
     */
    public function getAvailableUsers(Request $request): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'email_verified_at')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'is_email_verified' => !is_null($user->email_verified_at),
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
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'store_id' => 'required|exists:stores,id',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        // Check if user already has a POS user record for this store
        $existing = PosUser::where('user_id', $validated['user_id'])
            ->where('store_id', $validated['store_id'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'User already assigned to this store',
            ], 400);
        }

        $posUser = PosUser::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'POS user created successfully',
            'data' => $posUser->load(['user', 'store', 'role']),
        ], 201);
    }

    /**
     * Display the specified POS user.
     */
    public function show(PosUser $posUser): JsonResponse
    {
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
        $validated = $request->validate([
            'store_id' => 'sometimes|exists:stores,id',
            'role_id' => 'sometimes|exists:roles,id',
            'is_active' => 'sometimes|boolean',
        ]);

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
    public function deactivate(PosUser $posUser): JsonResponse
    {
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
    public function activate(PosUser $posUser): JsonResponse
    {
        $posUser->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'POS user activated successfully',
            'data' => $posUser->fresh()->load(['user', 'store', 'role']),
        ]);
    }

    /**
     * Remove the specified POS user.
     */
    public function destroy(PosUser $posUser): JsonResponse
    {
        $posUser->delete();

        return response()->json([
            'success' => true,
            'message' => 'POS user deleted successfully',
        ]);
    }
}
