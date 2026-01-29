# Quick Test Guide - Role & Permission System

## ✅ Setup Complete

### Database Changes
- ✅ Migration: `is_master_admin` column added to users table (default: `true`)
- ✅ All existing users updated to `is_master_admin = true`
- ✅ 5 default roles created with permissions

### Code Changes
- ✅ PosUser model: Added `hasPermission()`, `hasAnyPermission()`, `hasAllPermissions()` methods
- ✅ User model: Added `isMasterAdmin()` method and `roles()` relationship
- ✅ PosUserController: New users automatically set as master admin

## Quick Test Commands

### 1. Check Users & Master Admin Status
```bash
php artisan tinker
```
```php
// Check all users
User::select('id', 'name', 'email', 'is_master_admin')->get()

// Get first user
$user = User::first()
$user->isMasterAdmin() // Should return true
```

### 2. Check Roles & Permissions
```php
// Get all roles
Role::with('user')->get()

// Get specific role with permissions
$cashierRole = Role::where('name', 'Kasir')->first()
$cashierRole->permissions // Array of permissions

// Get role by ID
$role = Role::find(2)
```

### 3. Create POS User & Test Permissions
```php
// Get or create test users
$masterAdmin = User::first()
$employee = User::create([
    'name' => 'John Kasir',
    'email' => 'john@test.com',
    'password' => Hash::make('password123'),
    'is_master_admin' => true
])

// Get store and role
$store = Store::first()
$cashierRole = Role::where('name', 'Kasir')->first()

// Create POS user
$posUser = PosUser::create([
    'user_id' => $employee->id,
    'store_id' => $store->id,
    'role_id' => $cashierRole->id,
    'is_active' => true
])

// Test permissions
$posUser->hasPermission('pos.create_order') // Should return true
$posUser->hasPermission('pos.view_reports') // Should return false (not in Kasir role)
$posUser->hasAnyPermission(['pos.create_order', 'pos.view_reports']) // true
$posUser->hasAllPermissions(['pos.create_order', 'pos.view_products']) // true
```

### 4. Update Role Permissions
```php
$role = Role::find(2) // Kasir role
$role->update([
    'permissions' => [
        'pos.create_order',
        'pos.view_products',
        'pos.process_payment',
        'pos.manage_tables' // Add new permission
    ]
])
```

## API Testing (via HTTP Client)

### Get POS Users List
```http
GET /api/pos/users
Authorization: Bearer {token}
```

Expected response includes role and permissions:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 2,
      "store_id": 1,
      "role_id": 2,
      "is_active": true,
      "user": { ... },
      "store": { ... },
      "role": {
        "id": 2,
        "name": "Kasir",
        "permissions": [
          "pos.create_order",
          "pos.view_products",
          "pos.process_payment"
        ]
      }
    }
  ]
}
```

### Create New POS User
```http
POST /api/pos/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "user_id": 5,
  "store_id": 1,
  "role_id": 2,
  "is_active": true
}
```

## Database Verification

### Check Users Table
```sql
SELECT id, name, email, is_master_admin FROM users;
```
Expected: All users have `is_master_admin = true`

### Check Roles Table
```sql
SELECT id, name, user_id, permissions FROM roles;
```
Expected: 5 roles created

### Check POS Users with Roles
```sql
SELECT 
    pu.id,
    u.name as user_name,
    s.name as store_name,
    r.name as role_name,
    r.permissions
FROM pos_users pu
JOIN users u ON pu.user_id = u.id
JOIN stores s ON pu.store_id = s.id
JOIN roles r ON pu.role_id = r.id;
```

## Default Roles Created

1. **Owner/Manager** - Full access (16 permissions)
2. **Kasir** - Orders & payments (5 permissions)
3. **Waiter** - Orders & tables (4 permissions)
4. **Kitchen Staff** - View only (2 permissions)
5. **Inventory Manager** - Products & inventory (6 permissions)

## Testing Checklist

- [ ] All users have `is_master_admin = true`
- [ ] 5 roles created with correct permissions
- [ ] Can create POS user with role
- [ ] `hasPermission()` method works correctly
- [ ] `hasAnyPermission()` method works correctly
- [ ] `hasAllPermissions()` method works correctly
- [ ] API endpoint returns role & permissions
- [ ] Can update role permissions

## Common Issues & Solutions

### Issue: User not master admin
**Solution:** Run migration refresh
```bash
php artisan migrate:refresh --path=database/migrations/2026_01_27_140612_add_is_master_admin_to_users_table.php
```

### Issue: No roles created
**Solution:** Run seeder
```bash
php artisan db:seed --class=RoleSeeder
```

### Issue: Permission check not working
**Solution:** Make sure POS user is loaded with role relationship
```php
$posUser = PosUser::with('role')->find($id);
```

## Next Steps

1. ✅ Add middleware untuk permission checking
2. ✅ Add policies untuk authorization
3. ✅ Add permission check di setiap sensitive endpoint
4. ✅ Write tests untuk permission system
5. ✅ Document available permissions

## See Also

- [ROLE_PERMISSION_SYSTEM.md](ROLE_PERMISSION_SYSTEM.md) - Full documentation
- [database/seeders/RoleSeeder.php](../database/seeders/RoleSeeder.php) - Default roles
- [app/Models/PosUser.php](../app/Models/PosUser.php) - Permission methods
