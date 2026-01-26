<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SendOtpNotification;
use App\Notifications\SendPasswordResetLinkNotification;
use App\Services\OtpService;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

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
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ],
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

        if (! Auth::attempt($request->only('email', 'password'))) {
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

        if (! $user) {
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
        $user = $request->user();

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

        $expirationMinutes = (int) config('auth.passwords.users.expire', 60);
        $notifiable->notify(new SendPasswordResetLinkNotification($token, $expirationMinutes));

        return ApiResponse::success([
            'message' => 'Password reset link has been sent to your email',
            'expires_in_minutes' => $expirationMinutes,
        ], 'Password reset link has been sent to your email');
    }

    /**
     * Reset password using token
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

        $email = $this->passwordResetService->verifyToken($request->token);

        if (! $email) {
            return ApiResponse::error('Invalid or expired password reset token', 400);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            return ApiResponse::error('User not found', 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $this->passwordResetService->deleteToken($request->token);

        return ApiResponse::success([
            'message' => 'Password has been reset successfully',
        ], 'Password has been reset successfully');
    }
}
