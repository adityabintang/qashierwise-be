<?php

// Simple script to create default reservation config
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\ReservationConfig;
use App\Models\Store;
use App\Models\User;

try {
    $user = User::where('email', 'admin@qashierwise.com')->first();

    if (! $user) {
        echo "❌ User not found!\n";
        exit(1);
    }

    // Get first store
    $store = Store::where('user_id', $user->id)->first();

    if (! $store) {
        echo "❌ No store found for this user. Please create a store first.\n";
        exit(1);
    }

    // Check if config exists
    $config = ReservationConfig::where('user_id', $user->id)
        ->where('store_id', $store->id)
        ->first();

    if ($config) {
        echo "✅ Reservation config already exists:\n";
        echo "   Store: {$store->name}\n";
        echo '   Active: '.($config->is_active ? 'Yes' : 'No')."\n";
        echo '   Available slots: '.count($config->available_slots ?? [])."\n";
        exit(0);
    }

    // Create default config
    $config = ReservationConfig::create([
        'user_id' => $user->id,
        'store_id' => $store->id,
        'is_active' => true,
        'available_slots' => [],
        'guest_options' => [2, 4, 6, 8],
        'reservation_fee' => 0,
        'dp_percentage' => 50,
        'allow_full_payment' => true,
        'allow_dp_payment' => true,
        'available_tables' => [],
        'available_products' => [],
        'enable_menu_selection' => false,
        'require_menu_selection' => false,
    ]);

    echo "✅ Successfully created reservation config:\n";
    echo "   Store: {$store->name}\n";
    echo "   Link: http://127.0.0.1:8000/reservation/admin\n";
    echo "\n";
    echo "⚠️  Please configure available slots in the dashboard before accepting reservations.\n";

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    exit(1);
}
