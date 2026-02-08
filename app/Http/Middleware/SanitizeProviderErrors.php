<?php

namespace App\Http\Middleware;

use App\Exceptions\EncryptionException;
use App\Exceptions\NoActiveProviderException;
use App\Exceptions\ProviderException;
use App\Exceptions\RLSViolationException;
use App\Exceptions\UnsupportedProviderException;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Middleware to sanitize and handle errors from the multi-provider QRIS system.
 *
 * This middleware:
 * - Sanitizes encryption errors to prevent technical detail exposure
 * - Handles RLS violations with generic security messages
 * - Classifies provider errors (network vs credential vs provider)
 * - Maps errors to user-friendly messages
 *
 * Requirements: 12.1, 12.2, 12.3, 12.5
 */
class SanitizeProviderErrors
{
    /**
     * PostgreSQL error codes for RLS violations.
     */
    private const RLS_ERROR_CODES = [
        '42501', // insufficient_privilege
        '42P01', // undefined_table (can occur with RLS)
    ];

    /**
     * Network-related error indicators.
     */
    private const NETWORK_ERROR_INDICATORS = [
        'connection refused',
        'connection timed out',
        'could not resolve host',
        'network is unreachable',
        'timeout',
        'curl error',
        'failed to connect',
        'connection reset',
    ];

    /**
     * Credential-related error indicators.
     */
    private const CREDENTIAL_ERROR_INDICATORS = [
        'unauthorized',
        'invalid credentials',
        'authentication failed',
        'invalid api key',
        'invalid token',
        'access denied',
        '401',
        '403',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } catch (Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    /**
     * Handle exceptions and return sanitized responses.
     */
    private function handleException(Throwable $e, Request $request): Response
    {
        // Handle encryption errors
        if ($e instanceof EncryptionException) {
            return $this->handleEncryptionError($e, $request);
        }

        // Handle RLS violations
        if ($this->isRLSViolation($e)) {
            return $this->handleRLSViolation($e, $request);
        }

        // Handle provider exceptions
        if ($e instanceof ProviderException) {
            return $this->handleProviderError($e, $request);
        }

        // Handle no active provider
        if ($e instanceof NoActiveProviderException) {
            return $this->handleNoActiveProvider($e, $request);
        }

        // Handle unsupported provider
        if ($e instanceof UnsupportedProviderException) {
            return $this->handleUnsupportedProvider($e, $request);
        }

        // Handle RLS violation exception
        if ($e instanceof RLSViolationException) {
            return $this->handleRLSViolation($e, $request);
        }

        // Classify and handle unknown exceptions
        return $this->handleUnknownException($e, $request);
    }

    /**
     * Handle encryption errors with sanitized messages.
     */
    private function handleEncryptionError(EncryptionException $e, Request $request): Response
    {
        // Log the technical details for debugging
        Log::error('Encryption error occurred', [
            'message' => $e->getMessage(),
            'user_id' => $request->user()?->id,
            'route' => $request->route()?->getName(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Return sanitized error to user
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $e->getErrorCode(),
                'message' => $e->getUserMessage(),
            ],
        ], $e->getCode());
    }

    /**
     * Handle RLS violations with generic security messages.
     */
    private function handleRLSViolation(Throwable $e, Request $request): Response
    {
        // Log the violation attempt with details
        Log::warning('RLS violation detected', [
            'user_id' => $request->user()?->id,
            'route' => $request->route()?->getName(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'error' => $e->getMessage(),
        ]);

        // Return generic security error without details
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'ACCESS_DENIED',
                'message' => 'You do not have permission to access this resource.',
            ],
        ], 403);
    }

    /**
     * Handle provider errors with classification.
     */
    private function handleProviderError(ProviderException $e, Request $request): Response
    {
        // Log provider error with classification
        Log::error('Provider error occurred', [
            'provider' => $e->getProviderName(),
            'error_type' => $e->getErrorType(),
            'error_code' => $e->getProviderErrorCode(),
            'message' => $e->getMessage(),
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $e->getErrorCode(),
                'message' => $e->getUserMessage(),
                'provider' => $e->getProviderName(),
                'error_type' => $e->getErrorType(),
            ],
        ], $e->getCode());
    }

    /**
     * Handle no active provider error.
     */
    private function handleNoActiveProvider(NoActiveProviderException $e, Request $request): Response
    {
        Log::info('No active provider configured', [
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => $e->getErrorCode(),
                'message' => $e->getUserMessage(),
            ],
        ], $e->getCode());
    }

    /**
     * Handle unsupported provider error.
     */
    private function handleUnsupportedProvider(UnsupportedProviderException $e, Request $request): Response
    {
        Log::warning('Unsupported provider requested', [
            'message' => $e->getMessage(),
            'user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'UNSUPPORTED_PROVIDER',
                'message' => $e->getMessage(),
                'supported_providers' => UnsupportedProviderException::getSupportedProviders(),
            ],
        ], 400);
    }

    /**
     * Handle unknown exceptions by classifying them.
     */
    private function handleUnknownException(Throwable $e, Request $request): Response
    {
        $errorMessage = strtolower($e->getMessage());

        // Check if it's a network error
        if ($this->isNetworkError($errorMessage)) {
            Log::error('Network error detected', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'NETWORK_ERROR',
                    'message' => 'Unable to connect to the payment provider. Please check your internet connection and try again.',
                ],
            ], 503);
        }

        // Check if it's a credential error
        if ($this->isCredentialError($errorMessage)) {
            Log::error('Credential error detected', [
                'message' => $e->getMessage(),
                'user_id' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'CREDENTIAL_ERROR',
                    'message' => 'Invalid payment provider credentials. Please verify your API keys and try again.',
                ],
            ], 401);
        }

        // Log unknown error and return generic message
        Log::error('Unknown error occurred', [
            'message' => $e->getMessage(),
            'user_id' => $request->user()?->id,
            'route' => $request->route()?->getName(),
            'trace' => $e->getTraceAsString(),
        ]);

        // Don't expose internal errors to users
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'INTERNAL_ERROR',
                'message' => 'An error occurred while processing your request. Please try again later.',
            ],
        ], 500);
    }

    /**
     * Check if the exception is an RLS violation.
     */
    private function isRLSViolation(Throwable $e): bool
    {
        if (! ($e instanceof QueryException)) {
            return false;
        }

        $errorCode = $e->getCode();
        $errorMessage = strtolower($e->getMessage());

        // Check PostgreSQL error codes
        if (in_array($errorCode, self::RLS_ERROR_CODES)) {
            return true;
        }

        // Check error message for RLS-related keywords
        return str_contains($errorMessage, 'row level security') ||
               str_contains($errorMessage, 'policy') ||
               str_contains($errorMessage, 'insufficient privilege');
    }

    /**
     * Check if the error message indicates a network error.
     */
    private function isNetworkError(string $errorMessage): bool
    {
        foreach (self::NETWORK_ERROR_INDICATORS as $indicator) {
            if (str_contains($errorMessage, $indicator)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the error message indicates a credential error.
     */
    private function isCredentialError(string $errorMessage): bool
    {
        foreach (self::CREDENTIAL_ERROR_INDICATORS as $indicator) {
            if (str_contains($errorMessage, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
