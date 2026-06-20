#!/usr/bin/env php
<?php

/**
 * WhatsApp Message Testing Script for Super Admin
 * 
 * Script ini mensimulasikan webhook dari WhatsApp untuk testing pesan masuk
 * ke akun Super Admin tanpa perlu menghubungkan Facebook.
 * 
 * Usage:
 *   php test_whatsapp_message.php
 * 
 * Konfigurasi:
 *   - Edit TEST_CONTACTS untuk menambah/mengubah nomor HP dan nama
 *   - Edit TEST_MESSAGES untuk mengubah pesan yang dikirim
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\Http;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ============================================================================
// KONFIGURASI - EDIT BAGIAN INI UNTUK TESTING
// ============================================================================

// Daftar kontak yang akan mengirim pesan
// Format: ['wa_id' => 'nomor_wa', 'name' => 'nama_kontak']
$TEST_CONTACTS = [
    ['wa_id' => '6281234567890', 'name' => 'Customer A - Jakarta'],
    ['wa_id' => '6281234567891', 'name' => 'Customer B - Bandung'],
    ['wa_id' => '6281234567892', 'name' => 'Customer C - Surabaya'],
    ['wa_id' => '6281234567893', 'name' => 'Customer D - Medan'],
    ['wa_id' => '6281234567894', 'name' => 'Customer E - Bali'],
];

// Daftar pesan yang akan dikirim (random untuk setiap kontak)
$TEST_MESSAGES = [
    'Halo, saya ingin bertanya tentang produk Anda',
    'Apakah ada promo hari ini?',
    'Berapa harga untuk paket premium?',
    'Saya tertarik untuk berlangganan',
    'Bisa minta info lebih lanjut?',
    'Kapan bisa mulai menggunakan layanan?',
    'Apakah tersedia untuk area saya?',
    'Bagaimana cara pembayarannya?',
];

// Konfigurasi Super Admin
$SUPER_ADMIN_CONFIG = [
    'email' => 'admin@qashierwise.com',
    'password' => 'password',
    'phone_number_id' => '1107948765724794',
    'waba_id' => '27207160652209568',
];

// API Configuration
$API_URL = env('APP_URL', 'http://localhost:8000');

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function printHeader($text) {
    echo "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    echo "  " . $text . "\n";
    echo "═══════════════════════════════════════════════════════════════\n";
}

function printSuccess($text) {
    echo "✓ " . $text . "\n";
}

function printError($text) {
    echo "✗ " . $text . "\n";
}

function printInfo($text) {
    echo "ℹ " . $text . "\n";
}

function printWarning($text) {
    echo "⚠ " . $text . "\n";
}

// ============================================================================
// MAIN SCRIPT
// ============================================================================

printHeader("WhatsApp Message Testing Script - Super Admin");

printInfo("Konfigurasi:");
echo "  - Super Admin: {$SUPER_ADMIN_CONFIG['email']}\n";
echo "  - Jumlah kontak test: " . count($TEST_CONTACTS) . "\n";
echo "  - Jumlah variasi pesan: " . count($TEST_MESSAGES) . "\n";
echo "  - API URL: {$API_URL}\n";

// Step 1: Login sebagai Super Admin
printInfo("\nStep 1: Login sebagai Super Admin...");

$loginResponse = Http::post($API_URL . '/api/login', [
    'email' => $SUPER_ADMIN_CONFIG['email'],
    'password' => $SUPER_ADMIN_CONFIG['password'],
]);

if (!$loginResponse->successful()) {
    printError("Login failed!");
    echo "Response: " . $loginResponse->body() . "\n";
    printWarning("\nPastikan Super Admin sudah dibuat dengan menjalankan:");
    printInfo("  php artisan db:seed --class=UserSeeder");
    exit(1);
}

$loginData = $loginResponse->json();
$accessToken = $loginData['data']['access_token'] ?? null;

if (!$accessToken) {
    printError("No access token received!");
    echo "Response: " . json_encode($loginData, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

printSuccess("Login successful! Token: " . substr($accessToken, 0, 20) . "...");

// Step 2: Cek atau buat WhatsApp account untuk Super Admin
printInfo("\nStep 2: Memeriksa WhatsApp account...");

// Cek langsung di database
$user = \App\Models\User::where('email', $SUPER_ADMIN_CONFIG['email'])->first();

if (!$user) {
    printError("Super Admin user tidak ditemukan!");
    exit(1);
}

$account = \App\Models\WhatsAppAccount::where('user_id', $user->id)->first();

if (!$account) {
    printWarning("WhatsApp account belum ada, membuat account baru...");
    
    $account = \App\Models\WhatsAppAccount::create([
        'user_id' => $user->id,
        'phone_number_id' => $SUPER_ADMIN_CONFIG['phone_number_id'],
        'business_account_id' => $SUPER_ADMIN_CONFIG['waba_id'], // business_account_id = waba_id
        'waba_id' => $SUPER_ADMIN_CONFIG['waba_id'],
        'access_token' => 'test_token_' . time(),
        'is_active' => true,
    ]);
    
    printSuccess("WhatsApp account created! ID: " . $account->id);
    printInfo("  User ID: " . $account->user_id);
    printInfo("  Phone Number ID: " . $account->phone_number_id);
    printInfo("  Business Account ID: " . $account->business_account_id);
    printInfo("  WABA ID: " . $account->waba_id);
} else {
    printSuccess("WhatsApp account sudah ada! ID: " . $account->id);
    printInfo("  User ID: " . $account->user_id);
    printInfo("  Phone Number ID: " . $account->phone_number_id);
    printInfo("  Is Active: " . ($account->is_active ? 'Yes' : 'No'));
    
    // Pastikan account aktif
    if (!$account->is_active) {
        $account->update(['is_active' => true]);
        printSuccess("Account diaktifkan!");
    }
}

// Step 3: Kirim pesan dari setiap kontak
printHeader("Step 3: Mengirim Pesan Test");

$sentMessages = [];

foreach ($TEST_CONTACTS as $index => $contact) {
    $contactNumber = $index + 1;
    printInfo("\n[{$contactNumber}/" . count($TEST_CONTACTS) . "] Mengirim pesan dari: {$contact['name']} ({$contact['wa_id']})");
    
    // Pilih pesan random
    $message = $TEST_MESSAGES[array_rand($TEST_MESSAGES)];
    
    // Generate unique message ID
    $messageId = 'wamid.' . time() . rand(1000, 9999) . $index;
    
    // Buat webhook payload
    $webhookPayload = [
        'object' => 'whatsapp_business_account',
        'entry' => [
            [
                'id' => $SUPER_ADMIN_CONFIG['waba_id'],
                'changes' => [
                    [
                        'value' => [
                            'messaging_product' => 'whatsapp',
                            'metadata' => [
                                'display_phone_number' => '15551234567',
                                'phone_number_id' => $SUPER_ADMIN_CONFIG['phone_number_id'],
                            ],
                            'contacts' => [
                                [
                                    'profile' => [
                                        'name' => $contact['name'],
                                    ],
                                    'wa_id' => $contact['wa_id'],
                                ],
                            ],
                            'messages' => [
                                [
                                    'from' => $contact['wa_id'],
                                    'id' => $messageId,
                                    'timestamp' => (string)time(),
                                    'type' => 'text',
                                    'text' => [
                                        'body' => $message,
                                    ],
                                ],
                            ],
                        ],
                        'field' => 'messages',
                    ],
                ],
            ],
        ],
    ];
    
    // Kirim webhook
    $webhookResponse = Http::post($API_URL . '/api/whatsapp/webhook', $webhookPayload);
    
    if ($webhookResponse->successful()) {
        printSuccess("Pesan terkirim!");
        echo "   Pesan: \"" . substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '') . "\"\n";
        
        $sentMessages[] = [
            'contact' => $contact,
            'message' => $message,
            'message_id' => $messageId,
        ];
    } else {
        printError("Gagal mengirim pesan!");
        echo "   Response: " . $webhookResponse->body() . "\n";
    }
    
    // Delay kecil agar timestamp berbeda
    usleep(100000); // 0.1 detik
}

// Step 4: Verifikasi pesan tersimpan
printInfo("\nStep 4: Memverifikasi pesan tersimpan...");

sleep(1); // Wait for processing

// Cek langsung di database
$dbMessages = \App\Models\WhatsAppMessage::where('user_id', $user->id)->get();
printInfo("Pesan di database (direct query): " . $dbMessages->count());

$messagesResponse = Http::withToken($accessToken)
    ->get($API_URL . '/api/whatsapp/messages?limit=20');

if (!$messagesResponse->successful()) {
    printError("Gagal mengambil messages via API!");
    printInfo("Response: " . $messagesResponse->body());
} else {
    $messages = $messagesResponse->json()['data'] ?? [];
    printSuccess("Ditemukan " . count($messages) . " pesan via API");
}

if ($dbMessages->count() > 0 && count($messages) == 0) {
    printWarning("Pesan ada di database tapi tidak muncul via API!");
    printInfo("Kemungkinan masalah RLS (Row Level Security)");
}

// Step 5: Verifikasi contacts
printInfo("\nStep 5: Memverifikasi contacts...");

$contactsResponse = Http::withToken($accessToken)
    ->get($API_URL . '/api/whatsapp/contacts');

if (!$contactsResponse->successful()) {
    printError("Gagal mengambil contacts!");
    exit(1);
}

$contacts = $contactsResponse->json()['data'] ?? [];
printSuccess("Ditemukan " . count($contacts) . " kontak");

echo "\nDaftar Kontak:\n";
foreach ($contacts as $contact) {
    echo "  • " . $contact['name'] . " (" . $contact['wa_id'] . ")";
    echo " - " . $contact['unread_count'] . " unread";
    echo " - Last: " . ($contact['last_message_text'] ? substr($contact['last_message_text'], 0, 30) . '...' : 'N/A');
    echo "\n";
}

// Step 6: Buat sample tags untuk testing
printInfo("\nStep 6: Membuat sample tags...");

$sampleTags = [
    ['name' => 'VIP Customer', 'color' => '#a855f7'],
    ['name' => 'Hot Lead', 'color' => '#ef4444'],
    ['name' => 'Follow Up', 'color' => '#eab308'],
    ['name' => 'Interested', 'color' => '#22c55e'],
    ['name' => 'Need Info', 'color' => '#3b82f6'],
];

$createdTags = [];

foreach ($sampleTags as $tagData) {
    $tagResponse = Http::withToken($accessToken)
        ->post($API_URL . '/api/whatsapp/tags', $tagData);
    
    if ($tagResponse->successful()) {
        $tag = $tagResponse->json()['data'];
        $createdTags[] = $tag;
        printSuccess("Tag created: {$tag['name']} ({$tag['color']})");
    } else {
        // Tag mungkin sudah ada
        $errorBody = $tagResponse->json();
        if (isset($errorBody['message']) && strpos($errorBody['message'], 'already exists') !== false) {
            printInfo("Tag '{$tagData['name']}' sudah ada, skip...");
        } else {
            printWarning("Gagal membuat tag '{$tagData['name']}'");
        }
    }
}

// Step 7: Assign random tags ke beberapa contacts
if (!empty($contacts) && !empty($createdTags)) {
    printInfo("\nStep 7: Assign tags ke contacts (sample)...");
    
    // Ambil 3 kontak pertama untuk demo
    $contactsToTag = array_slice($contacts, 0, min(3, count($contacts)));
    
    foreach ($contactsToTag as $contact) {
        // Pilih 1-2 tag random
        $numTags = rand(1, 2);
        $selectedTags = array_slice($createdTags, 0, $numTags);
        $tagIds = array_column($selectedTags, 'id');
        
        $assignResponse = Http::withToken($accessToken)
            ->post($API_URL . "/api/whatsapp/contacts/{$contact['id']}/tags", [
                'tag_ids' => $tagIds,
            ]);
        
        if ($assignResponse->successful()) {
            $tagNames = implode(', ', array_column($selectedTags, 'name'));
            printSuccess("Tags assigned to {$contact['name']}: {$tagNames}");
        } else {
            printWarning("Gagal assign tags ke {$contact['name']}");
        }
    }
}

// Step 8: Test filter contacts by tag
if (!empty($createdTags)) {
    printInfo("\nStep 8: Testing filter contacts by tag...");
    
    $firstTag = $createdTags[0];
    $filterResponse = Http::withToken($accessToken)
        ->get($API_URL . "/api/whatsapp/contacts?tag_id={$firstTag['id']}");
    
    if ($filterResponse->successful()) {
        $filteredContacts = $filterResponse->json()['data'] ?? [];
        printSuccess("Filter by tag '{$firstTag['name']}': " . count($filteredContacts) . " contacts");
    }
}

// Final Summary
printHeader("Summary");

echo "\n📊 Statistik:\n";
echo "  • Total pesan dikirim: " . count($sentMessages) . "\n";
echo "  • Total kontak dibuat: " . count($contacts) . "\n";
echo "  • Total tags dibuat: " . count($createdTags) . "\n";

echo "\n🎯 Akses Dashboard:\n";
echo "  • URL: {$API_URL}/dashboard/messages\n";
echo "  • Login: {$SUPER_ADMIN_CONFIG['email']}\n";
echo "  • Password: {$SUPER_ADMIN_CONFIG['password']}\n";

echo "\n📝 Kontak yang mengirim pesan:\n";
foreach ($sentMessages as $sent) {
    echo "  • {$sent['contact']['name']} ({$sent['contact']['wa_id']})\n";
    echo "    Pesan: \"" . substr($sent['message'], 0, 60) . (strlen($sent['message']) > 60 ? '...' : '') . "\"\n";
}

echo "\n🏷️  Tags yang tersedia untuk testing:\n";
foreach ($createdTags as $tag) {
    echo "  • {$tag['name']} ({$tag['color']})\n";
}

printHeader("Testing Selesai!");

printInfo("\n💡 Tips:");
echo "  1. Buka dashboard untuk melihat pesan: {$API_URL}/dashboard/messages\n";
echo "  2. Klik pada kontak untuk melihat chat history\n";
echo "  3. Gunakan tags untuk mengorganisir kontak\n";
echo "  4. Filter kontak berdasarkan tag\n";
echo "  5. Edit variabel \$TEST_CONTACTS dan \$TEST_MESSAGES di script ini untuk testing lebih lanjut\n";

printInfo("\n🔄 Untuk mengirim pesan lagi:");
echo "  1. Edit \$TEST_CONTACTS untuk menambah/mengubah nomor HP\n";
echo "  2. Edit \$TEST_MESSAGES untuk mengubah pesan\n";
echo "  3. Jalankan: php test_whatsapp_message.php\n";

echo "\n";
