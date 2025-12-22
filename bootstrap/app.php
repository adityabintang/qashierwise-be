<?php

use App\Exceptions\EmbeddedSignupDisabledException;
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

        // Disable CSRF for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
            'webhook/*',
            'broadcasting/*',
        ]);

        // Register custom middleware aliases
        $middleware->alias([
            'check.web.auth' => \App\Http\Middleware\CheckWebAuth::class,
            'whatsapp.connected' => \App\Http\Middleware\EnsureWhatsAppConnected::class,
            'qris.rate_limit' => \App\Http\Middleware\QrisRateLimiter::class,
            'withdrawal.auth' => \App\Http\Middleware\WithdrawalAuthentication::class,
            'admin.session' => \App\Http\Middleware\AdminSessionValidation::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
