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

        // Create all permissions for POS system
        $permissions = [
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
