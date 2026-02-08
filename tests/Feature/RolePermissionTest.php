<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Run permission seeder to ensure all permissions exist
        $this->seed(\Database\Seeders\PermissionSeeder::class);
    }

    /**
     * Test creating a new role with permissions.
     */
    public function test_can_create_role_with_permissions(): void
    {
        $role = Role::create([
            'name' => 'Test Role',
            'guard_name' => 'sanctum',
        ]);

        $permissions = Permission::whereIn('name', ['view_products', 'manage_products'])->get();
        $role->givePermissionTo($permissions);

        $this->assertDatabaseHas('roles', [
            'name' => 'Test Role',
            'guard_name' => 'sanctum',
        ]);

        $this->assertTrue($role->hasPermissionTo('view_products'));
        $this->assertTrue($role->hasPermissionTo('manage_products'));
    }

    /**
     * Test assigning role to user.
     */
    public function test_can_assign_role_to_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create([
            'name' => 'Cashier Test',
            'guard_name' => 'sanctum',
        ]);

        $role->givePermissionTo(['view_products', 'view_orders']);

        $user->assignRole($role);

        $this->assertTrue($user->hasRole('Cashier Test'));
        $this->assertTrue($user->hasPermissionTo('view_products', 'sanctum'));
        $this->assertTrue($user->hasPermissionTo('view_orders', 'sanctum'));
    }

    /**
     * Test syncing roles to user.
     */
    public function test_can_sync_roles_to_user(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'synctest@example.com',
            'password' => bcrypt('password'),
        ]);

        $role1 = Role::create(['name' => 'Role 1', 'guard_name' => 'sanctum']);
        $role2 = Role::create(['name' => 'Role 2', 'guard_name' => 'sanctum']);

        // Assign first role
        $user->assignRole($role1);
        $this->assertTrue($user->hasRole('Role 1'));

        // Sync to second role (should remove first)
        $user->syncRoles([$role2->name]);
        $this->assertFalse($user->hasRole('Role 1'));
        $this->assertTrue($user->hasRole('Role 2'));
    }

    /**
     * Test permission checking with sanctum guard.
     */
    public function test_permission_checking_with_sanctum_guard(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'guardtest@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'Manager Test', 'guard_name' => 'sanctum']);
        $role->givePermissionTo(['manage_products', 'manage_orders']);

        $user->assignRole($role);

        // Test with explicit guard
        $this->assertTrue($user->hasPermissionTo('manage_products', 'sanctum'));
        $this->assertTrue($user->hasPermissionTo('manage_orders', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('manage_users', 'sanctum'));
    }

    /**
     * Test role update with syncPermissions.
     */
    public function test_can_update_role_permissions(): void
    {
        $role = Role::create(['name' => 'Update Test', 'guard_name' => 'sanctum']);

        // Initial permissions
        $role->givePermissionTo(['view_products', 'view_orders']);
        $this->assertTrue($role->hasPermissionTo('view_products'));
        $this->assertTrue($role->hasPermissionTo('view_orders'));

        // Update permissions using sync
        $role->syncPermissions(['manage_products', 'manage_orders']);
        $this->assertFalse($role->hasPermissionTo('view_products'));
        $this->assertFalse($role->hasPermissionTo('view_orders'));
        $this->assertTrue($role->hasPermissionTo('manage_products'));
        $this->assertTrue($role->hasPermissionTo('manage_orders'));
    }

    /**
     * Test getting all user permissions.
     */
    public function test_can_get_all_user_permissions(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'allperms@example.com',
            'password' => bcrypt('password'),
        ]);

        $role1 = Role::create(['name' => 'Role A', 'guard_name' => 'sanctum']);
        $role1->givePermissionTo(['view_products', 'view_orders']);

        $role2 = Role::create(['name' => 'Role B', 'guard_name' => 'sanctum']);
        $role2->givePermissionTo(['manage_categories', 'view_reports']);

        $user->assignRole([$role1, $role2]);

        $permissions = $user->getAllPermissions()->pluck('name')->toArray();

        $this->assertContains('view_products', $permissions);
        $this->assertContains('view_orders', $permissions);
        $this->assertContains('manage_categories', $permissions);
        $this->assertContains('view_reports', $permissions);
        $this->assertCount(4, $permissions);
    }

    /**
     * Test role deletion.
     */
    public function test_can_delete_role(): void
    {
        $role = Role::create(['name' => 'Delete Test', 'guard_name' => 'sanctum']);
        $role->givePermissionTo(['view_products']);

        $roleId = $role->id;

        $role->delete();

        $this->assertDatabaseMissing('roles', ['id' => $roleId]);
    }

    /**
     * Test hasAnyPermission method.
     */
    public function test_has_any_permission(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'anyperms@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'Any Test', 'guard_name' => 'sanctum']);
        $role->givePermissionTo(['view_products']);
        $user->assignRole($role);

        $this->assertTrue($user->hasAnyPermission(['view_products', 'manage_products'], 'sanctum'));
        $this->assertFalse($user->hasAnyPermission(['manage_products', 'manage_orders'], 'sanctum'));
    }

    /**
     * Test hasAllPermissions method.
     */
    public function test_has_all_permissions(): void
    {
        $user = User::create([
            'name' => 'Test User',
            'email' => 'allpermscheck@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::create(['name' => 'All Test', 'guard_name' => 'sanctum']);
        $role->givePermissionTo(['view_products', 'manage_products']);
        $user->assignRole($role);

        $this->assertTrue($user->hasAllPermissions(['view_products', 'manage_products'], 'sanctum'));
        $this->assertFalse($user->hasAllPermissions(['view_products', 'manage_orders'], 'sanctum'));
    }
}
