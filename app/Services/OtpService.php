<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OtpService
{
    protected int $expirationMinutes;

    protected int $otpLength;

    protected int $rateLimitMax;

    protected int $rateLimitMinutes;

    public function __construct()
    {
        $this->expirationMinutes = (int) config('otp.expiration_minutes', 10);
        $this->otpLength = (int) config('otp.length', 6);
        $this->rateLimitMax = (int) config('otp.rate_limit_max', 3);
        $this->rateLimitMinutes = (int) config('otp.rate_limit_minutes', 1);
    }

    public function generate(string $email, string $type): string
    {
        $this->cleanupExpired();

        $code = $this->generateRandomCode();

        $this->invalidatePreviousOtp($email, $type);

        DB::table('otp_codes')->insert([
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'expires_at' => now()->addMinutes($this->expirationMinutes),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    public function verify(string $email, string $code, string $type): bool
    {
        $this->cleanupExpired();

        $otp = DB::table('otp_codes')
            ->where('email', $email)
            ->where('code', $code)
            ->where('type', $type)
            ->where('expires_at', '>', now())
            ->whereNull('used_at')
            ->first();

        if (! $otp) {
            return false;
        }

        if ($otp->attempts >= 5) {
            return false;
        }

        DB::table('otp_codes')
            ->where('id', $otp->id)
            ->increment('attempts');

        $isVerified = $code === $otp->code;

        if ($isVerified) {
            DB::table('otp_codes')
                ->where('id', $otp->id)
                ->update([
                    'used_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($type === 'email_verification') {
                $user = User::where('email', $email)->first();
                if ($user && ! $user->email_verified_at) {
                    $user->email_verified_at = now();
                    $user->save();
                }
            }
        }

        return $isVerified;
    }

    public function isRateLimited(string $email, string $type): bool
    {
        $cacheKey = "otp_rate_limit:{$email}:{$type}";

        $attempts = Cache::get($cacheKey, 0);

        return $attempts >= $this->rateLimitMax;
    }

    public function recordAttempt(string $email, string $type): void
    {
        $cacheKey = "otp_rate_limit:{$email}:{$type}";

        Cache::increment($cacheKey);

        Cache::put($cacheKey, Cache::get($cacheKey), now()->addMinutes($this->rateLimitMinutes));
    }

    protected function generateRandomCode(): string
    {
        return str_pad((string) random_int(0, 999999), $this->otpLength, '0', STR_PAD_LEFT);
    }

    protected function invalidatePreviousOtp(string $email, string $type): void
    {
        DB::table('otp_codes')
            ->where('email', $email)
            ->where('type', $type)
            ->whereNull('used_at')
            ->update([
                'used_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function cleanupExpired(): void
    {
        DB::table('otp_codes')
            ->where('expires_at', '<', now())
            ->whereNull('used_at')
            ->delete();
    }

    public function getRemainingAttempts(string $email, string $type): int
    {
        $cacheKey = "otp_rate_limit:{$email}:{$type}";
        $attempts = Cache::get($cacheKey, 0);

        return max(0, $this->rateLimitMax - $attempts);
    }

    public function getTimeUntilNextAttempt(string $email, string $type): ?int
    {
        $cacheKey = "otp_rate_limit:{$email}:{$type}";

        if (! Cache::has($cacheKey)) {
            return null;
        }

        $ttl = Cache::get($cacheKey.':ttl', 0);

        if ($ttl <= 0) {
            return null;
        }

        return $ttl;
    }
}
