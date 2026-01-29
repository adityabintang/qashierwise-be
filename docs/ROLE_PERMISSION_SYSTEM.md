# Role & Permission System

## Overview

Aplikasi menggunakan system Role-Based Access Control (RBAC) dengan dua level permission:
1. **Master Admin Level** - User level (table `users`)
2. **POS User Level** - Store level (table `pos_users`)

## Database Schema

### Users Table
- `id` - Primary key
- `name` - Nama user
- `email` - Email user (unique)
- `password` - Hashed password
- `is_master_admin` - Boolean (default: `true`)
  - **ALL users are master admins by default**
  - Master admin memiliki full access ke semua store miliknya
  - Bisa create, update, delete stores, products, categories, dll

### Roles Table
- `id` - Primary key
- `user_id` - Foreign key ke `users` (owner/creator role)
- `name` - Nama role (contoh: "Kasir", "Manager", "Waiter")
- `permissions` - JSON array berisi list permissions
- Format permissions: `["pos.create_order", "pos.view_products", "pos.manage_tables"]`

### POS Users Table
- `id` - Primary key
- `user_id` - Foreign key ke `users`
- `store_id` - Foreign key ke `stores`
- `role_id` - Foreign key ke `roles`
- `is_active` - Boolean status

## Permission Structure

### Permission Format
Permissions disimpan sebagai JSON array di `roles.permissions`:
```json
[
  "pos.create_order",
  "pos.view_products",
  "pos.manage_tables",
  "pos.view_reports",
  "pos.manage_staff",
  "pos.edit_prices"
]
```

### Common Permissions
- `pos.create_order` - Buat order baru
- `pos.view_products` - Lihat daftar produk
- `pos.edit_products` - Edit produk
- `pos.manage_categories` - Manage kategori
- `pos.view_reports` - Lihat laporan
- `pos.manage_staff` - Manage staff/karyawan
- `pos.manage_tables` - Manage meja (untuk restaurant)
- `pos.process_payment` - Proses pembayaran
- `pos.void_order` - Void/batalkan order
- `pos.give_discount` - Kasih diskon

## How to Set Permissions

### 1. Create Role dengan Permissions
```php
use App\Models\Role;
use App\Models\User;

$user = User::find(1); // Master admin

// Create role untuk kasir
$cashierRole = Role::create([
    'user_id' => $user->id,
    'name' => 'Kasir',
    'permissions' => [
        'pos.create_order',
        'pos.view_products',
        'pos.process_payment'
    ]
]);

// Create role untuk manager
$managerRole = Role::create([
    'user_id' => $user->id,
    'name' => 'Manager',
    'permissions' => [
        'pos.create_order',
        'pos.view_products',
        'pos.edit_products',
        'pos.manage_categories',
        'pos.view_reports',
        'pos.manage_staff',
        'pos.give_discount',
        'pos.void_order'
    ]
]);
```

### 2. Assign Role ke POS User
```php
use App\Models\PosUser;

PosUser::create([
    'user_id' => $employee->id,
    'store_id' => $store->id,
    'role_id' => $cashierRole->id,
    'is_active' => true
]);
```

### 3. Update Permissions untuk Existing Role
```php
$role = Role::find(1);
$role->update([
    'permissions' => [
        'pos.create_order',
        'pos.view_products',
        'pos.process_payment',
        'pos.manage_tables' // Add new permission
    ]
]);
```

## Checking Permissions in Code

### Method 1: Manual Check
```php
// In Controller or Policy
$posUser = PosUser::with('role')->find($posUserId);

if (in_array('pos.create_order', $posUser->role->permissions)) {
    // User has permission
}
```

### Method 2: Helper Method (Recommended)
Add to `PosUser` model:
```php
public function hasPermission(string $permission): bool
{
    if (!$this->role) {
        return false;
    }
    
    return in_array($permission, $this->role->permissions ?? []);
}
```

Usage:
```php
if ($posUser->hasPermission('pos.create_order')) {
    // Allow action
}
```

### Method 3: Middleware (Best Practice)
Create middleware `CheckPosPermission`:
```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CheckPosPermission
{
    public function handle(Request $request, Closure $next, string $permission)
    {
        $posUser = $request->user()->posUser;
        
        if (!$posUser || !$posUser->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action'
            ], 403);
        }
        
        return $next($request);
    }
}
```

Register in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'pos.permission' => \App\Http\Middleware\CheckPosPermission::class,
    ]);
})
```

Usage in routes:
```php
Route::middleware(['auth:sanctum', 'pos.permission:pos.create_order'])
    ->post('/api/pos/orders', [OrderController::class, 'store']);
```

## Master Admin vs POS User

### Master Admin (`users.is_master_admin = true`)
- **Full access** ke semua fitur
- Bisa create/update/delete stores
- Bisa create/manage roles
- Bisa assign roles ke POS users
- Tidak perlu check permissions

### POS User (staff/karyawan)
- Limited access berdasarkan role
- Hanya bisa akses store yang di-assign
- Permissions di-check sebelum action

## API Endpoints

### Get All Roles (Master Admin Only)
```
GET /api/pos/roles
```

### Create Role
```
POST /api/pos/roles
{
  "name": "Kasir",
  "permissions": [
    "pos.create_order",
    "pos.view_products"
  ]
}
```

### Update Role Permissions
```
PUT /api/pos/roles/{id}
{
  "permissions": [
    "pos.create_order",
    "pos.view_products",
    "pos.manage_tables"
  ]
}
```

### Assign Role to POS User
```
POST /api/pos/users
{
  "user_id": 5,
  "store_id": 1,
  "role_id": 2,
  "is_active": true
}
```

## Example Seeder

Create `database/seeders/RoleSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $masterAdmin = User::first(); // Ambil master admin

        // Role: Owner/Manager
        Role::create([
            'user_id' => $masterAdmin->id,
            'name' => 'Owner/Manager',
            'permissions' => [
                'pos.create_order',
                'pos.view_products',
                'pos.edit_products',
                'pos.manage_categories',
                'pos.view_reports',
                'pos.manage_staff',
                'pos.manage_tables',
                'pos.process_payment',
                'pos.give_discount',
                'pos.void_order',
            ],
        ]);

        // Role: Kasir
        Role::create([
            'user_id' => $masterAdmin->id,
            'name' => 'Kasir',
            'permissions' => [
                'pos.create_order',
                'pos.view_products',
                'pos.process_payment',
            ],
        ]);

        // Role: Waiter
        Role::create([
            'user_id' => $masterAdmin->id,
            'name' => 'Waiter',
            'permissions' => [
                'pos.create_order',
                'pos.view_products',
                'pos.manage_tables',
            ],
        ]);
    }
}
```

Run seeder:
```bash
php artisan db:seed --class=RoleSeeder
```

## Testing

### Test Permission Check
```php
use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\PosUser;

class PermissionTest extends TestCase
{
    public function test_cashier_can_create_order(): void
    {
        $user = User::factory()->create();
        $role = Role::factory()->create([
            'permissions' => ['pos.create_order']
        ]);
        $posUser = PosUser::factory()->create([
            'user_id' => $user->id,
            'role_id' => $role->id
        ]);

        $this->assertTrue($posUser->hasPermission('pos.create_order'));
        $this->assertFalse($posUser->hasPermission('pos.view_reports'));
    }
}
```

## Best Practices

1. **Always check is_master_admin first**
   - Master admin bypass all permission checks
   
2. **Use descriptive permission names**
   - Format: `{module}.{action}_{resource}`
   - Example: `pos.create_order`, `pos.view_reports`

3. **Group related permissions**
   - Use consistent prefixes (pos, admin, inventory)

4. **Document all available permissions**
   - Keep list updated in this doc

5. **Use middleware for routes**
   - Centralized permission checking
   - Cleaner controller code

6. **Test permissions thoroughly**
   - Write tests for each permission
   - Test both allowed and denied scenarios

## Migration History

- `2026_01_27_140612_add_is_master_admin_to_users_table.php`
  - Added `is_master_admin` column to users
  - Default value: `true`
  - All existing users set as master admin

## Notes

- Semua user di table `users` adalah master admin by default
- Master admin tidak perlu role atau permissions untuk akses full
- Role & permissions hanya untuk POS users (staff/karyawan level store)
- Permissions stored as JSON array untuk flexibility
- Bisa add custom permissions sesuai kebutuhan bisnis
