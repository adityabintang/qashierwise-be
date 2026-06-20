# WhatsApp Template Sample Variables & Variable Types

## Overview

Fitur lengkap untuk support dua jenis variable dalam template WhatsApp with sample values:

1. **Numeric Variables**: `{{1}}`, `{{2}}`, `{{3}}` - placeholder dengan nomor
2. **Named Variables**: `{{customer_name}}`, `{{order_id}}` - placeholder dengan nama custom

Setiap variable bisa memiliki **sample values** (contoh nilai) untuk preview & personalisasi template.

## Cara Penggunaan

### 1. Pilih Jenis Variabel (Variable Type)

**Di Form Template Creation**, ada dropdown "Variable Type" dengan dua pilihan:

```
○ Numeric ({{1}}, {{2}}, etc.)
○ Named ({{customer_name}}, {{order_id}}, etc.)
```

**Pilihan ini akan mempengaruhi:**
- Placeholder di body text
- Label untuk sample values
- Validasi - mencegah pencampuran kedua jenis

### 2a. Numeric Variables ({{1}}, {{2}})

**Gunakan saat:**
- Tidak perlu nama variable yang meaningful
- Urutan variable sudah pasti
- Contoh: `Hello {{1}}, your order {{2}} total {{3}}.`

**Di Body Text:**
```
Halo {{1}}, pesanan Anda {{2}} total {{3}}.
```

**Sample Values akan muncul untuk:**
- Variable {{1}}
- Variable {{2}}  
- Variable {{3}}

**Preview:**
```
Halo John Doe, pesanan Anda ORD-12345 total Rp 100.000.
```

### 2b. Named Variables ({{customer_name}}, {{order_id}})

**Gunakan saat:**
- Setiap variable memiliki nama yang meaningful
- Lebih readable dan maintainable
- Contoh: `Hello {{customer_name}}, your order {{order_id}} total {{amount}}.`

**Di Body Text:**
```
Halo {{customer_name}}, pesanan Anda {{order_id}} total {{amount}}.
```

**Sample Values akan muncul untuk:**
- Variable {{customer_name}}
- Variable {{order_id}}
- Variable {{amount}}

**Preview:**
```
Halo John Doe, pesanan Anda ORD-12345 total Rp 100.000.
```

### 3. Isi Sample Values (Opsional)

Untuk setiap variable yang terdeteksi, isi contoh nilai:

```
{{customer_name}} Example: John Doe
{{order_id}} Example: ORD-12345
{{amount}} Example: Rp 100.000
```

Template preview akan update real-time dengan sample values ini.

## Validasi Variable Type

Form akan **validate consistency** antara jenis variable yang dipilih dan apa yang ada di body text:

### ❌ Error Case 1: Named Type tapi Content Numeric
```
Variable Type: Named ({{customer_name}})
Body Text: "Hello {{1}}, order {{2}}"

Error: 
"Template contains numeric variables like {{1}}, {{2}} but named format 
({{customer_name}}, {{order_id}}) was selected. Use named format only."
```

### ❌ Error Case 2: Numeric Type tapi Content Named
```
Variable Type: Numeric ({{1}})
Body Text: "Hello {{customer_name}}, order {{order_id}}"

Error:
"Template contains named variables like {{customer_name}} but numeric format 
({{1}}, {{2}}) was selected. Use numeric format only."
```

### ✅ Valid Case
```
Variable Type: Named
Body Text: "Hello {{customer_name}}, order {{order_id}} total {{amount}}"

Status: ✅ Valid - akan proceed ke sample values input
```

## Database Schema

### Kolom Baru

**1. `body_examples` (JSON Array)**
```sql
ALTER TABLE whatsapp_templates ADD COLUMN body_examples JSON NULL;
```

**Type**: JSON Array  
**Example**: `["John Doe", "ORD-12345", "Rp 100.000"]`

**2. `variable_type` (ENUM)**
```sql
ALTER TABLE whatsapp_templates ADD COLUMN variable_type ENUM('numeric', 'named') DEFAULT 'numeric';
```

**Values**: 
- `numeric`: Menggunakan {{1}}, {{2}}, dll
- `named`: Menggunakan {{customer_name}}, {{order_id}}, dll

## API Usage

### Create Template dengan Named Variables

**Request**:
```json
POST /api/whatsapp/templates

{
  "name": "order_confirmation",
  "category": "UTILITY",
  "language": "id",
  "body": "Halo {{customer_name}}, pesanan {{order_id}} senilai {{amount}} sudah dikonfirmasi.",
  "variable_type": "named",
  "body_examples": ["John Doe", "ORD-12345", "Rp 100.000"]
}
```

**Response**:
```json
{
  "success": true,
  "message": "Template created successfully",
  "data": {
    "id": 1,
    "template_id": "template_123",
    "name": "order_confirmation",
    "status": "PENDING"
  }
}
```

### Create Template dengan Numeric Variables

**Request**:
```json
POST /api/whatsapp/templates

{
  "name": "appointment_reminder",
  "category": "UTILITY",
  "language": "id",
  "body": "Reminder: appointment {{1}} pada {{2}} untuk {{3}}.",
  "variable_type": "numeric",
  "body_examples": ["Dr. Smith", "2026-02-20 10:00", "Haircut"]
}
```

### Get Templates (Include Variable Type & Examples)

**Request**:
```
GET /api/whatsapp/templates
```

**Response** (sample):
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "order_confirmation",
      "status": "APPROVED",
      "category": "UTILITY",
      "language": "id",
      "body": "Halo {{customer_name}}, pesanan {{order_id}} senilai {{amount}}.",
      "variable_type": "named",
      "body_examples": ["John Doe", "ORD-12345", "Rp 100.000"],
      "quality_score": 95
    },
    {
      "id": 2,
      "name": "appointment_reminder",
      "status": "APPROVED", 
      "body": "Reminder: appointment {{1}} pada {{2}}.",
      "variable_type": "numeric",
      "body_examples": ["Dr. Smith", "2026-02-20 10:00"],
      "quality_score": 92
    }
  ]
}
```

## Frontend Features

### 1. Dynamic Variable Type Selection
- Dropdown untuk pilih "Numeric" atau "Named"
- Dynamic placeholder di textarea sesuai pilihan
- Auto-clear examples saat ganti variable type

### 2. Smart Variable Detection
- Auto-detect variable type dari body text
- Numeric: identifies `{{1}}`, `{{2}}`, etc
- Named: identifies `{{customer_name}}`, `{{order_id}}`, etc

### 3. Live Preview
- Real-time preview dengan sample values
- Display sample values di dalam template preview
- Placeholders saat sample values kosong

### 4. Validation Messages
- Clear error messages saat variable type mismatch
- Warning saat numeric variables tidak sequential ({{1}}, {{3}} tanpa {{2}})
- Character count untuk body text (max 1024)

## Testing

9 comprehensive test cases:

```
✓ can create template with sample variables
✓ can create template without sample variables  
✓ can create template with empty sample values
✓ filters out empty sample values
✓ sample variables with special characters
✓ template creation without authentication fails
✓ cannot create template with invalid name and sample variables
✓ template with numeric variables and numeric type
✓ template with named variables and named type
```

Run tests:
```bash
php artisan test tests/Feature/WhatsAppTemplateSampleVariablesTest.php
```

## Use Cases

### 1. Reservation Confirmation (Named)
```
Variable Type: Named
Body: "Halo {{customer_name}}, reservasi untuk {{person_count}} orang 
       pada {{date}} jam {{time}} sudah dikonfirmasi."

Examples:
- customer_name: John Doe
- person_count: 4
- date: 2026-02-15
- time: 19:00
```

### 2. Order Status (Numeric)
```
Variable Type: Numeric
Body: "Pesanan {{1}} nomor {{2}} total {{3}} telah dipproses.
       Estimasi tiba {{4}}."

Examples:
- {{1}}: #ORD001
- {{2}}: ABC123XYZ
- {{3}}: Rp 250.000
- {{4}}: 2 jam
```

### 3. OTP Verification (Named)
```
Variable Type: Named
Body: "Kode verifikasi: {{verification_code}}.
       Valid selama {{validity_minutes}} menit."

Examples:
- verification_code: 123456
- validity_minutes: 10
```

## Validation Rules

| Rule | Numeric | Named |
|------|---------|-------|
| Format | `{{1}}`, `{{2}}` | `{{name}}`, `{{id}}` |
| Characters | Digits only | Lowercase, underscore, alphanumeric |
| Sequential | Warning if not {{1}}, {{2}}, {{3}} | No requirement |
| Mixed Types | ❌ Not allowed | ❌ Not allowed |
| Sample Values | Optional | Optional |
| Empty Examples | ✅ Filtered out | ✅ Filtered out |

## Meta Business Platform Alignment

Fitur ini align dengan Meta Business Platform specification:

- **Numeric Format**: WhatsApp Mobile App format
  - Variables: `{{1}}`, `{{2}}`, etc
  - Benefit: Simple, auto-increment

- **Named Format**: WhatsApp Business API format  
  - Variables: `{{customer_name}}`, `{{order_id}}`, etc
  - Benefit: Readable, maintainable, safe to share

Both formats fully supported oleh WhatsApp Cloud API v21.0+

## Migration Guide

Jika sudah punya template lama (sebelum feature ini):

1. **Existing templates default ke `numeric`** type
2. **body_examples column nullable** - backward compatible
3. **No data loss** - semua template lama tetap berfungsi
4. **Update optional** - bisa update template ke gunakan named variables

## Troubleshooting

### Q: Dapat error "Template berisi parameter variabel dengan format yang salah"

**A:** Ini adalah validasi dari Meta Business Platform. Berarti:
- Variable type yang dipilih tidak match dengan content
- Contoh: Pilih "Named" tapi body punya `{{1}}`
- **Solusi**: Ganti body text atau ubah variable type

### Q: Sample values tidak muncul di preview

**A:** Kemungkinan:
- Tidak ada variables terdeteksi di body text
- Variable type tidak sesuai dengan format di body
- **Solusi**: Pastikan format variable sesuai dengan type yang dipilih

### Q: Ganti variable type, contoh values hilang

**A:** Ini feature by design untuk prevent confusion. 
- Saat ganti type, contoh values di-reset
- **Alasan**: Format lama mungkin tidak cocok dengan type baru
- **Solusi**: Isi ulang sample values sesuai variable baru
