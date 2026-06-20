<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AuthorRoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'view_blog_post',
            'create_blog_post',
            'update_blog_post',
            'delete_blog_post',
            'view_blog_category',
            'create_blog_category',
            'update_blog_category',
            'delete_blog_category',
            'view_blog_tag',
            'create_blog_tag',
            'update_blog_tag',
            'delete_blog_tag',
        ];

        // Create permissions
        $permissionIds = [];
        foreach ($permissions as $permission) {
            $existing = DB::table('permissions')
                ->where('name', $permission)
                ->where('guard_name', 'sanctum')
                ->first();

            if ($existing) {
                $permissionIds[] = $existing->id;
            } else {
                $id = DB::table('permissions')->insertGetId([
                    'name' => $permission,
                    'guard_name' => 'sanctum',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $permissionIds[] = $id;
            }
        }

        // Create role
        $role = DB::table('roles')
            ->where('name', 'author')
            ->where('guard_name', 'sanctum')
            ->first();

        if (!$role) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'author',
                'guard_name' => 'sanctum',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = $role->id;
        }

        // Assign permissions to role
        foreach ($permissionIds as $permissionId) {
            $existing = DB::table('role_has_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();

            if (!$existing) {
                DB::table('role_has_permissions')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        $this->command->info('Author role created successfully!');
    }
}
