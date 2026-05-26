<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;

$kasir = User::where('email', 'kasir@gmail.com')->first();

echo "=== TESTING PERMISSION CHECKS ===\n\n";
echo "User: {$kasir->email}\n";
echo "ID: {$kasir->id}\n\n";

// Test getAllPermissions()
echo 'getAllPermissions() count: '.$kasir->getAllPermissions()->count()."\n";
echo 'Permissions: '.json_encode($kasir->getAllPermissions()->pluck('name'))."\n\n";

// Test hasPermissionTo() with different methods
$perms = ['view_products', 'view_categories', 'view_orders'];

foreach ($perms as $perm) {
    echo "Testing permission: '{$perm}'\n";

    $check1 = $kasir->hasPermissionTo($perm);
    echo "  hasPermissionTo('{$perm}'): ".($check1 ? 'YES' : 'NO')."\n";

    $check2 = $kasir->hasPermissionTo($perm, 'sanctum');
    echo "  hasPermissionTo('{$perm}', 'sanctum'): ".($check2 ? 'YES' : 'NO')."\n";

    $check3 = $kasir->can($perm);
    echo "  can('{$perm}'): ".($check3 ? 'YES' : 'NO')."\n";

    echo "\n";
}
