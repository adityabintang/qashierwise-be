<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\Subscription;

echo "=== Checking Users and Subscriptions ===\n\n";

$users = User::all();
echo "Total Users: {$users->count()}\n\n";

foreach ($users as $user) {
    echo "User {$user->id}: {$user->email}\n";
    $subscription = $user->subscription;
    if ($subscription) {
        echo "  ✅ Has subscription:\n";
        echo "     - Plan: {$subscription->plan_name}\n";
        echo "     - Status: {$subscription->status}\n";
        echo "     - Provider: {$subscription->provider}\n";
        echo "     - Created: {$subscription->created_at}\n";
    } else {
        echo "  ❌ No subscription\n";
    }
    echo "\n";
}

echo "--- All Subscriptions ---\n\n";
$subscriptions = Subscription::with('user')->get();
echo "Total Subscriptions: {$subscriptions->count()}\n\n";

foreach ($subscriptions as $sub) {
    echo "Subscription {$sub->id}:\n";
    echo "  - User: {$sub->user->email} (ID: {$sub->user_id})\n";
    echo "  - Plan: {$sub->plan_name}\n";
    echo "  - Status: {$sub->status}\n";
    echo "  - Provider: {$sub->provider}\n";
    echo "  - Period: {$sub->current_period_start} to {$sub->current_period_end}\n";
    echo "  - Created: {$sub->created_at}\n";
    echo "\n";
}
