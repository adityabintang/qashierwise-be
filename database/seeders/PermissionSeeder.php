<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions for POS system with namespaced format
        $permissions = [
            // POS Product Management
            'pos.view_products' => 'View Products',
            'pos.manage_products' => 'Manage Products (Create/Edit/Delete)',

            // POS Category Management
            'pos.view_categories' => 'View Categories',
            'pos.manage_categories' => 'Manage Categories (Create/Edit/Delete)',

            // POS Order Management
            'pos.view_orders' => 'View Orders',
            'pos.create_order' => 'Create Orders',
            'pos.edit_order' => 'Edit Orders',
            'pos.manage_orders' => 'Manage Orders (Create/Edit/Cancel)',
            'pos.void_order' => 'Void Orders',

            // POS Payment Processing
            'pos.process_payment' => 'Process Payments',

            // POS Store Management
            'pos.view_stores' => 'View Stores',
            'pos.manage_stores' => 'Manage Stores (Create/Edit/Activate/Deactivate)',

            // POS Table Management
            'pos.view_tables' => 'View Tables',
            'pos.manage_tables' => 'Manage Tables (Create/Edit/Delete)',

            // POS User Management
            'pos.view_users' => 'View POS Users',
            'pos.manage_users' => 'Manage POS Users (Create/Edit/Delete/Activate/Deactivate)',

            // Role Management
            'pos.view_roles' => 'View Roles',
            'pos.manage_roles' => 'Manage Roles (Create/Edit/Delete)',

            // Reports & Analytics
            'pos.view_reports' => 'View Reports & Analytics',

            // Transaction History
            'pos.view_transactions' => 'View Transaction History',

            // Admin: User Management
            'admin.manage_users' => 'Manage All Users',

            // Subscription: Manage Subscriptions
            'subscription.manage' => 'Manage Subscriptions',
        ];

        foreach ($permissions as $permission => $description) {
            // Use firstOrCreate to avoid error if permission already exists
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'sanctum',
            ]);
        }

        $this->command->info('Permissions created successfully!');
    }
}
