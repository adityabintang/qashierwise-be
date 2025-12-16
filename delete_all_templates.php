<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Http;

// Configuration
$wabaId = '2344002079368562';
$accessToken = env('WHATSAPP_ACCESS_TOKEN'); // Get from .env
$apiVersion = 'v22.0';

if (empty($accessToken)) {
    echo "ERROR: WHATSAPP_ACCESS_TOKEN not found in .env\n";
    echo "Please provide the access token as argument: php delete_all_templates.php YOUR_ACCESS_TOKEN\n";
    
    // Check if token provided as argument
    if (isset($argv[1])) {
        $accessToken = $argv[1];
    } else {
        exit(1);
    }
}

echo "=== Delete All Templates from WABA: {$wabaId} ===\n\n";

// Step 1: Get all templates
echo "Fetching templates...\n";
$response = Http::withToken($accessToken)
    ->get("https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates", [
        'limit' => 100,
        'fields' => 'name,status,language,id'
    ]);

if ($response->failed()) {
    echo "ERROR: Failed to fetch templates\n";
    echo $response->body() . "\n";
    exit(1);
}

$templates = $response->json()['data'] ?? [];
echo "Found " . count($templates) . " templates\n\n";

if (empty($templates)) {
    echo "No templates to delete.\n";
    exit(0);
}

// Step 2: Delete each template
$deleted = 0;
$failed = 0;

foreach ($templates as $template) {
    $name = $template['name'];
    $id = $template['id'] ?? null;
    
    echo "Deleting: {$name}... ";
    
    $deleteUrl = "https://graph.facebook.com/{$apiVersion}/{$wabaId}/message_templates?name=" . urlencode($name);
    if ($id) {
        $deleteUrl .= "&hsm_id=" . urlencode($id);
    }
    
    $deleteResponse = Http::withToken($accessToken)->delete($deleteUrl);
    
    if ($deleteResponse->successful()) {
        echo "OK\n";
        $deleted++;
    } else {
        $error = $deleteResponse->json()['error']['message'] ?? 'Unknown error';
        echo "FAILED: {$error}\n";
        $failed++;
    }
    
    // Small delay to avoid rate limiting
    usleep(200000); // 200ms
}

echo "\n=== Summary ===\n";
echo "Deleted: {$deleted}\n";
echo "Failed: {$failed}\n";
