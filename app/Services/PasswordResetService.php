<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PasswordResetService
{
    protected int $expirationMinutes;

    public function __construct()
    {
        $this->expirationMinutes = (int) config('auth.passwords.users.expire', 60);
    }

    public function createToken(string $email): string
    {
        $this->cleanupExpired();

        DB::table('password_reset_tokens')
            ->where('email', $email)
            ->delete();

        $token = Str::random(64);

        DB::table('password_reset_tokens')->insert([
            'email' => $email,
            'token' => hash('sha256', $token),
            'created_at' => now(),
        ]);

        return $token;
    }

    public function verifyToken(string $token): ?string
    {
        $this->cleanupExpired();

        $hashedToken = hash('sha256', $token);

        $reset = DB::table('password_reset_tokens')
            ->where('token', $hashedToken)
            ->where('created_at', '>', now()->subMinutes($this->expirationMinutes))
            ->first();

        return $reset ? $reset->email : null;
    }

    public function deleteToken(string $token): void
    {
        $hashedToken = hash('sha256', $token);

        DB::table('password_reset_tokens')
            ->where('token', $hashedToken)
            ->delete();
    }

    public function isEmailRegistered(string $email): bool
    {
        return DB::table('users')
            ->where('email', $email)
            ->exists();
    }

    protected function cleanupExpired(): void
    {
        DB::table('password_reset_tokens')
            ->where('created_at', '<=', now()->subMinutes($this->expirationMinutes))
            ->delete();
    }
}
