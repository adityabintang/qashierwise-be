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
                'pos.view_products',
                'pos.view_categories',
                'pos.view_orders',
                'pos.manage_orders',
                'pos.process_payment',
                'pos.view_transactions',
            ],
            'Manager' => [
                'pos.view_products',
                'pos.manage_products',
                'pos.view_categories',
                'pos.manage_categories',
                'pos.view_orders',
                'pos.manage_orders',
                'pos.process_payment',
                'pos.view_users',
                'pos.manage_users',
                'pos.view_roles',
                'pos.view_reports',
                'pos.view_transactions',
                'pos.view_tables',
                'pos.manage_tables',
            ],
            'Owner' => [
                'pos.view_products',
                'pos.manage_products',
                'pos.view_categories',
                'pos.manage_categories',
                'pos.view_orders',
                'pos.manage_orders',
                'pos.process_payment',
                'pos.view_stores',
                'pos.manage_stores',
                'pos.view_users',
                'pos.manage_users',
                'pos.view_roles',
                'pos.manage_roles',
                'pos.view_reports',
                'pos.view_transactions',
                'pos.view_tables',
                'pos.manage_tables',
            ],
            'Kasir' => [
                'pos.view_products',
                'pos.view_categories',
                'pos.view_orders',
                'pos.manage_orders',
                'pos.process_payment',
                'pos.view_transactions',
            ],
            'Waiter' => [
                'pos.view_products',
                'pos.view_categories',
                'pos.view_orders',
                'pos.manage_orders',
                'pos.view_tables',
            ],
            'Kitchen Staff' => [
                'pos.view_orders',
                'pos.view_products',
            ],
            'Inventory Manager' => [
                'pos.view_products',
                'pos.manage_products',
                'pos.view_categories',
                'pos.manage_categories',
                'pos.view_reports',
            ],
            'Content Manager' => [
                'blog.view_posts',
                'blog.manage_posts',
                'blog.view_categories',
                'blog.manage_categories',
                'blog.view_tags',
                'blog.manage_tags',
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
