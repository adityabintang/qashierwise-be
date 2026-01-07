<?php

/**
 * Test RLS for Contacts and Messages
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;

echo "=== Test Contacts & Messages RLS ===\n\n";

// Get user 1 (has data)
$user1 = User::find(1);

if (! $user1) {
    echo "User 1 not found\n";
    exit(1);
}

echo "User 1: {$user1->name}\n";
auth()->login($user1);

$contacts = WhatsAppContact::count();
$messages = WhatsAppMessage::count();

echo "  - Contacts: {$contacts}\n";
echo "  - Messages: {$messages}\n\n";

if ($contacts > 0) {
    $contact = WhatsAppContact::first();
    echo "  First Contact:\n";
    echo "    - ID: {$contact->id}\n";
    echo "    - Name: {$contact->name}\n";
    echo "    - WA ID: {$contact->wa_id}\n";
    echo "    - User ID: {$contact->user_id}\n";

    $contactMessages = WhatsAppMessage::where('contact_id', $contact->id)->count();
    echo "    - Messages: {$contactMessages}\n\n";
}

auth()->logout();

// Test as different user
echo "=== Test as User 2 (should see nothing) ===\n";
$user2 = User::find(2);

if ($user2) {
    echo "User 2: {$user2->name}\n";
    auth()->login($user2);

    $contacts = WhatsAppContact::count();
    $messages = WhatsAppMessage::count();

    echo "  - Contacts: {$contacts}\n";
    echo "  - Messages: {$messages}\n";

    if ($contacts > 0) {
        echo "  ❌ ERROR: User 2 can see contacts!\n";
    } else {
        echo "  ✅ Correct: User 2 cannot see any contacts\n";
    }

    if ($messages > 0) {
        echo "  ❌ ERROR: User 2 can see messages!\n";
    } else {
        echo "  ✅ Correct: User 2 cannot see any messages\n";
    }

    auth()->logout();
} else {
    echo "User 2 not found\n";
}

echo "\n=== Test without auth (should see nothing) ===\n";
$contacts = WhatsAppContact::count();
$messages = WhatsAppMessage::count();

echo "  - Contacts: {$contacts}\n";
echo "  - Messages: {$messages}\n";

if ($contacts > 0 || $messages > 0) {
    echo "  ❌ ERROR: Unauthenticated can see data!\n";
} else {
    echo "  ✅ Correct: Unauthenticated cannot see any data\n";
}

echo "\n=== Test Complete ===\n";
