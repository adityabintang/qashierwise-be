<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPosPermission
{
    /**
     * Handle an incoming request.
     * Supports multiple permissions separated by pipe (|) for OR logic.
     * Example: pos.permission:view_products|manage_products
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        \Log::info('CheckPosPermission START', [
            'url' => $request->url(),
            'method' => $request->method(),
            'required_permission' => $permission,
            'has_user' => $user ? true : false,
            'user_id' => $user?->id,
            'email' => $user?->email,
        ]);

        if (! $user) {
            \Log::warning('CheckPosPermission: No user - RETURN 401');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Check if user is master admin (can access everything)
        if ($user->isMasterAdmin()) {
            \Log::info('CheckPosPermission: Master admin bypass - GRANTED');
            return $next($request);
        }

        // Load POS user to verify store assignment
        $posUser = $user->posUsers()->first();
        \Log::info('CheckPosPermission: POS User check', [
            'has_pos_user' => (bool)$posUser,
            'pos_user_id' => $posUser?->id,
            'store_id' => $posUser?->store_id,
            'is_active' => $posUser?->is_active,
        ]);

        // If user is not a POS user (no store assignment), deny access
        if (! $posUser) {
            \Log::warning('CheckPosPermission: No POS user found - DENIED - RETURN 403');
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        // Check if user has any of the required permissions (OR logic)
        $requiredPermissions = explode('|', $permission);
        $allPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $hasAnyPermission = false;

        foreach ($requiredPermissions as $perm) {
            if ($user->hasPermissionTo(trim($perm), 'sanctum')) {
                $hasAnyPermission = true;
                break;
            }
        }

        \Log::info('CheckPosPermission: Permission check', [
            'required_permissions' => $requiredPermissions,
            'has_any_permission' => $hasAnyPermission,
            'all_permissions' => $allPermissions,
            'permissions_count' => count($allPermissions),
        ]);

        if (! $hasAnyPermission) {
            \Log::warning('CheckPosPermission: Permission denied - RETURN 403', [
                'required' => $requiredPermissions,
                'has' => $allPermissions,
            ]);
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to access this resource.',
            ], 403);
        }

        \Log::info('CheckPosPermission: GRANTED - PASS THROUGH');
        return $next($request);
    }
}
