<?php

// Simple script to set user slug
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;

try {
    $user = User::where('email', 'admin@qashierwise.com')->first();

    if (! $user) {
        echo "User not found!\n";
        exit(1);
    }

    $user->slug = 'admin';
    $user->save();

    echo "✅ Successfully set slug for user: {$user->email}\n";
    echo "   Slug: {$user->slug}\n";
    echo "   Reservation link: http://127.0.0.1:8000/reservation/admin\n";

} catch (Exception $e) {
    echo '❌ Error: '.$e->getMessage()."\n";
    exit(1);
}
