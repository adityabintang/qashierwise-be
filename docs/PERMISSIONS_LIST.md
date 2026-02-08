# Complete Permission List

## Permission Categories

### POS (Point of Sale) Permissions

#### Orders
- `pos.create_order` - Buat order baru
- `pos.view_orders` - Lihat daftar orders
- `pos.edit_order` - Edit order existing
- `pos.manage_orders` - Full management orders (add items, remove items, discount, cancel)
- `pos.void_order` - Void/batalkan order

#### Products
- `pos.view_products` - Lihat daftar produk
- `pos.edit_products` - Edit produk
- `pos.manage_products` - Create/Edit/Delete produk
- `pos.delete_products` - Delete produk

#### Categories
- `pos.view_categories` - Lihat kategori produk
- `pos.manage_categories` - Create/Edit/Delete kategori

#### Payments
- `pos.process_payment` - Proses pembayaran
- `pos.give_discount` - Kasih diskon
- `pos.refund_payment` - Refund pembayaran

#### Inventory
- `pos.view_inventory` - Lihat stock inventory
- `pos.adjust_inventory` - Adjust/ubah stock

#### Tables (Restaurant)
- `pos.view_tables` - Lihat daftar meja
- `pos.manage_tables` - Manage meja (create/edit/delete, set available/occupied)

#### Reports
- `pos.view_reports` - Lihat laporan
- `pos.export_reports` - Export laporan ke file

#### Staff Management
- `pos.manage_staff` - Legacy permission for staff
- `pos.view_pos_users` - Lihat daftar POS users/staff
- `pos.manage_pos_users` - Create/Edit/Delete/Activate/Deactivate POS users

#### Stores
- `pos.view_stores` - Lihat daftar toko
- `pos.manage_stores` - Create/Edit/Activate/Deactivate toko

#### Roles
- `pos.view_roles` - Lihat daftar roles
- `pos.manage_roles` - Create/Edit/Delete roles & permissions

### Dashboard Permission
- `dashboard.view` - Akses dashboard (charts, summary, analytics)

### Contacts Module
- `contacts.view` - Lihat daftar contacts/customers
- `contacts.manage` - Create/Edit/Delete contacts

### Templates Module (WhatsApp/Message Templates)
- `templates.view` - Lihat daftar templates
- `templates.manage` - Create/Edit/Delete templates

### Reservations Module
- `reservations.view` - Lihat daftar reservasi
- `reservations.manage` - Create/Edit/Cancel reservasi

### Business Profile
- `business_profile.view` - Lihat business profile
- `business_profile.manage` - Edit business profile

### WhatsApp Module
- `whatsapp.view` - Lihat WhatsApp accounts & messages
- `whatsapp.manage` - Connect/disconnect WhatsApp, send messages

### AI Agent Module
- `ai_agent.view` - Lihat AI agent settings & conversations
- `ai_agent.manage` - Configure AI agent, manage conversations

## Default Role Permissions

### Owner/Manager (40 permissions)
Full access to all modules:
- All POS permissions
- All dashboard permissions
- All contacts permissions
- All templates permissions
- All reservations permissions
- All business profile permissions
- All WhatsApp permissions
- All AI agent permissions

### Kasir (7 permissions)
```json
[
  "pos.create_order",
  "pos.view_orders",
  "pos.manage_orders",
  "pos.view_products",
  "pos.process_payment",
  "pos.view_inventory",
  "dashboard.view"
]
```

### Waiter (9 permissions)
```json
[
  "pos.create_order",
  "pos.view_orders",
  "pos.manage_orders",
  "pos.view_products",
  "pos.view_tables",
  "pos.manage_tables",
  "reservations.view",
  "reservations.manage",
  "dashboard.view"
]
```

### Kitchen Staff (3 permissions)
```json
[
  "pos.view_orders",
  "pos.view_products",
  "dashboard.view"
]
```

### Inventory Manager (10 permissions)
```json
[
  "pos.view_products",
  "pos.edit_products",
  "pos.manage_products",
  "pos.view_categories",
  "pos.manage_categories",
  "pos.view_inventory",
  "pos.adjust_inventory",
  "pos.view_reports",
  "pos.export_reports",
  "dashboard.view"
]
```

## Permission Checking

### In Backend (Laravel Middleware)
```php
// Route with permission check
Route::get('/products', [ProductController::class, 'index'])
    ->middleware('pos.permission:view_products');
```

### In Frontend (React/Vue)
```javascript
// Check if user has permission
const hasPermission = (permission) => {
  const user = getCurrentUser();
  if (user.is_master_admin) return true;
  
  const posUser = user.pos_users?.[0]; // Get first POS user
  if (!posUser || !posUser.role) return false;
  
  return posUser.role.permissions.includes(permission);
};

// Usage
if (hasPermission('pos.manage_products')) {
  // Show edit/delete buttons
}
```

## Adding New Permissions

### 1. Update Role Permissions
```php
$role = Role::find($roleId);
$role->update([
    'permissions' => array_merge($role->permissions, [
        'new_module.new_permission'
    ])
]);
```

### 2. Add Middleware to Routes
```php
Route::get('/new-route', [Controller::class, 'method'])
    ->middleware('pos.permission:new_module.new_permission');
```

### 3. Update Frontend Permission Check
```javascript
// Check permission in component
if (hasPermission('new_module.new_permission')) {
  // Show feature
}
```

## Permission Naming Convention

Format: `{module}.{action}_{resource}` or `{module}.{action}`

Examples:
- `pos.view_products` - View action on products resource
- `pos.manage_orders` - Manage action (CRUD) on orders
- `dashboard.view` - View dashboard (no specific resource)
- `contacts.manage` - Manage contacts

Best practices:
- Use lowercase with underscores
- Group by module prefix (pos, dashboard, contacts, etc)
- Use descriptive action verbs (view, manage, create, edit, delete)
- `manage` usually means full CRUD access
- `view` means read-only access
