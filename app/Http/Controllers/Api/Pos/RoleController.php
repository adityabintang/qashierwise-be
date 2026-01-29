<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::where('guard_name', 'sanctum')
            ->with('permissions')
            ->orderBy('name', 'asc')
            ->get()
            ->map(function ($role) {
                // Manually count users from model_has_roles table
                $usersCount = \DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', 'App\\Models\\User')
                    ->count();

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'permissions' => $role->permissions->pluck('name')->toArray(),
                    'pos_users_count' => $usersCount,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    /**
     * Get available permissions.
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'sanctum')
            ->orderBy('name', 'asc')
            ->get()
            ->mapWithKeys(function ($permission) {
                return [$permission->name => $this->getPermissionDescription($permission->name)];
            });

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // Only master admin can create roles
        if (! $currentUser->isMasterAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only master admin can create roles',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        // Check if role name already exists
        $existingRole = Role::where('guard_name', 'sanctum')
            ->where('name', $validated['name'])
            ->first();

        if ($existingRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role name already exists',
            ], 422);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'sanctum',
        ]);

        if (! empty($validated['permissions'])) {
            $role->givePermissionTo($validated['permissions']);
        }

        $role->load('permissions');

        // Manually count users from model_has_roles table
        $usersCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'pos_users_count' => $usersCount,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if ($role->guard_name !== 'sanctum') {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $role->load('permissions');

        // Manually count users from model_has_roles table
        $usersCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'pos_users_count' => $usersCount,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $currentUser = $request->user();

        // Only master admin can update roles
        if (! $currentUser->isMasterAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only master admin can update roles',
            ], 403);
        }

        if ($role->guard_name !== 'sanctum') {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string|exists:permissions,name',
        ]);

        // Check if role name already exists (excluding current role)
        if (isset($validated['name'])) {
            $existingRole = Role::where('guard_name', 'sanctum')
                ->where('name', $validated['name'])
                ->where('id', '!=', $role->id)
                ->first();

            if ($existingRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role name already exists',
                ], 422);
            }

            $role->name = $validated['name'];
            $role->save();
        }

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        $role->load('permissions');

        // Manually count users from model_has_roles table
        $usersCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
                'pos_users_count' => $usersCount,
                'created_at' => $role->created_at,
                'updated_at' => $role->updated_at,
            ],
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $currentUser = $request->user();

        // Only master admin can delete roles
        if (! $currentUser->isMasterAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only master admin can delete roles',
            ], 403);
        }

        if ($role->guard_name !== 'sanctum') {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Check if role is assigned to any users via model_has_roles
        $userCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'App\\Models\\User')
            ->count();

        if ($userCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role. It is assigned to '.$userCount.' user(s).',
            ], 400);
        }

        // Delete directly via DB to bypass Spatie event issues
        \DB::table('role_has_permissions')->where('role_id', $role->id)->delete();
        \DB::table('roles')->where('id', $role->id)->delete();

        // Clear permission cache
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Get permission description for display.
     */
    private function getPermissionDescription(string $permission): string
    {
        $descriptions = [
            'view_products' => 'View Products',
            'manage_products' => 'Manage Products (Create/Edit/Delete)',
            'view_categories' => 'View Categories',
            'manage_categories' => 'Manage Categories (Create/Edit/Delete)',
            'view_orders' => 'View Orders',
            'manage_orders' => 'Manage Orders (Create/Edit/Cancel)',
            'process_payment' => 'Process Payments',
            'view_stores' => 'View Stores',
            'manage_stores' => 'Manage Stores (Create/Edit/Activate/Deactivate)',
            'view_tables' => 'View Tables',
            'manage_tables' => 'Manage Tables (Create/Edit/Delete)',
            'view_users' => 'View POS Users',
            'manage_users' => 'Manage POS Users (Create/Edit/Delete/Activate/Deactivate)',
            'view_roles' => 'View Roles',
            'manage_roles' => 'Manage Roles (Create/Edit/Delete)',
            'view_reports' => 'View Reports & Analytics',
            'view_transactions' => 'View Transaction History',
        ];

        return $descriptions[$permission] ?? ucwords(str_replace('_', ' ', $permission));
    }
}
