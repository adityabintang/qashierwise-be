<?php

/**
 * Reset all templates - delete and re-sync from Meta
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WhatsAppTemplate;

echo "=== Reset Templates ===\n\n";

$count = WhatsAppTemplate::withoutGlobalScopes()->count();
echo "Found {$count} templates in database\n";

if ($count > 0) {
    echo "Deleting all templates...\n";
    WhatsAppTemplate::withoutGlobalScopes()->delete();
    echo "✅ All templates deleted\n\n";
}

echo "Now you need to sync templates from Meta for each WhatsApp account.\n";
echo "Use the 'Sync from Meta' button in the application.\n";

echo "\n=== Reset Complete ===\n";
