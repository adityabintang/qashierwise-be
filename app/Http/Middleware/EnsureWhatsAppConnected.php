<?php

namespace App\Http\Middleware;

use App\Exceptions\WhatsAppNotConnectedException;
use App\Services\WhatsAppAccountService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to ensure the authenticated user has a connected WhatsApp account.
 *
 * This middleware checks if the user has an active WhatsApp account before
 * allowing access to WhatsApp-related routes. If no account is connected,
 * it throws a WhatsAppNotConnectedException.
 *
 * Usage: Apply to routes that require WhatsApp account access.
 *
 * @see \App\Exceptions\WhatsAppNotConnectedException
 */
class EnsureWhatsAppConnected
{
    protected WhatsAppAccountService $whatsAppAccountService;

    public function __construct(WhatsAppAccountService $whatsAppAccountService)
    {
        $this->whatsAppAccountService = $whatsAppAccountService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *
     * @throws WhatsAppNotConnectedException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = auth()->id();

        if (! $userId) {
            throw new WhatsAppNotConnectedException(
                'Authentication required to access WhatsApp features.'
            );
        }

        if (! $this->whatsAppAccountService->hasConnectedAccount($userId)) {
            throw new WhatsAppNotConnectedException(
                'Please connect your WhatsApp Business account first.'
            );
        }

        return $next($request);
    }
}
