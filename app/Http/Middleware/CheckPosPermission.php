<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPosPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Load POS user with role
        $posUser = $user->posUsers()->with('role')->first();

        // If user is not a POS user, allow access (main admin/owner)
        if (!$posUser) {
            return $next($request);
        }

        // If POS user has no role, deny access
        if (!$posUser->role) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned. Please contact administrator.',
            ], 403);
        }

        // Check if user's role has the required permission
        $permissions = $posUser->role->permissions ?? [];

        if (!in_array($permission, $permissions)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
