# WhatsApp Variable Types - Quick Reference

## Variable Type Comparison

### Numeric Variables
```
Format:        {{1}}, {{2}}, {{3}}
Use when:      Simple sequential parameters
Meta format:   WhatsApp Mobile App Standard
Example body:  "Halo {{1}}, pesanan {{2}} senilai {{3}}"
Sample values: ["John Doe", "ORD-12345", "Rp 100.000"]
```

### Named Variables  
```
Format:        {{customer_name}}, {{order_id}}, {{amount}}
Use when:      Self-documenting, API integration
Meta format:   WhatsApp Business API Standard
Example body:  "Halo {{customer_name}}, pesanan {{order_id}} senilai {{amount}}"
Sample values: ["John Doe", "ORD-12345", "Rp 100.000"]
```

## Decision Tree

```
Is your integration via Meta Business Dashboard?
├─ YES → Use Numeric ({{1}}, {{2}})
│
└─ NO → Use WhatsApp Cloud API?
    ├─ YES, need readable variables → Use Named ({{customer_name}})
    │
    └─ Can be simple sequencing → Use Numeric ({{1}}, {{2}})
```

## API Quick Examples

### Create Numeric Template
```bash
curl -X POST https://your-api.com/api/whatsapp/templates \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "order_status",
    "category": "UTILITY",
    "language": "id",
    "body": "Status pesanan {{1}} hari {{2}} total {{3}}.",
    "variable_type": "numeric",
    "body_examples": ["ORD-123", "2 hari", "Rp 250.000"]
  }'
```

### Create Named Template
```bash
curl -X POST https://your-api.com/api/whatsapp/templates \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "order_status",
    "category": "UTILITY", 
    "language": "id",
    "body": "Status pesanan {{order_id}} hari {{delivery_days}} total {{amount}}.",
    "variable_type": "named",
    "body_examples": ["ORD-123", "2 hari", "Rp 250.000"]
  }'
```

### Retrieve Templates
```bash
curl https://your-api.com/api/whatsapp/templates \
  -H "Authorization: Bearer YOUR_TOKEN"

# Response includes:
# {
#   "variable_type": "numeric" | "named",
#   "body_examples": ["value1", "value2"]
# }
```

## Common Patterns

### Reservation System
**Recommended**: Named (clearer intent)
```json
{
  "body": "Reservasi {{customer_name}} untuk {{date}} jam {{time}} di {{location}} sudah dikonfirmasi.",
  "variable_type": "named",
  "body_examples": ["John Doe", "2026-02-20", "19:00", "Restaurant ABC"]
}
```

### Order Notification  
**Both work**, Named preferred for clarity
```json
{
  "body": "Pesanan {{order_number}} dari {{customer}} total {{price}} sedang diproses.",
  "variable_type": "named",
  "body_examples": ["ORD12345", "John Doe", "Rp 100.000"]
}
```

### OTP/Code
**Numeric acceptable** (simple parameter)
```json
{
  "body": "Kode verifikasi: {{1}}. Valid {{2}} menit.",
  "variable_type": "numeric",
  "body_examples": ["123456", "10"]
}
```

### Complex Multi-Purpose
**Named recommended**
```json
{
  "body": "Halo {{customer_name}},\n\nReservasi untuk {{party_size}} orang pada {{date}} jam {{time}} di {{restaurant}} sudah dikonfirmasi.\n\nKode booking: {{booking_code}}\n\nTerima kasih!",
  "variable_type": "named",
  "body_examples": [
    "John Doe",
    "4",
    "2026-02-20",
    "19:00", 
    "Restaurant ABC",
    "BK-2026-0220-001"
  ]
}
```

## Validation Rules Summary

| Check | Numeric | Named |
|-------|---------|-------|
| Pattern | `\{\{\d+\}\}` | `\{\{[a-z_][a-z0-9_]*\}\}` |
| Sequential | Recommended | N/A |
| Case sensitive | N/A | Lowercase only |
| Underscores | N/A | Allowed |
| Special chars | None | None |
| Mixed types | ❌ Error | ❌ Error |
| Sample count | Optional | Optional |
| Empty samples | 🗑️ Filtered | 🗑️ Filtered |

## Error Messages

### Numeric → Named Format Mismatch
```
Error: Template contains named variables like {{customer_name}}, {{order_id}} 
but numeric format ({{1}}, {{2}}) was selected. Use numeric format only.
```

### Named → Numeric Format Mismatch
```
Error: Template contains numeric variables like {{1}}, {{2}} 
but named format ({{customer_name}}, {{order_id}}) was selected. Use named format only.
```

### Invalid Named Variable (uppercase)
```
❌ {{CustomerName}}  → Invalid (uppercase)
❌ {{customer-name}} → Invalid (dashes not allowed)
❌ {{customer.name}} → Invalid (dots not allowed)

✅ {{customer_name}}  → Valid
✅ {{customerName}}   → Wait, lowercase enforced, so {{customername}}
```

### Invalid Numeric Variable (non-sequential)
```
❌ {{1}}, {{3}}, {{5}}  → Warning (missing {{2}}, {{4}})

✅ {{1}}, {{2}}, {{3}}  → Perfect
```

## Sample Values Best Practices

1. **Use realistic examples**
   ```json
   // Good
   "body_examples": ["John Doe", "ORD-12345", "Rp 100.000"]
   
   // Poor  
   "body_examples": ["Lorem", "Ipsum", "Dolor"]
   ```

2. **Match expected data types**
   ```json
   // Date example - use actual date format
   "Delivery date: {{delivery_date}}"
   "body_examples": ["2026-02-20"]  // or "20 Februari 2026"
   
   // Currency example - show currency symbol
   "Total: {{total_amount}}"
   "body_examples": ["Rp 250.000"]
   ```

3. **Preserve special formatting**
   ```json
   // If body has line breaks, sample should too
   "Halo {{name}},\n\nPesanan {{order}}..."
   "body_examples": ["John Doe", "ORD-123"]
   
   // If using emojis in template
   "✅ Pesanan {{id}} terkonfirmasi"
   "body_examples": ["ORD-123"]
   ```

4. **Keep examples reasonably sized**
   ```json
   // Good - similar length to real data
   "body_examples": ["John Doe", "ORD-12345", "Rp 100.000"]
   
   // Poor - ridiculously long
   "body_examples": ["Lorem ipsum dolor sit amet consectetur adipisicing elit", ...]
   ```

## Type Selection Flowchart

```
START: Need WhatsApp template

    ↓
Is template for humans to read/approve?
├─ YES → Does each {{}} variable need clear naming?
│        ├─ YES → Use NAMED {{customer_name}}
│        └─ NO  → Use NUMERIC {{1}}, {{2}}
│
└─ NO (System to System) → Can values be retrieved by position?
         ├─ YES → Use NUMERIC {{1}}, {{2}}
         └─ NO  → Use NAMED {{customer_name}}
```

## Field Reference

### Create/Update Template Payload

```typescript
{
  // Required
  "name": string,                    // Template identifier
  "category": enum,                  // UTILITY | MARKETING | AUTHENTICATION
  "language": string,                // "id" | "en" | etc (ISO-639-1)
  "body": string,                    // Template text with {{variables}}
  
  // NEW - Optional but recommended
  "variable_type": enum,             // "numeric" | "named"
  "body_examples": string[],         // Sample values for variables
  
  // Optional
  "header_type": enum,               // TEXT | IMAGE | VIDEO | DOCUMENT
  "header_example": string,          // Sample header if used
  "footer": string,                  // Template footer
  "buttons": Button[]                // CTA buttons
}
```

### Response Template Object

```typescript
{
  "id": number,
  "template_id": string,             // Meta template identifier
  "name": string,
  "status": enum,                    // PENDING | APPROVED | REJECTED
  "category": enum,
  "language": string,
  "body": string,
  
  // NEW
  "variable_type": "numeric|named",
  "body_examples": string[],
  
  "header_type": enum | null,
  "footer": string | null,
  "quality_score": number,
  "created_at": datetime,
  "updated_at": datetime
}
```

## Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| "Type mismatch" error | Selected one type but body has other | Ensure body format matches type |
| Variables not detected | Regex not matching format | Check spacing: `{{1}}` not `{{ 1 }}` |
| Preview shows placeholders | No sample values provided | Add body_examples array |
| Sample values cleared | Changed variable type | Sample values auto-reset for safety |
| API returns null variable_type | Old template pre-migration | Set to "numeric" or update |

## Integration Checklist

```
[ ] Decide: Numeric or Named variables?
[ ] Design template body with correct format
[ ] Prepare realistic sample values
[ ] Test template creation via API or dashboard
[ ] Verify response includes variable_type
[ ] Verify sample_examples in API response
[ ] Test sending message with actual values
[ ] Monitor WhatsApp Meta approval status
[ ] Update frontend if using dynamic variables
[ ] Document variable names for team (if named)
```
