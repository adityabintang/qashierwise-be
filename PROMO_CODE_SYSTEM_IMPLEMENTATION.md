# Promo Code System Implementation

## Overview

Karena Midtrans Snap tidak memiliki fitur input kode kupon di payment page mereka, kita implementasikan sistem promo code di aplikasi kita sendiri.

## How It Works

```
User input promo code di website
    ↓
Validate promo code
    ↓
Calculate discount
    ↓
Send discounted price to Midtrans
    ↓
User pays discounted amount
    ↓
Record promo code usage
```

## Database Schema

### Table: `promo_codes`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| code | string | Unique promo code (e.g., "NEWYEAR2026") |
| type | enum | 'percentage' or 'fixed' |
| value | decimal | Percentage (20 = 20%) or fixed amount |
| max_uses | int | Maximum usage limit (NULL = unlimited) |
| used_count | int | Current usage count |
| valid_from | timestamp | Start date (NULL = immediate) |
| valid_until | timestamp | End date (NULL = no expiry) |
| applicable_plans | json | ['standard', 'pro'] or NULL for all |
| min_purchase | decimal | Minimum purchase amount |
| is_active | boolean | Active status |
| description | text | Description for admin |

### Table: `promo_code_usages`
| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| promo_code_id | bigint | Foreign key to promo_codes |
| user_id | bigint | Foreign key to users |
| subscription_id | bigint | Foreign key to subscriptions |
| original_amount | decimal | Original price |
| discount_amount | decimal | Discount applied |
| final_amount | decimal | Final price paid |
| created_at | timestamp | Usage timestamp |

## Promo Code Types

### 1. Percentage Discount
```php
code: "SAVE20"
type: "percentage"
value: 20  // 20% off
```

**Example:**
- Original: Rp 350.000
- Discount: Rp 70.000 (20%)
- Final: Rp 280.000

### 2. Fixed Amount Discount
```php
code: "DISCOUNT50K"
type: "fixed"
value: 50000  // Rp 50.000 off
```

**Example:**
- Original: Rp 350.000
- Discount: Rp 50.000
- Final: Rp 300.000

## Features

### ✅ Validation Rules
- Code must exist and be active
- Must be within valid date range
- Must not exceed max uses
- Must be applicable to selected plan
- Must meet minimum purchase requirement

### ✅ Flexible Configuration
- Set expiry dates
- Limit usage count
- Apply to specific plans only
- Set minimum purchase amount
- Enable/disable anytime

### ✅ Usage Tracking
- Track who used the code
- Track when it was used
- Track discount amount
- Link to subscription

## API Endpoints

### Validate Promo Code (Optional - for preview)
```http
POST /api/promo-codes/validate
Authorization: Bearer {token}
Content-Type: application/json

{
  "code": "NEWYEAR2026",
  "plan_id": "standard",
  "duration": "1_month"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "valid": true,
    "message": "Kode promo berhasil! Hemat Rp 70.000",
    "original_amount": 350000,
    "discount": 70000,
    "final_amount": 280000
  }
}
```

### Checkout with Promo Code
```http
POST /api/subscription/checkout
Authorization: Bearer {token}
Content-Type: application/json

{
  "plan_id": "standard",
  "duration": "1_month",
  "promo_code": "NEWYEAR2026"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "redirect_url": "https://app.sandbox.midtrans.com/snap/v3/...",
    "snap_token": "...",
    "original_amount": 350000,
    "final_amount": 280000,
    "discount": 70000
  }
}
```

## Creating Promo Codes

### Via Tinker (for now)
```php
php artisan tinker

use App\Models\PromoCode;

// Create 20% discount code
PromoCode::create([
    'code' => 'NEWYEAR2026',
    'type' => 'percentage',
    'value' => 20,
    'max_uses' => 100,
    'valid_from' => now(),
    'valid_until' => now()->addDays(30),
    'applicable_plans' => ['standard', 'pro'],
    'min_purchase' => 100000,
    'is_active' => true,
    'description' => 'New Year 2026 Promo - 20% off',
]);

// Create fixed discount code
PromoCode::create([
    'code' => 'FIRST50K',
    'type' => 'fixed',
    'value' => 50000,
    'max_uses' => 50,
    'valid_until' => now()->addDays(7),
    'is_active' => true,
    'description' => 'First time user - Rp 50.000 off',
]);

// Create unlimited code for specific plan
PromoCode::create([
    'code' => 'PROPLAN10',
    'type' => 'percentage',
    'value' => 10,
    'max_uses' => null, // Unlimited
    'applicable_plans' => ['pro'], // Pro plan only
    'is_active' => true,
    'description' => 'Pro plan discount - 10% off',
]);
```

## Example Promo Codes

### 1. New Year Promo
```php
code: "NEWYEAR2026"
type: "percentage"
value: 20
valid_until: 2026-01-31
description: "New Year Sale - 20% off all plans"
```

### 2. First Time User
```php
code: "FIRST50K"
type: "fixed"
value: 50000
max_uses: 100
description: "First time user discount"
```

### 3. Annual Plan Bonus
```php
code: "ANNUAL15"
type: "percentage"
value: 15
applicable_plans: null  // All plans
min_purchase: 1000000  // Only for annual plans
description: "Extra 15% off annual plans"
```

### 4. Flash Sale
```php
code: "FLASH24H"
type: "percentage"
value: 30
max_uses: 50
valid_from: "2026-02-01 00:00:00"
valid_until: "2026-02-01 23:59:59"
description: "24-hour flash sale"
```

## Frontend Integration (Next Step)

### Add Promo Code Input
```html
<div class="promo-code-section">
    <input 
        type="text" 
        x-model="promoCode"
        placeholder="Masukkan kode promo"
        class="promo-input"
    />
    <button @click="applyPromoCode()">
        Gunakan
    </button>
</div>

<div x-show="discount > 0" class="discount-info">
    <p>Diskon: Rp <span x-text="formatPrice(discount)"></span></p>
    <p>Total: Rp <span x-text="formatPrice(finalAmount)"></span></p>
</div>
```

### Alpine.js Logic
```javascript
promoCode: '',
discount: 0,
finalAmount: 0,

async applyPromoCode() {
    // Validate promo code
    const response = await fetch('/api/promo-codes/validate', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${this.token}`,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            code: this.promoCode,
            plan_id: this.selectedPlan,
            duration: this.selectedDuration
        })
    });
    
    const data = await response.json();
    
    if (data.success && data.data.valid) {
        this.discount = data.data.discount;
        this.finalAmount = data.data.final_amount;
        // Show success message
    } else {
        // Show error message
    }
}
```

## Admin Panel (Future)

Create admin interface to:
- ✅ Create/edit/delete promo codes
- ✅ View usage statistics
- ✅ Enable/disable codes
- ✅ Export usage reports
- ✅ Set expiry dates
- ✅ Monitor performance

## Benefits

### For Business
- ✅ Flexible marketing campaigns
- ✅ Track promo effectiveness
- ✅ Control discount amounts
- ✅ Limit usage to prevent abuse
- ✅ Target specific plans
- ✅ Time-limited promotions

### For Users
- ✅ Get discounts on subscriptions
- ✅ Easy to apply (just enter code)
- ✅ See discount before payment
- ✅ Transparent pricing

## Security Considerations

### ✅ Implemented
- Code validation before checkout
- Usage limit enforcement
- Expiry date checking
- Plan applicability check
- Minimum purchase requirement

### ⚠️ Additional (Optional)
- One-time use per user
- IP-based rate limiting
- Referral tracking
- A/B testing

## Migration

Run migration to create tables:
```bash
php artisan migrate
```

## Testing

### Test Percentage Discount
```bash
# Create test code
php artisan tinker
PromoCode::create([
    'code' => 'TEST20',
    'type' => 'percentage',
    'value' => 20,
    'is_active' => true,
]);

# Test checkout with code
# Original: Rp 350.000
# Expected: Rp 280.000 (20% off)
```

### Test Fixed Discount
```bash
# Create test code
PromoCode::create([
    'code' => 'TEST50K',
    'type' => 'fixed',
    'value' => 50000,
    'is_active' => true,
]);

# Test checkout with code
# Original: Rp 350.000
# Expected: Rp 300.000 (Rp 50.000 off)
```

## Summary

✅ Promo code system implemented
✅ Supports percentage and fixed discounts
✅ Flexible validation rules
✅ Usage tracking
✅ Works with Midtrans Snap
✅ Ready for frontend integration

Next step: Add promo code input UI to pricing page!
