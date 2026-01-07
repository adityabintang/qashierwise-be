<?php

/**
 * Fix template ownership - remove templates that don't belong to correct WABA
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;

echo "=== Fix Template Ownership ===\n\n";

// Get all templates without scope
$templates = WhatsAppTemplate::withoutGlobalScopes()->get();

echo "Found {$templates->count()} templates\n\n";

foreach ($templates as $template) {
    $account = WhatsAppAccount::withoutGlobalScopes()->find($template->whatsapp_account_id);

    if (! $account) {
        echo "❌ Template '{$template->name}' has invalid account ID: {$template->whatsapp_account_id}\n";
        echo "   Deleting...\n";
        $template->delete();

        continue;
    }

    echo "Template: {$template->name} ({$template->language})\n";
    echo "  - Assigned to WABA: {$account->business_account_id}\n";
    echo "  - User: {$account->user_id}\n";

    // Ask if this is correct
    echo '  Is this correct? (y/n): ';
    $answer = trim(fgets(STDIN));

    if (strtolower($answer) !== 'y') {
        echo "  Deleting template...\n";
        $template->delete();
        echo "  ✅ Deleted\n";
    } else {
        echo "  ✅ Kept\n";
    }

    echo "\n";
}

echo "=== Fix Complete ===\n";
echo "Now sync templates from Meta for each WABA.\n";
