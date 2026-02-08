<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class UpdateRolePermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Update Owner/Manager role
        $owner = Role::where('name', 'Owner/Manager')->first();
        if ($owner) {
            $owner->update([
                'permissions' => [
                    // POS Permissions
                    'pos.create_order',
                    'pos.view_orders',
                    'pos.edit_order',
                    'pos.manage_orders',
                    'pos.void_order',
                    'pos.view_products',
                    'pos.edit_products',
                    'pos.manage_products',
                    'pos.delete_products',
                    'pos.view_categories',
                    'pos.manage_categories',
                    'pos.view_reports',
                    'pos.export_reports',
                    'pos.manage_staff',
                    'pos.view_users',
                    'pos.manage_users',
                    'pos.view_tables',
                    'pos.manage_tables',
                    'pos.process_payment',
                    'pos.give_discount',
                    'pos.refund_payment',
                    'pos.view_inventory',
                    'pos.adjust_inventory',
                    'pos.view_stores',
                    'pos.manage_stores',
                    'pos.view_roles',
                    'pos.manage_roles',
                    // Other Modules
                    'dashboard.view',
                    'contacts.view',
                    'contacts.manage',
                    'templates.view',
                    'templates.manage',
                    'reservations.view',
                    'reservations.manage',
                    'business_profile.view',
                    'business_profile.manage',
                    'whatsapp.view',
                    'whatsapp.manage',
                    'ai_agent.view',
                    'ai_agent.manage',
                ],
            ]);
            $this->command->info('✅ Updated Owner/Manager role - '.count($owner->permissions).' permissions');
        }

        // Update Kasir role
        $kasir = Role::where('name', 'Kasir')->first();
        if ($kasir) {
            $kasir->update([
                'permissions' => [
                    'pos.create_order',
                    'pos.view_orders',
                    'pos.manage_orders',
                    'pos.view_products',
                    'pos.process_payment',
                    'pos.view_inventory',
                    'dashboard.view',
                ],
            ]);
            $this->command->info('✅ Updated Kasir role - '.count($kasir->permissions).' permissions');
        }

        // Update Waiter role
        $waiter = Role::where('name', 'Waiter')->first();
        if ($waiter) {
            $waiter->update([
                'permissions' => [
                    'pos.create_order',
                    'pos.view_orders',
                    'pos.manage_orders',
                    'pos.view_products',
                    'pos.view_tables',
                    'pos.manage_tables',
                    'reservations.view',
                    'reservations.manage',
                    'dashboard.view',
                ],
            ]);
            $this->command->info('✅ Updated Waiter role - '.count($waiter->permissions).' permissions');
        }

        // Update Kitchen Staff role
        $kitchen = Role::where('name', 'Kitchen Staff')->first();
        if ($kitchen) {
            $kitchen->update([
                'permissions' => [
                    'pos.view_orders',
                    'pos.view_products',
                    'dashboard.view',
                ],
            ]);
            $this->command->info('✅ Updated Kitchen Staff role - '.count($kitchen->permissions).' permissions');
        }

        // Update Inventory Manager role
        $inventory = Role::where('name', 'Inventory Manager')->first();
        if ($inventory) {
            $inventory->update([
                'permissions' => [
                    'pos.view_products',
                    'pos.edit_products',
                    'pos.manage_products',
                    'pos.view_categories',
                    'pos.manage_categories',
                    'pos.view_inventory',
                    'pos.adjust_inventory',
                    'pos.view_reports',
                    'pos.export_reports',
                    'dashboard.view',
                ],
            ]);
            $this->command->info('✅ Updated Inventory Manager role - '.count($inventory->permissions).' permissions');
        }
    }
}
