<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to clear Spatie Permission cache on each request.
 *
 * This middleware ensures complete tenant isolation by clearing the permission
 * cache before processing each authenticated request. This prevents permission
 * leakage between different users/tenants in a multi-tenant environment.
 *
 * IMPORTANT: This middleware should be placed AFTER auth:sanctum middleware
 * in the middleware stack to ensure the user is authenticated before clearing cache.
 */
class ClearPermissionCache
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only clear cache for authenticated requests
        // This prevents unnecessary cache clearing for public routes
        if ($request->user()) {
            // Clear the permission cache to ensure fresh permission data
            // for each request, preventing cross-tenant permission leakage
            app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

            \Log::debug('Permission cache cleared for user', [
                'user_id' => $request->user()->id,
                'email' => $request->user()->email,
                'url' => $request->url(),
            ]);
        }

        return $next($request);
    }
}
