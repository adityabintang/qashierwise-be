<?php

/**
 * Script untuk test webhook incoming message
 * Jalankan: php test_webhook_message.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\WhatsAppAccount;
use App\Models\WhatsAppContact;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Http;

echo "=== WhatsApp Webhook Test ===\n\n";

// 1. Check if there's an active WhatsApp account
echo "1. Checking WhatsApp accounts...\n";
$accounts = WhatsAppAccount::where('is_active', true)->get();

if ($accounts->isEmpty()) {
    echo "   ❌ No active WhatsApp accounts found!\n";
    echo "   User needs to connect WhatsApp via Embedded Signup first.\n";
    exit(1);
}

foreach ($accounts as $account) {
    echo "   ✅ Account found:\n";
    echo "      - User ID: {$account->user_id}\n";
    echo "      - Phone Number ID: {$account->phone_number_id}\n";
    echo "      - WABA ID: {$account->waba_id}\n";
    echo "      - Display Phone: {$account->display_phone_number}\n";
}

// 2. Simulate webhook payload
echo "\n2. Simulating webhook payload...\n";

$testPhoneNumberId = $accounts->first()->phone_number_id;
$testWabaId = $accounts->first()->waba_id;

$webhookPayload = [
    'object' => 'whatsapp_business_account',
    'entry' => [
        [
            'id' => $testWabaId,
            'changes' => [
                [
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '628123456789',
                            'phone_number_id' => $testPhoneNumberId,
                        ],
                        'contacts' => [
                            [
                                'profile' => [
                                    'name' => 'Test User',
                                ],
                                'wa_id' => '6281234567890',
                            ],
                        ],
                        'messages' => [
                            [
                                'from' => '6281234567890',
                                'id' => 'wamid.test_'.time(),
                                'timestamp' => (string) time(),
                                'type' => 'text',
                                'text' => [
                                    'body' => 'Test message from webhook simulation',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];

echo "   Payload created for phone_number_id: {$testPhoneNumberId}\n";

// 3. Send test webhook to local endpoint
echo "\n3. Sending test webhook...\n";

$webhookUrl = config('app.url', 'http://localhost').'/api/whatsapp/webhook';
echo "   URL: {$webhookUrl}\n";

try {
    $response = Http::post($webhookUrl, $webhookPayload);

    echo "   Response Status: {$response->status()}\n";
    echo "   Response Body: {$response->body()}\n";

    if ($response->successful()) {
        echo "   ✅ Webhook processed successfully!\n";
    } else {
        echo "   ❌ Webhook failed!\n";
    }
} catch (\Exception $e) {
    echo "   ❌ Error: {$e->getMessage()}\n";
}

// 4. Check if contact and message were created
echo "\n4. Checking database...\n";

$contact = WhatsAppContact::where('wa_id', '6281234567890')->first();
if ($contact) {
    echo "   ✅ Contact created/found:\n";
    echo "      - ID: {$contact->id}\n";
    echo "      - Name: {$contact->name}\n";
    echo "      - User ID: {$contact->user_id}\n";
    echo "      - Last Message: {$contact->last_message_at}\n";
} else {
    echo "   ❌ Contact not found in database\n";
}

$message = WhatsAppMessage::where('message_id', 'LIKE', 'wamid.test_%')
    ->orderBy('created_at', 'desc')
    ->first();

if ($message) {
    echo "   ✅ Message created:\n";
    echo "      - ID: {$message->id}\n";
    echo "      - Message ID: {$message->message_id}\n";
    echo "      - Content: {$message->content}\n";
    echo "      - Direction: {$message->direction}\n";
} else {
    echo "   ❌ Message not found in database\n";
}

echo "\n=== Test Complete ===\n";
echo "\nChecklist untuk Mode Live:\n";
echo "1. ☐ Pastikan webhook URL di Meta: https://api.qashierwise.com/api/whatsapp/webhook\n";
echo "2. ☐ Pastikan field 'messages' sudah di-subscribe di Meta webhook\n";
echo "3. ☐ Pastikan WHATSAPP_WEBHOOK_VERIFY_TOKEN di .env sama dengan di Meta\n";
echo "4. ☐ Pastikan App Mode di Meta sudah 'Live' (bukan Development)\n";
echo "5. ☐ Pastikan phone_number_id di database sama dengan yang di Meta\n";
