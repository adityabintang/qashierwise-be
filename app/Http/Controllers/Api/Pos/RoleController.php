<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Helper method to clear conflicting 'permissions' JSON column before loading relationship.
     */
    public function clearPermissionsAttributeAndLoad(Role $role): void
    {
        // Remove permissions JSON column from attributes array
        $attributes = $role->getAttributes();
        unset($attributes['permissions']);

        $reflection = new \ReflectionClass($role);
        $property = $reflection->getProperty('attributes');
        $property->setAccessible(true);
        $property->setValue($role, $attributes);

        // Now load the relationship properly
        $role->load('permissions');
    }

    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        // All users (including super admin per merchant) can only see roles they created
        // Each merchant admin has isolated data - they can only see their own roles
        $roles = Role::where('guard_name', 'sanctum')
            ->where('user_id', $currentUser->id)
            ->with('permissions')
            ->orderBy('name', 'asc')
            ->get();

        // Get stores owned by the current user
        $storeIds = \App\Models\Store::where('user_id', $currentUser->id)->pluck('id');

        $roles = $roles->map(function ($role) use ($storeIds) {
            // Clear conflicting 'permissions' JSON column and load relationship
            $this->clearPermissionsAttributeAndLoad($role);

            // Count users from model_has_roles table who are POS users in these stores
            $usersCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', function ($query) use ($storeIds) {
                    $query->select('user_id')
                        ->from('pos_users')
                        ->whereIn('store_id', $storeIds);
                })
                ->count();

            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions ? $role->permissions->pluck('name')->toArray() : [],
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
     * Only returns POS-related permissions (excludes blog permissions).
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'sanctum')
            ->where(function ($query) {
                // Only include POS permissions (exclude blog permissions)
                $query->where('name', 'like', 'view_%')
                      ->orWhere('name', 'like', 'manage_%')
                      ->orWhere('name', 'like', 'process_%');
            })
            ->where('name', 'not like', '%_blog_%')
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
        $effectiveUserId = $currentUser->getEffectiveUserId();

        // Only master admin or super admin can create roles
        if (! $currentUser->isMasterAdmin() && ! $currentUser->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Only master admin can create roles',
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string|exists:permissions,name,guard_name,sanctum',
        ]);

        // Check if role name already exists for this user (scoped by user_id)
        $existingRole = Role::where('guard_name', 'sanctum')
            ->where('name', $validated['name'])
            ->where('user_id', $effectiveUserId)
            ->first();

        if ($existingRole) {
            return response()->json([
                'success' => false,
                'message' => 'Role name already exists',
            ], 422);
        }

        // Create role using direct database insertion
        // Bypass Spatie's Role::create() which enforces unique(name, guard_name) validation
        $roleId = \DB::table('roles')->insertGetId([
            'name' => $validated['name'],
            'guard_name' => 'sanctum',
            'user_id' => $effectiveUserId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = Role::find($roleId);

        // Add permissions if provided using direct database manipulation
        if (! empty($validated['permissions']) && is_array($validated['permissions'])) {
            foreach ($validated['permissions'] as $permissionName) {
                $permission = \Spatie\Permission\Models\Permission::where('name', $permissionName)
                    ->where('guard_name', 'sanctum')
                    ->first();

                if ($permission) {
                    \DB::table('role_has_permissions')->insert([
                        'permission_id' => $permission->id,
                        'role_id' => $role->id,
                    ]);
                }
            }
        }

        // Clear permission cache and reload
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role->refresh();

        // Clear conflicting 'permissions' JSON column and load relationship
        $this->clearPermissionsAttributeAndLoad($role);

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
                'permissions' => $role->permissions ? $role->permissions->pluck('name')->toArray() : [],
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
        $currentUser = $request->user();
        $effectiveUserId = $currentUser->getEffectiveUserId();

        // Get stores owned by the effective user (master admin)
        $storeIds = \App\Models\Store::where('user_id', $effectiveUserId)->pluck('id');

        // Check if this role is assigned to any POS user in these stores
        $roleAssignedToStore = \App\Models\PosUser::whereIn('store_id', $storeIds)
            ->where('role_id', $role->id)
            ->exists();

        if ($role->guard_name !== 'sanctum' || ! $roleAssignedToStore) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $this->clearPermissionsAttributeAndLoad($role);

        // Count users from model_has_roles table who are POS users in these stores
        $usersCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', 'App\\Models\\User')
            ->whereIn('model_id', function ($query) use ($storeIds) {
                $query->select('user_id')
                    ->from('pos_users')
                    ->whereIn('store_id', $storeIds);
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions ? $role->permissions->pluck('name')->toArray() : [],
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

        // Super admin can update any role
        if (! $currentUser->isSuperAdmin()) {
            // Only master admin can update roles (for their merchant)
            if (! $currentUser->isMasterAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only master admin can update roles',
                ], 403);
            }

            // Get stores owned by the effective user (master admin)
            $storeIds = \App\Models\Store::where('user_id', $currentUser->getEffectiveUserId())->pluck('id');

            // Check if this role is assigned to any POS user in these stores
            $roleAssignedToStore = \App\Models\PosUser::whereIn('store_id', $storeIds)
                ->where('role_id', $role->id)
                ->exists();

            if ($role->guard_name !== 'sanctum' || ! $roleAssignedToStore) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string|exists:permissions,name,guard_name,sanctum',
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
            // Remove all existing permissions using direct database deletion
            \DB::table('role_has_permissions')
                ->where('role_id', $role->id)
                ->delete();

            // Add new permissions if provided using direct database insertion
            if (! empty($validated['permissions']) && is_array($validated['permissions'])) {
                foreach ($validated['permissions'] as $permissionName) {
                    $permission = \Spatie\Permission\Models\Permission::where('name', $permissionName)
                        ->where('guard_name', 'sanctum')
                        ->first();

                    if ($permission) {
                        \DB::table('role_has_permissions')->insert([
                            'permission_id' => $permission->id,
                            'role_id' => $role->id,
                        ]);
                    }
                }
            }
        }

        // Clear permission cache and reload
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $role->refresh();

        // Clear conflicting 'permissions' JSON column and load relationship
        $this->clearPermissionsAttributeAndLoad($role);

        // Count users from model_has_roles table who are POS users in these stores
        if ($currentUser->isSuperAdmin()) {
            // Super admin: count all users with this role
            $usersCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->count();
        } else {
            // Master admin: count only users in their stores
            $storeIds = \App\Models\Store::where('user_id', $currentUser->getEffectiveUserId())->pluck('id');
            $usersCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', function ($query) use ($storeIds) {
                    $query->select('user_id')
                        ->from('pos_users')
                        ->whereIn('store_id', $storeIds);
                })
                ->count();
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions ? $role->permissions->pluck('name')->toArray() : [],
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

        // Super admin can delete any role
        if (! $currentUser->isSuperAdmin()) {
            // Only master admin can delete roles (for their merchant)
            if (! $currentUser->isMasterAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only master admin can delete roles',
                ], 403);
            }

            $effectiveUserId = $currentUser->getEffectiveUserId();

            // Check if this role belongs to the current user (via user_id)
            if ($role->user_id !== $effectiveUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role not found',
                ], 404);
            }
        }

        // Check guard name
        if ($role->guard_name !== 'sanctum') {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        // Check if role is assigned to any users in this tenant's stores via model_has_roles
        if ($currentUser->isSuperAdmin()) {
            $userCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->count();
        } else {
            $userCount = \DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->where('model_type', 'App\\Models\\User')
                ->whereIn('model_id', function ($query) use ($storeIds) {
                    $query->select('user_id')
                        ->from('pos_users')
                        ->whereIn('store_id', $storeIds);
                })
                ->count();
        }

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
