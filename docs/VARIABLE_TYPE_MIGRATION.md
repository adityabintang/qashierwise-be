# Variable Type Migration Guide

## Overview

Saat mengupgrade ke versi terbaru, fitur **Variable Type Support** ditambahkan untuk mendukung dua format variable dalam template WhatsApp:

1. **Numeric**: `{{1}}`, `{{2}}`, `{{3}}`
2. **Named**: `{{customer_name}}`, `{{order_id}}`, `{{amount}}`

Panduan ini menjelaskan bagaimana upgrade mempengaruhi existing templates dan bagaimana menggunakan feature baru.

## What Changed

### Database Schema
```sql
-- Kolom baru di whatsapp_templates
ALTER TABLE whatsapp_templates ADD COLUMN variable_type ENUM('numeric', 'named') DEFAULT 'numeric';
```

**Default untuk existing templates**: `numeric`

Ini berarti semua template lama secara otomatis diasumsikan menggunakan numeric variable format.

### Model Updates
```php
// WhatsAppTemplate.php
protected $fillable = [
    // ...
    'body_examples',
    'variable_type',  // ← NEW
];

protected $casts = [
    'body_examples' => 'array',  // ← NEW
    'variable_type' => 'string',  // ← NEW
];
```

### API Changes
**GET /api/whatsapp/templates** sekarang mengembalikan:
```json
{
  "id": 1,
  "name": "order_confirmation",
  "body": "Pesanan {{1}} total {{2}}",
  "variable_type": "numeric",        // ← NEW
  "body_examples": ["ORD-123", "Rp 100.000"],  // ← NEW
  "status": "APPROVED"
}
```

## Migration Process

### Step 1: Run Migration
```bash
php artisan migrate
```

Output:
```
2026_02_14_093020_add_variable_type_to_whatsapp_templates_table ✓ Done
```

**What happens:**
- Column `variable_type` ditambahkan dengan default `'numeric'`
- Semua existing templates automatically set ke `'numeric'`
- No data loss, semua template tetap berfungsi

### Step 2: Verify Data
```bash
php artisan tinker

# Check templates
App\Models\WhatsAppTemplate::select('id', 'name', 'body', 'variable_type')->get()

# Output:
# [
#   {
#     id: 1,
#     name: "order_confirmation",
#     body: "Pesanan {{1}} total {{2}}",
#     variable_type: "numeric"  ← Default
#   }
# ]
```

### Step 3: No Action Required
Selesai! Semua template tetap berfungsi seperti sebelumnya.

## Backward Compatibility

✅ **Fully backward compatible**

- Existing templates otomatis set ke `numeric` type
- API responses still work dengan existing clients
- Templates continue working dengan WhatsApp Cloud API
- No breaking changes

## Using Named Variables (New Feature)

Setelah migration, Anda bisa update template untuk gunakan named variables:

### Option 1: Via Admin Dashboard

1. Create new template
2. Select "Variable Type" → **Named**
3. Fill body: `Halo {{customer_name}}, pesanan {{order_id}} total {{amount}}`
4. Add sample values
5. Create template

### Option 2: Via API

**Update existing template dengan named variables:**
```bash
# Disarankan delete old & create new untuk consistency
# Karena WhatsApp API tidak bisa langsung update template body

POST /api/whatsapp/templates
{
  "name": "order_confirmation_v2",
  "category": "UTILITY",
  "language": "id",
  "body": "Halo {{customer_name}}, pesanan {{order_id}} senilai {{amount}}.",
  "variable_type": "named",
  "body_examples": ["John Doe", "ORD-12345", "Rp 100.000"]
}
```

### Option 3: Via Tinker

```bash
php artisan tinker

$template = App\Models\WhatsAppTemplate::create([
    'name' => 'order_confirmation_named',
    'body' => 'Pesanan {{order_id}} senilai {{amount}} untuk {{customer_name}}',
    'variable_type' => 'named',
    'body_examples' => ['ORD-12345', 'Rp 100.000', 'John Doe'],
    // ... other fields
]);
```

## Validation Rules

### Numeric Variables
```javascript
// Format: {{1}}, {{2}}, {{3}} (sequential)
// When selected, body must only contain {{N}} format
// Error if body has {{customer_name}} or mixed format
```

### Named Variables
```javascript
// Format: {{customer_name}}, {{order_id}} (alphanumeric with underscore)
// When selected, body must only contain {{name}} format
// Error if body has {{1}} or mixed format
```

## Migration Decision Matrix

| Scenario | Action | Notes |
|----------|--------|-------|
| Existing numeric template | ✅ Keep unchanged | Auto set to `numeric`, continue working |
| Want to use named format | ➕ Create new template | Select `named` type, fill with {{name}} format |
| Testing new feature | ➕ Create new test template | Won't affect existing templates |
| Deprecate old template | ❌ Delete or mark inactive | Create replacement with preferred format |

## Example Migration Scenario

### Before (Numeric Only)
```
Template: order_confirmation
Body: "Pesanan {{1}} untuk {{2}} senilai {{3}}"
Status: APPROVED
Variable Type: Not applicable
```

### After Upgrade (Still Works)
```
Template: order_confirmation
Body: "Pesanan {{1}} untuk {{2}} senilai {{3}}"
Status: APPROVED
Variable Type: numeric  ← Auto set to numeric
Body Examples: null (if not set before)
```

### New Template with Named Format
```
Template: order_confirmation_v2 (NEW)
Body: "Pesanan {{order_id}} untuk {{customer}} senilai {{amount}}"
Status: PENDING (awaiting WhatsApp approval)
Variable Type: named
Body Examples: ["ORD-12345", "John Doe", "Rp 100.000"]
```

## Testing After Migration

### Unit Test
```bash
# Verify numeric template still works
php artisan test tests/Feature/WhatsAppTemplateSampleVariablesTest.php --filter="numeric"

# Output: ✓ template with numeric variables and numeric type
```

### Manual Test
```bash
# 1. Create numeric template (old way)
php artisan tinker
App\Models\WhatsAppTemplate::where('variable_type', 'numeric')->count()

# 2. Create named template (new way)
App\Models\WhatsAppTemplate::where('variable_type', 'named')->count()

# Both must work correctly
```

### Integration Test
```bash
# Send message with both templates
php artisan tinker

$numeric = App\Models\WhatsAppTemplate::where('variable_type', 'numeric')->first();
$named = App\Models\WhatsAppTemplate::where('variable_type', 'named')->first();

// Both should send correctly via WhatsApp API
```

## Rollback (If Needed)

Jika ada issue, Anda bisa:

### Option 1: Revert Migration
```bash
php artisan migrate:rollback --step=1
```

**Effect:**
- Column `variable_type` dihapus
- All templates tetap intact (column `body_examples` tetap)
- Feature variable type tidak available, tetapi numeric templates tetap berfungsi

### Option 2: Keep Column, Set All to Numeric
```bash
php artisan tinker

// Ensure all templates set to numeric
App\Models\WhatsAppTemplate::whereNull('variable_type')->update(['variable_type' => 'numeric']);
```

**Effect:**
- Column tetap ada
- Semua templates berkisar ke numeric format
- Feature tetap available tapi hanya numeric dipakai

## FAQ

### Q: Apakah existing templates otomatis convert ke named format?

**A:** Tidak. Semua existing templates tetap di `numeric` format. Hanya template baru yang bisa pilih `named`.

### Q: Bisa ganti template dari numeric ke named?

**A:** Tidak langsung. Karena WhatsApp tidak support update template body. Solusi:
1. Buat template baru dengan format named
2. Delete template lama (jika sudah approved)
3. Update application untuk pakai template baru

### Q: Apakah migration otomatis jaga data existing?

**A:** Ya, 100% backward compatible:
- Semua existing templates tetap berfungsi
- Kolom `body_examples` dari sebelumnya tetap ada
- Hanya menambah kolom baru `variable_type` dengan default

### Q: Bagaimana jika database sudah punya some templates dengan `body_examples`?

**A:** Kategori baik. Kolom `variable_type` ditambahkan separately dan set ke `numeric` untuk semua.

```sql
-- Sebelum migration
whatsapp_templates: [id, name, body, body_examples, ...]

-- Sesudah migration
whatsapp_templates: [id, name, body, body_examples, variable_type, ...]
                                                           ↑
                                                   Baru! Default='numeric'
```

### Q: Bisa lihat templates yang mana yang `named` vs `numeric`?

**A:** Ya, via tinker atau query:
```bash
php artisan tinker

# Count per type
App\Models\WhatsAppTemplate::groupBy('variable_type')->selectRaw('variable_type, COUNT(*) as count')->get()

# Output:
# {variable_type: "numeric", count: 15}
# {variable_type: "named", count: 2}
```

## Support

Jika ada issue setelah migration:

1. **Numeric templates tidak send**:
   - Check: Body format harus `{{1}}`, `{{2}}`
   - Validate: `php artisan test ... --filter="numeric"`

2. **Named variables tidak detect**:
   - Check: Form variable type ke "Named"
   - Validate: Body harus `{{customer_name}}` format (lowercase, underscore)

3. **Data missing**:
   - Check migrations applied: `php artisan migrate:status`
   - Verify: `php artisan tinker` → `DB::table('whatsapp_templates')->first()`

4. **API still working?**:
   - Check: `/api/whatsapp/templates` response includes `variable_type`
   - Expected: All existing templates have `variable_type: "numeric"`
