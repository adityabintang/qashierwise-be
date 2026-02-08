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
     *
     * Access levels:
     * - Super admin (admin@qashierwise.com): Can access everything across all merchants
     * - Master admin: Can access their own merchant's data
     * - Sub-account: Limited to assigned store permissions
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
            'is_master_admin' => $user?->isMasterAdmin(),
            'is_super_admin' => $user?->isSuperAdmin(),
        ]);

        if (! $user) {
            \Log::warning('CheckPosPermission: No user - RETURN 401');
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        // Super admin (admin@qashierwise.com) has full system access
        // This is a system-wide administrator, not tied to any merchant
        if ($user->isSuperAdmin()) {
            \Log::info('CheckPosPermission: Super admin bypass - GRANTED');
            return $next($request);
        }

        // Check if user is master admin (can access everything for their merchant)
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

        // Check if POS user is active
        if (!$posUser->is_active) {
            \Log::warning('CheckPosPermission: POS user inactive - DENIED - RETURN 403');
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive. Please contact your administrator.',
            ], 403);
        }

        // Check if user has any of the required permissions (OR logic)
        $requiredPermissions = explode('|', $permission);
        $hasAnyPermission = false;

        foreach ($requiredPermissions as $perm) {
            if ($user->hasPermissionTo(trim($perm), 'sanctum')) {
                $hasAnyPermission = true;
                break;
            }
        }

        // Get user permissions via direct query for logging (bypasses JSON column conflict)
        $allPermissions = $user->getPermissionsViaDirectQuery();

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
