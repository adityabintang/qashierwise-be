#!/usr/bin/env php
<?php
/**
 * Usage: php chat.php <nomor_wa> "<pesan>"
 * Contoh: php chat.php 6281234567890 "halo bang menu nya apa aja?"
 */

if ($argc < 3) {
    echo "Usage: php chat.php <nomor_wa> \"<pesan>\"\n";
    echo "Contoh: php chat.php 6281234567890 \"halo bang menu nya apa aja?\"\n";
    exit(1);
}

$waId   = trim($argv[1]);
$pesan  = trim($argv[2]);
$name   = $argc >= 4 ? trim($argv[3]) : "Test ({$waId})";

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$API_URL        = config('app.url', 'http://localhost:8000');
$ADMIN_EMAIL    = 'admin@qashierwise.com';
$ADMIN_PASSWORD = 'password';

// Login
$login = \Illuminate\Support\Facades\Http::post("{$API_URL}/api/login", [
    'email'    => $ADMIN_EMAIL,
    'password' => $ADMIN_PASSWORD,
]);

if (! $login->successful()) {
    echo "✗ Login gagal: " . $login->body() . "\n";
    exit(1);
}

$token = $login->json('data.access_token');
echo "✓ Login OK\n";

// Cari atau buat WhatsApp account
$user    = \App\Models\User::where('email', $ADMIN_EMAIL)->firstOrFail();
$account = \App\Models\WhatsAppAccount::withoutGlobalScopes()
    ->where('user_id', $user->id)
    ->first();

if (! $account) {
    $account = \App\Models\WhatsAppAccount::withoutGlobalScopes()
        ->where('phone_number_id', '1107948765724794')
        ->first();
}

if (! $account) {
    $account = \App\Models\WhatsAppAccount::create([
        'user_id'             => $user->id,
        'phone_number_id'     => '1107948765724794',
        'business_account_id' => '27207160652209568',
        'waba_id'             => '27207160652209568',
        'access_token'        => 'test_token',
        'is_active'           => true,
    ]);
}

if (! $account->is_active) {
    $account->update(['is_active' => true]);
}

echo "✓ WhatsApp account: phone_number_id={$account->phone_number_id}\n";

// Kirim 1 pesan via webhook
$messageId = 'wamid.test' . time() . rand(1000, 9999);

$payload = [
    'object' => 'whatsapp_business_account',
    'entry'  => [[
        'id'      => $account->waba_id,
        'changes' => [[
            'field' => 'messages',
            'value' => [
                'messaging_product' => 'whatsapp',
                'metadata'  => [
                    'display_phone_number' => '15551234567',
                    'phone_number_id'      => $account->phone_number_id,
                ],
                'contacts'  => [[
                    'profile' => ['name' => $name],
                    'wa_id'   => $waId,
                ]],
                'messages'  => [[
                    'from'      => $waId,
                    'id'        => $messageId,
                    'timestamp' => (string) time(),
                    'type'      => 'text',
                    'text'      => ['body' => $pesan],
                ]],
            ],
        ]],
    ]],
];

$res = \Illuminate\Support\Facades\Http::post("{$API_URL}/api/whatsapp/webhook", $payload);

if ($res->successful()) {
    echo "✓ Pesan terkirim!\n";
    echo "  Dari  : {$name} ({$waId})\n";
    echo "  Pesan : \"{$pesan}\"\n";
} else {
    echo "✗ Gagal: " . $res->body() . "\n";
    exit(1);
}
