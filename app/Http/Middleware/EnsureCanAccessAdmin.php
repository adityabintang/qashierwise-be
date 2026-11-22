<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the React admin (blog CMS) API. Mirrors the old Filament
 * User::canAccessPanel() rule: only super admins and authors get in.
 */
class EnsureCanAccessAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! ($user->isSuperAdmin() || $user->isAuthor())) {
            return response()->json([
                'success' => false,
                'message' => __('auth.admin_access_denied') ?: 'You are not allowed to access the admin panel.',
            ], 403);
        }

        return $next($request);
    }
}
