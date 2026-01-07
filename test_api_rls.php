<?php

/**
 * Test API endpoints RLS
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;

echo "=== Test API Endpoints RLS ===\n\n";

// Get user 1 token
$user1 = User::find(1);
$token1 = $user1->createToken('test')->plainTextToken;

echo "User 1: {$user1->name}\n";
echo 'Token: '.substr($token1, 0, 20)."...\n\n";

// Test contacts endpoint
echo "Testing GET /api/whatsapp/contacts\n";
$request = Request::create('/api/whatsapp/contacts', 'GET');
$request->headers->set('Authorization', "Bearer {$token1}");
$request->setUserResolver(function () use ($user1) {
    return $user1;
});

try {
    $controller = app(\App\Http\Controllers\Api\WhatsAppController::class);
    $response = $controller->getContacts($request);
    $data = json_decode($response->getContent(), true);

    echo "  Status: {$response->getStatusCode()}\n";
    echo '  Contacts: '.($data['total'] ?? 0)."\n";
    echo "  ✅ Success\n\n";
} catch (\Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n\n";
}

// Test messages endpoint
echo "Testing GET /api/whatsapp/messages\n";
$request = Request::create('/api/whatsapp/messages', 'GET');
$request->headers->set('Authorization', "Bearer {$token1}");
$request->setUserResolver(function () use ($user1) {
    return $user1;
});

try {
    $controller = app(\App\Http\Controllers\Api\WhatsAppController::class);
    $response = $controller->getMessages($request);
    $data = json_decode($response->getContent(), true);

    echo "  Status: {$response->getStatusCode()}\n";
    echo '  Messages: '.($data['total'] ?? 0)."\n";
    echo "  ✅ Success\n\n";
} catch (\Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n\n";
}

// Test as user 2
$user2 = User::find(2);
if ($user2) {
    $token2 = $user2->createToken('test')->plainTextToken;

    echo "User 2: {$user2->name}\n";
    echo 'Token: '.substr($token2, 0, 20)."...\n\n";

    // Test contacts endpoint
    echo "Testing GET /api/whatsapp/contacts (User 2)\n";
    $request = Request::create('/api/whatsapp/contacts', 'GET');
    $request->headers->set('Authorization', "Bearer {$token2}");
    $request->setUserResolver(function () use ($user2) {
        return $user2;
    });

    try {
        $controller = app(\App\Http\Controllers\Api\WhatsAppController::class);
        $response = $controller->getContacts($request);
        $data = json_decode($response->getContent(), true);

        echo "  Status: {$response->getStatusCode()}\n";
        echo '  Contacts: '.($data['total'] ?? 0)."\n";

        if (($data['total'] ?? 0) > 0) {
            echo "  ❌ ERROR: User 2 can see contacts!\n\n";
        } else {
            echo "  ✅ Correct: User 2 cannot see any contacts\n\n";
        }
    } catch (\Exception $e) {
        echo "  ❌ Error: {$e->getMessage()}\n\n";
    }
}

echo "=== Test Complete ===\n";
