<?php

use App\Exceptions\EmbeddedSignupDisabledException;
use App\Exceptions\EncryptionException;
use App\Exceptions\NoActiveProviderException;
use App\Exceptions\ProviderException;
use App\Exceptions\RLSViolationException;
use App\Exceptions\UnsupportedProviderException;
use App\Exceptions\WhatsAppNotConnectedException;
use App\Exceptions\WhatsAppTokenExpiredException;
use App\Exceptions\WhatsAppTokenInvalidException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // CORS configuration
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        // Apply localization middleware to all routes
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\LocalizationMiddleware::class,
            \App\Http\Middleware\SetPostgresUserContext::class,
        ]);
        
        $middleware->appendToGroup('api', [
            \App\Http\Middleware\LocalizationMiddleware::class,
            \App\Http\Middleware\SetPostgresUserContext::class,
        ]);

        // Disable CSRF for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhook/*',
            'broadcasting/*',
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'localization' => \App\Http\Middleware\LocalizationMiddleware::class,
            'check.web.auth' => \App\Http\Middleware\CheckWebAuth::class,
            'whatsapp.connected' => \App\Http\Middleware\EnsureWhatsAppConnected::class,
            'qris.rate_limit' => \App\Http\Middleware\QrisRateLimiter::class,
            'admin.session' => \App\Http\Middleware\AdminSessionValidation::class,
            'postgres.user.context' => \App\Http\Middleware\SetPostgresUserContext::class,
            'sanitize.provider.errors' => \App\Http\Middleware\SanitizeProviderErrors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle Encryption Exception
        $exceptions->render(function (EncryptionException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $e->getErrorCode(),
                        'message' => $e->getUserMessage(),
                    ],
                ], $e->getCode());
            }
        });

        // Handle RLS Violation Exception
        $exceptions->render(function (RLSViolationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $e->getErrorCode(),
                        'message' => $e->getUserMessage(),
                    ],
                ], $e->getCode());
            }
        });

        // Handle Provider Exception
        $exceptions->render(function (ProviderException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
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
        });

        // Handle No Active Provider Exception
        $exceptions->render(function (NoActiveProviderException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => $e->getErrorCode(),
                        'message' => $e->getUserMessage(),
                    ],
                ], $e->getCode());
            }
        });

        // Handle Unsupported Provider Exception
        $exceptions->render(function (UnsupportedProviderException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'UNSUPPORTED_PROVIDER',
                        'message' => $e->getMessage(),
                        'supported_providers' => UnsupportedProviderException::getSupportedProviders(),
                    ],
                ], 400);
            }
        });

        // Handle WhatsApp Not Connected Exception
        $exceptions->render(function (WhatsAppNotConnectedException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ], $e->getCode());
            }
        });

        // Handle WhatsApp Token Expired Exception
        $exceptions->render(function (WhatsAppTokenExpiredException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ], $e->getCode());
            }
        });

        // Handle WhatsApp Token Invalid Exception
        $exceptions->render(function (WhatsAppTokenInvalidException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ], $e->getCode());
            }
        });

        // Handle Embedded Signup Disabled Exception
        $exceptions->render(function (EmbeddedSignupDisabledException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'error_code' => $e->getErrorCode(),
                    'message' => $e->getMessage(),
                ], $e->getCode());
            }
        });
    })->create();
