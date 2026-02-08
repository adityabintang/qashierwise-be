# Sub-Account Data Inheritance System

## Overview

Sistem sub-account dirancang agar karyawan (kasir, waiter, kitchen staff, dll) yang ditambahkan oleh master admin dapat mengakses dan mengoperasikan **semua data master admin**, termasuk:

- **Stores** (Toko)
- **Products** (Produk)
- **Orders** (Pesanan)
- **Tables** (Meja)
- **Categories** (Kategori)
- **WhatsApp Account** (Akun WhatsApp Business)
- **AI Agent** (Konfigurasi AI Agent)
- **Reservations** (Reservasi)
- **Contacts** (Kontak WhatsApp)
- **Templates** (Template Pesan)
- **Business Profile** (Profil Bisnis)
- **Transactions** (Transaksi)
- **Reports** (Laporan)
- **Subscription** (Status Langganan)

Sub-account **TIDAK** diperlakukan sebagai entitas terpisah, melainkan sebagai "perpanjangan tangan" dari master admin dengan batasan permission.

## Konsep Inti

### 1. Master Admin vs Sub-Account

- **Master Admin**: User yang mendaftar sendiri, memiliki `is_master_admin = true`, memiliki subscription, membuat toko, produk, dll.
- **Sub-Account**: User yang dibuat oleh master admin melalui menu "Users/Staff", memiliki `is_master_admin = false`, **tidak memiliki subscription sendiri**.

### 2. Data Inheritance

Sub-account **mewarisi** semua data dari master admin:
- Menggunakan `getEffectiveUserId()` yang mengembalikan ID master admin
- Subscription status menggunakan `getEffectiveSubscription()` dari master admin
- Semua query database menggunakan effective user ID, bukan ID sub-account

### 3. Permission Control

Meskipun sub-account bisa mengakses semua data master admin, akses mereka dibatasi oleh **role permission**:
- Kasir: Bisa create order, view products, tapi tidak bisa edit products atau manage users
- Waiter: Bisa manage tables, view orders
- Kitchen Staff: Hanya bisa view orders untuk diproses
- Inventory Manager: Bisa manage products, categories, tapi tidak bisa create orders

## Implementasi Teknis

### Model User

```php
// Check if user is master admin
public function isMasterAdmin(): bool
{
    return $this->is_master_admin === true;
}

// Get master admin for sub-account (returns self if already master admin)
public function getMasterAdmin(): ?User
{
    if ($this->isMasterAdmin()) {
        return $this;
    }
    
    $posUser = $this->posUsers()->with('store')->first();
    return $posUser && $posUser->store 
        ? User::find($posUser->store->user_id) 
        : null;
}

// Get effective user ID (master admin's ID for sub-accounts)
public function getEffectiveUserId(): int
{
    return $this->isMasterAdmin() 
        ? $this->id 
        : ($this->getMasterAdmin()?->id ?? $this->id);
}

// Get effective subscription (master admin's subscription)
public function getEffectiveSubscription()
{
    return $this->isMasterAdmin() 
        ? $this->subscription 
        : $this->getMasterAdmin()?->subscription;
}
```

### Controller Usage

**❌ WRONG (Old Way):**
```php
public function index()
{
    $userId = auth()->id(); // Returns sub-account's ID
    $stores = Store::where('user_id', $userId)->get(); // Empty!
}
```

**✅ CORRECT (New Way):**
```php
public function index()
{
    $userId = auth()->user()->getEffectiveUserId(); // Returns master admin's ID
    $stores = Store::where('user_id', $userId)->get(); // Returns all stores!
}
```

### Controllers Updated

Semua controller berikut telah di-update untuk menggunakan `getEffectiveUserId()`:

**POS Controllers:**
- `ProductController` - Product management
- `CategoryController` - Category management
- `StoreController` - Store management
- `OrderController` - Order management
- `TableController` - Table management
- `TransactionController` - Transaction history
- `ReportController` - Reports & analytics
- `PosUserController` - Staff management

**Main Controllers:**
- `WhatsAppController` - WhatsApp messaging & contacts
- `EmbeddedSignupController` - WhatsApp account connection
- `AiAgentController` - AI Agent configuration & testing
- `ReservationController` - Reservation management
- `ReservationFlowConfigController` - WhatsApp Flow configuration

## Permission System

### Master Admin Bypass

Master admin **bypass** semua permission checks:

```php
// CheckPosPermission Middleware
if ($user->isMasterAdmin()) {
    return $next($request); // Skip permission check
}
```

### Sub-Account Permission Check

Sub-account harus memiliki permission yang sesuai:

```php
// Check single permission
if (!$posUser->hasPermission('pos.create_order')) {
    return response()->json(['message' => 'Unauthorized'], 403);
}
```

### Available Permissions

**POS Module (27 permissions):**
- Orders: `create_order`, `view_orders`, `edit_order`, `delete_order`, `cancel_order`
- Products: `create_product`, `view_products`, `edit_product`, `delete_product`
- Categories: `create_category`, `view_categories`, `edit_category`, `delete_category`
- Payments: `process_payment`, `refund_payment`
- Tables: `create_table`, `view_tables`, `edit_table`, `delete_table`
- Stores: `create_store`, `view_stores`, `edit_store`, `delete_store`
- Users: `create_user`, `view_users`, `edit_user`, `delete_user`
- Roles: `create_role`, `view_roles`, `edit_role`

**Other Modules (14 permissions):**
- Dashboard: `view_dashboard`
- Contacts: `view_contacts`, `create_contact`, `edit_contact`, `delete_contact`
- Templates: `view_templates`, `create_template`, `edit_template`, `delete_template`
- Reservations: `view_reservations`, `manage_reservations`
- Business Profile: `view_business_profile`, `edit_business_profile`
- WhatsApp: `manage_whatsapp`
- AI Agent: `manage_ai_agent`

## Example Scenarios

### Scenario 1: Kasir Login

1. Kasir login dengan email `kasir1@gmail.com`
2. Backend memanggil `auth()->user()->getEffectiveUserId()` → Returns `1` (master admin ID)
3. Query products: `Product::where('user_id', 1)->get()` → Returns all master admin's products
4. Kasir bisa create order karena role "Kasir" punya permission `pos.create_order`
5. Subscription check: `$user->getEffectiveSubscription()` → Returns master admin's "pro" plan

### Scenario 2: Kitchen Staff Login

1. Kitchen staff login dengan email `kitchen@example.com`
2. Backend memanggil `auth()->user()->getEffectiveUserId()` → Returns `1` (master admin ID)
3. Query orders: `Order::whereHas('store', fn($q) => $q->where('user_id', 1))->get()` → All orders
4. Coba edit product → **BLOCKED** (role "Kitchen Staff" tidak punya `pos.edit_product`)
5. View orders for cooking → **ALLOWED** (role punya `pos.view_orders`)

### Scenario 3: Master Admin dengan WhatsApp & AI Agent

1. Master admin connect WhatsApp account → `WhatsAppAccount::create(['user_id' => 1, ...])`
2. Master admin configure AI Agent → `AiAgent::create(['whatsapp_account_id' => ..., ...])`
3. Kasir login → `getEffectiveUserId()` returns `1`
4. Kasir buka menu AI Agent:
   - Query: `WhatsAppAccount::where('user_id', 1)->first()` → **FOUND**
   - Query: `AiAgent::where('whatsapp_account_id', ...)->first()` → **FOUND**
5. Jika role kasir punya `manage_ai_agent` permission → Bisa toggle AI Agent on/off
6. Jika role kasir **TIDAK** punya permission → View only (disabled buttons)

## Testing

### Test Sub-Account Methods

```bash
php artisan db:seed --class=TestSubAccountSeeder
```

Output:
```
Testing Sub-Account Methods:

Kasir Email: kasir1@gmail.com
Is Master Admin: NO

Master Admin: Qashierwise (admin@qashierwise.com)
Effective User ID: 1

Subscription Plan: pro
```

### Test Data Access

```bash
php artisan db:seed --class=TestSubAccountDataAccessSeeder
```

Output:
```
=== Testing Sub-Account Data Access ===

👤 Sub-Account: kasir1@gmail.com
👑 Master Admin: admin@qashierwise.com
🔑 Effective User ID: 1

🏪 Stores accessible: 2
   - Qashierwise
   - Qashierwise 01

📦 Products accessible: 17
🪑 Tables accessible: 3
💬 WhatsApp Account: ✅ Connected
🤖 AI Agent: ✅ Configured
   - Active: Yes
   - Order Enabled: Yes

📅 Reservations accessible: 5
💳 Subscription: pro
   - Status: active

✅ Sub-account data access test completed!
```

## Migration from Old System

### Before (Wrong)

```php
// Sub-account login
$userId = auth()->id(); // Returns 5 (kasir's ID)
$products = Product::where('user_id', $userId)->get(); // Empty!
$subscription = $user->subscription; // null! (shows "Free Trial")
```

### After (Correct)

```php
// Sub-account login
$userId = auth()->user()->getEffectiveUserId(); // Returns 1 (master admin's ID)
$products = Product::where('user_id', $userId)->get(); // All products!
$subscription = $user->getEffectiveSubscription(); // Master admin's "pro" plan
```

## Frontend Integration

### Check Master Admin

```javascript
// API response includes is_master_admin
const user = await api.get('/api/user');
if (user.is_master_admin) {
    // Show all menus without restriction
    showFullMenu();
} else {
    // Show menu based on permissions
    showMenuBasedOnPermissions(user.permissions);
}
```

### Subscription Display

```javascript
// Always use effective subscription
const subscription = user.effective_subscription; // From master admin
displaySubscriptionBadge(subscription.plan); // "pro", not "Free Trial"
```

### Permission-Based UI

```javascript
// Check permission before showing button
if (userHasPermission('pos.edit_product')) {
    showEditButton();
} else {
    hideEditButton();
}
```

## Best Practices

### DO ✅

1. **Always use `getEffectiveUserId()`** for data queries
2. **Always use `getEffectiveSubscription()`** for subscription checks
3. **Check permissions** before allowing sub-account actions
4. **Use `isMasterAdmin()`** to differentiate UI/UX
5. **Test with sub-account login** after making changes

### DON'T ❌

1. **Never use `auth()->id()` directly** for data filtering
2. **Never use `$user->id`** for user_id queries
3. **Never check `$user->subscription`** directly (will be null for sub-accounts)
4. **Never bypass permission checks** for sub-accounts
5. **Never create separate data** for sub-accounts

## Troubleshooting

### Issue: Sub-account tidak bisa lihat data master admin

**Solution:** Controller belum menggunakan `getEffectiveUserId()`. Update controller:

```php
// Before
$userId = auth()->id();

// After
$userId = auth()->user()->getEffectiveUserId();
```

### Issue: Sub-account subscription shows "Free Trial"

**Solution:** Frontend belum menggunakan `getEffectiveSubscription()`. Update API response:

```php
return response()->json([
    'user' => $user,
    'subscription' => $user->getEffectiveSubscription(), // Not $user->subscription
]);
```

### Issue: Sub-account bisa akses fitur yang seharusnya restricted

**Solution:** Permission check belum ditambahkan. Tambahkan middleware:

```php
Route::post('/products', [ProductController::class, 'store'])
    ->middleware('check.pos.permission:pos.create_product');
```

## Summary

Sistem sub-account memastikan:
1. ✅ Sub-account mengakses **semua data master admin**
2. ✅ Sub-account mewarisi **subscription master admin**
3. ✅ Access control via **role permissions**, bukan data isolation
4. ✅ Master admin bisa **manage staff** tanpa khawatir data terpisah
5. ✅ Satu bisnis, satu dataset, banyak user dengan permission berbeda
