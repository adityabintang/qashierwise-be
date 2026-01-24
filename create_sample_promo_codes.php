<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\PromoCode;

echo "=== Creating Sample Promo Codes ===\n\n";

// 1. New Year Promo - 20% off
$promo1 = PromoCode::create([
    'code' => 'NEWYEAR2026',
    'type' => 'percentage',
    'value' => 20,
    'max_uses' => 100,
    'valid_from' => now(),
    'valid_until' => now()->addDays(30),
    'applicable_plans' => ['standard', 'pro'],
    'min_purchase' => null,
    'is_active' => true,
    'description' => 'New Year 2026 Promo - 20% off all plans',
]);

echo "✅ Created: {$promo1->code}\n";
echo "   Type: {$promo1->type}\n";
echo "   Value: {$promo1->value}%\n";
echo "   Valid until: {$promo1->valid_until}\n\n";

// 2. First Time User - Rp 50.000 off
$promo2 = PromoCode::create([
    'code' => 'FIRST50K',
    'type' => 'fixed',
    'value' => 50000,
    'max_uses' => 50,
    'valid_from' => now(),
    'valid_until' => now()->addDays(7),
    'applicable_plans' => null, // All plans
    'min_purchase' => null,
    'is_active' => true,
    'description' => 'First time user - Rp 50.000 off',
]);

echo "✅ Created: {$promo2->code}\n";
echo "   Type: {$promo2->type}\n";
echo "   Value: Rp " . number_format($promo2->value, 0, ',', '.') . "\n";
echo "   Valid until: {$promo2->valid_until}\n\n";

// 3. Annual Plan Bonus - Extra 15% off
$promo3 = PromoCode::create([
    'code' => 'ANNUAL15',
    'type' => 'percentage',
    'value' => 15,
    'max_uses' => null, // Unlimited
    'valid_from' => now(),
    'valid_until' => now()->addMonths(3),
    'applicable_plans' => null, // All plans
    'min_purchase' => 1000000, // Only for annual plans
    'is_active' => true,
    'description' => 'Extra 15% off annual plans (min purchase Rp 1.000.000)',
]);

echo "✅ Created: {$promo3->code}\n";
echo "   Type: {$promo3->type}\n";
echo "   Value: {$promo3->value}%\n";
echo "   Min purchase: Rp " . number_format($promo3->min_purchase, 0, ',', '.') . "\n";
echo "   Valid until: {$promo3->valid_until}\n\n";

// 4. Pro Plan Special - 10% off
$promo4 = PromoCode::create([
    'code' => 'PROPLAN10',
    'type' => 'percentage',
    'value' => 10,
    'max_uses' => null, // Unlimited
    'valid_from' => now(),
    'valid_until' => null, // No expiry
    'applicable_plans' => ['pro'], // Pro plan only
    'min_purchase' => null,
    'is_active' => true,
    'description' => 'Pro plan discount - 10% off (no expiry)',
]);

echo "✅ Created: {$promo4->code}\n";
echo "   Type: {$promo4->type}\n";
echo "   Value: {$promo4->value}%\n";
echo "   Applicable to: Pro plan only\n";
echo "   Valid until: No expiry\n\n";

// 5. Test Code - 50% off (for testing)
$promo5 = PromoCode::create([
    'code' => 'TEST50',
    'type' => 'percentage',
    'value' => 50,
    'max_uses' => 10,
    'valid_from' => now(),
    'valid_until' => now()->addDays(365),
    'applicable_plans' => null,
    'min_purchase' => null,
    'is_active' => true,
    'description' => 'Test code - 50% off (for testing purposes)',
]);

echo "✅ Created: {$promo5->code}\n";
echo "   Type: {$promo5->type}\n";
echo "   Value: {$promo5->value}%\n";
echo "   Max uses: {$promo5->max_uses}\n\n";

echo "=== Summary ===\n\n";
echo "Total promo codes created: 5\n\n";

echo "Available Promo Codes:\n";
echo "1. NEWYEAR2026 - 20% off (expires in 30 days)\n";
echo "2. FIRST50K - Rp 50.000 off (expires in 7 days)\n";
echo "3. ANNUAL15 - 15% off annual plans (min Rp 1.000.000)\n";
echo "4. PROPLAN10 - 10% off Pro plan (no expiry)\n";
echo "5. TEST50 - 50% off for testing\n\n";

echo "✅ Sample promo codes created successfully!\n";
