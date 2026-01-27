<?php

namespace App\Http\Controllers\Api\Pos;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * List of available permissions for POS system
     * These permissions control access to POS endpoints
     */
    protected array $availablePermissions = [
        // Product Management
        'view_products' => 'View Products',
        'manage_products' => 'Manage Products (Create/Edit/Delete)',

        // Category Management
        'view_categories' => 'View Categories',
        'manage_categories' => 'Manage Categories (Create/Edit/Delete)',

        // Order Management
        'view_orders' => 'View Orders',
        'manage_orders' => 'Manage Orders (Create/Edit/Cancel)',

        // Payment Processing
        'process_payment' => 'Process Payments',

        // Store Management
        'view_stores' => 'View Stores',
        'manage_stores' => 'Manage Stores (Create/Edit/Activate/Deactivate)',

        // Table Management
        'view_tables' => 'View Tables',
        'manage_tables' => 'Manage Tables (Create/Edit/Delete)',

        // User Management
        'view_users' => 'View POS Users',
        'manage_users' => 'Manage POS Users (Create/Edit/Delete/Activate/Deactivate)',

        // Role Management
        'view_roles' => 'View Roles',
        'manage_roles' => 'Manage Roles (Create/Edit/Delete)',

        // Reports & Analytics
        'view_reports' => 'View Reports & Analytics',

        // Transaction History
        'view_transactions' => 'View Transaction History',
    ];

    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        $roles = Role::withCount('posUsers')
            ->orderBy('name', 'asc')
            ->get();

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
        return response()->json([
            'success' => true,
            'data' => $this->availablePermissions,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string',
        ]);

        // Validate that permissions exist in available list
        $invalidPermissions = array_diff($validated['permissions'] ?? [], array_keys($this->availablePermissions));
        if (!empty($invalidPermissions)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid permissions: ' . implode(', ', $invalidPermissions),
            ], 422);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'permissions' => $validated['permissions'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role->loadCount('posUsers'),
        ], 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $role->loadCount('posUsers'),
        ]);
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:roles,name,'.$role->id,
            'permissions' => 'nullable|array',
            'permissions.*' => 'required|string',
        ]);

        // Validate that permissions exist in available list
        if (isset($validated['permissions'])) {
            $invalidPermissions = array_diff($validated['permissions'], array_keys($this->availablePermissions));
            if (!empty($invalidPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid permissions: ' . implode(', ', $invalidPermissions),
                ], 422);
            }
        }

        if (isset($validated['name'])) {
            $role->name = $validated['name'];
        }
        if (isset($validated['permissions'])) {
            $role->permissions = $validated['permissions'];
        }

        $role->save();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role->fresh()->loadCount('posUsers'),
        ]);
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Role $role): JsonResponse
    {
        $posUserCount = $role->posUsers()->count();

        if ($posUserCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete role. It is assigned to '.$posUserCount.' user(s).',
            ], 400);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }
}
