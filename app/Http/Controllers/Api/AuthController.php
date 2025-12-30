<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return ApiResponse::validationError($validator->errors());
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        // Create token with expiration (1 month)
        $expirationMinutes = (int) config('sanctum.expiration', 43200);
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ], 'messages.success.created', 201);
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

        if (!Auth::attempt($request->only('email', 'password'))) {
            return ApiResponse::unauthorized('messages.error.invalid_credentials');
        }

        $user = User::where('email', $request->email)->firstOrFail();
        
        // Create token with expiration (1 month)
        $expirationMinutes = (int) config('sanctum.expiration', 43200);
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('auth_token', ['*'], $expiresAt)->plainTextToken;

        return ApiResponse::success([
            'user' => $user,
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
        
        if (!$user) {
            return ApiResponse::unauthorized('messages.error.unauthorized');
        }

        $token = $user->currentAccessToken();
        
        if ($token) {
            $token->delete();
        }

        return ApiResponse::success(null, 'auth.logout_success');
    }

    /**
     * Get the authenticated User
     */
    public function me(Request $request)
    {
        return ApiResponse::success([
            'user' => $request->user()
        ]);
    }
}
