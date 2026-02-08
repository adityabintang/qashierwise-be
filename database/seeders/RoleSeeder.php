<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Define default roles with their permissions
        $rolesWithPermissions = [
            'Cashier' => [
                'view_products',
                'view_categories',
                'view_orders',
                'manage_orders',
                'process_payment',
                'view_transactions',
            ],
            'Manager' => [
                'view_products',
                'manage_products',
                'view_categories',
                'manage_categories',
                'view_orders',
                'manage_orders',
                'process_payment',
                'view_users',
                'manage_users',
                'view_roles',
                'view_reports',
                'view_transactions',
                'view_tables',
                'manage_tables',
            ],
            'Owner' => [
                'view_products',
                'manage_products',
                'view_categories',
                'manage_categories',
                'view_orders',
                'manage_orders',
                'process_payment',
                'view_stores',
                'manage_stores',
                'view_users',
                'manage_users',
                'view_roles',
                'manage_roles',
                'view_reports',
                'view_transactions',
                'view_tables',
                'manage_tables',
            ],
            'Kasir' => [
                'view_products',
                'view_categories',
                'view_orders',
                'manage_orders',
                'process_payment',
                'view_transactions',
            ],
            'Waiter' => [
                'view_products',
                'view_categories',
                'view_orders',
                'manage_orders',
                'view_tables',
            ],
            'Kitchen Staff' => [
                'view_orders',
                'view_products',
            ],
            'Inventory Manager' => [
                'view_products',
                'manage_products',
                'view_categories',
                'manage_categories',
                'view_reports',
            ],
        ];

        foreach ($rolesWithPermissions as $roleName => $permissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum'],
                []
            );

            // Sync permissions to role (this will update existing roles)
            $role->syncPermissions($permissions);

            $this->command->info("Role '{$roleName}' created/updated with ".count($permissions).' permissions.');
        }

        $this->command->info('All roles created successfully!');
    }
}
