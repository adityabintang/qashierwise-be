<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use App\Models\Store;
use App\Models\SubMerchant;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Seeder;

class AdminAiAgentSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'admin@qashierwise.com')->first();

        if (! $user) {
            $this->command->error('User admin@qashierwise.com tidak ditemukan. Jalankan UserSeeder terlebih dahulu.');

            return;
        }

        $whatsappAccount = $this->resolveWhatsAppAccount($user);

        if (! $whatsappAccount) {
            $this->command->error('Tidak dapat membuat WhatsApp Account. Pastikan WHATSAPP_PHONE_NUMBER_ID sudah diisi di .env');

            return;
        }

        $store = Store::where('user_id', $user->id)
            ->where('code', 'ADM-PUSAT')
            ->first();

        if (! $store) {
            $this->command->warn('Store ADM-PUSAT tidak ditemukan. Jalankan AdminDemoDataSeeder terlebih dahulu untuk menghubungkan default store.');
        }

        // Ensure a placeholder SubMerchant exists when QRIS will be enabled —
        // AiAgentController rejects qris_enabled=true if user has no SubMerchant.
        // Without this, saving the AI agent config from the dashboard would fail
        // with "QRIS Payment tidak bisa diaktifkan...".
        $subMerchant = $this->ensureSubMerchant($user);

        $systemPrompt = $this->buildSystemPrompt();

        $agent = AiAgent::updateOrCreate(
            ['whatsapp_account_id' => $whatsappAccount->id],
            [
                'default_store_id'      => $store?->id,
                'bot_name'              => 'Qashi',
                'system_prompt'         => $systemPrompt,
                'business_info'         => [
                    'operating_hours' => 'Senin - Minggu, 08.00 - 22.00 WIB',
                    'phone'           => '021-12345678',
                    'address'         => 'Jl. Sudirman No. 1, Jakarta Pusat',
                    'description'     => 'Kafe Qashierwise menyajikan makanan dan minuman berkualitas dengan suasana nyaman. Kami melayani dine-in, takeaway, dan delivery.',
                ],
                'order_enabled'         => true,
                'qris_enabled'          => true,
                'reservation_enabled'   => true,
                'delivery_enabled'      => true,
                'default_ongkir'        => 10000.00,
                'is_active'             => true,
                'use_optimized_prompt'  => true,
                'enable_prompt_caching' => true,
                'use_toon_format'       => true,
                'product_sample_limit'  => 10,
                'settings'              => [
                    'greeting_message'    => 'Halo! Selamat datang di Kafe Qashierwise 👋 Saya Qashi, asisten virtual kami. Ada yang bisa saya bantu?',
                    'language'            => 'id',
                    'currency'            => 'IDR',
                    'timezone'            => 'Asia/Jakarta',
                ],
            ]
        );

        $this->command->info('');
        $this->command->info('=== AdminAiAgentSeeder selesai ===');
        $this->command->info('User             : ' . $user->email);
        $this->command->info('WhatsApp Account : ' . ($whatsappAccount->display_phone_number ?: $whatsappAccount->phone_number_id));
        $this->command->info('Default Store    : ' . ($store?->name ?? '(tidak terhubung)'));
        $this->command->info('Sub Merchant     : ' . ($subMerchant?->business_name ?? '(tidak dibuat)') . ' [#' . ($subMerchant?->id ?? '-') . ']');
        $this->command->info('Bot Name         : ' . $agent->bot_name);
        $this->command->info('Status           : ' . ($agent->is_active ? 'Aktif' : 'Nonaktif'));
        $this->command->info('Order            : ' . ($agent->order_enabled ? 'Aktif' : 'Nonaktif'));
        $this->command->info('QRIS             : ' . ($agent->qris_enabled ? 'Aktif' : 'Nonaktif'));
        $this->command->info('Reservasi        : ' . ($agent->reservation_enabled ? 'Aktif' : 'Nonaktif'));
        $this->command->info('Delivery         : ' . ($agent->delivery_enabled ? 'Aktif' : 'Nonaktif'));
    }

    /**
     * Create a placeholder SubMerchant for the demo user if none exists yet.
     * The fields here are intentionally placeholder values — they're enough
     * to pass the controller's existence check; real bank/Xendit details
     * should be filled in by the user via the Sub Merchant dashboard.
     */
    private function ensureSubMerchant(User $user): SubMerchant
    {
        return SubMerchant::firstOrCreate(
            ['user_id' => $user->id],
            [
                'business_name'         => 'Kafe Qashierwise',
                'xendit_account_id'     => null,
                'xendit_account_status' => 'pending',
                'bank_code'             => null,
                'bank_account_number'   => null,
                'bank_account_name'     => null,
                'is_active'             => true,
                'verified_at'           => now(),
            ]
        );
    }

    private function resolveWhatsAppAccount(User $user): ?WhatsAppAccount
    {
        // Gunakan withoutGlobalScope karena seeder berjalan tanpa auth
        $existing = WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $this->command->info("Menggunakan WhatsApp Account yang sudah ada: {$existing->phone_number_id}");

            return $existing;
        }

        $phoneNumberId      = env('WHATSAPP_PHONE_NUMBER_ID');
        $accessToken        = env('WHATSAPP_ACCESS_TOKEN');
        $businessAccountId  = env('WHATSAPP_BUSINESS_ACCOUNT_ID');

        if (! $phoneNumberId) {
            $this->command->warn('WHATSAPP_PHONE_NUMBER_ID tidak ditemukan di .env. Menggunakan nilai placeholder.');
            $phoneNumberId     = 'PLACEHOLDER_PHONE_NUMBER_ID';
            $accessToken       = 'PLACEHOLDER_ACCESS_TOKEN';
            $businessAccountId = 'PLACEHOLDER_BUSINESS_ACCOUNT_ID';
        }

        // Pastikan phone_number_id belum digunakan user lain
        $conflicting = WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->where('phone_number_id', $phoneNumberId)
            ->whereNot('user_id', $user->id)
            ->exists();

        if ($conflicting) {
            $this->command->error("phone_number_id '{$phoneNumberId}' sudah digunakan oleh user lain.");

            return null;
        }

        return WhatsAppAccount::withoutGlobalScope('userAccounts')
            ->updateOrCreate(
                ['phone_number_id' => $phoneNumberId],
                [
                    'user_id'              => $user->id,
                    'business_account_id'  => $businessAccountId,
                    'waba_id'              => $businessAccountId,
                    'display_phone_number' => env('WHATSAPP_DISPLAY_PHONE_NUMBER', $phoneNumberId),
                    'name'                 => 'Kafe Qashierwise',
                    'access_token'         => $accessToken,
                    'is_active'            => true,
                ]
            );
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
Kamu adalah Qashi, asisten virtual ramah dari Kafe Qashierwise.

Tugasmu:
- Membantu pelanggan melihat menu dan memesan makanan/minuman
- Menerima pesanan dan memproses pembayaran via QRIS
- Membantu reservasi meja
- Menjawab pertanyaan seputar menu, harga, jam operasional, dan lokasi

Karakter:
- Ramah, sopan, dan profesional
- Gunakan bahasa Indonesia yang natural dan hangat
- Gunakan emoji secukupnya untuk membuat chat lebih menarik
- Jika ada pertanyaan yang tidak bisa dijawab, sarankan pelanggan menghubungi nomor telepon kafe

Batasan:
- Jangan menjawab pertanyaan di luar konteks kafe dan layanan kami
- Jangan memberikan informasi harga yang tidak ada di menu
- Selalu konfirmasi pesanan sebelum memproses pembayaran
PROMPT;
    }
}
