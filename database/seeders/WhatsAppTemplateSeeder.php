<?php

namespace Database\Seeders;

use App\Models\WhatsAppAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $account = WhatsAppAccount::withoutGlobalScopes()->where('is_active', true)->first();

        if (! $account) {
            $this->command->error('No active WhatsApp account found. Run WhatsAppAccountSeeder first.');
            return;
        }

        $templates = [
            [
                'whatsapp_account_id' => $account->id,
                'phone_number_id'     => $account->phone_number_id,
                'template_id'         => 'tmpl_order_confirmation_001',
                'name'                => 'order_confirmation',
                'language'            => 'en',
                'category'            => 'UTILITY',
                'status'              => 'APPROVED',
                'header'              => 'Order Confirmation',
                'header_type'         => 'TEXT',
                'body'                => 'Hi {{1}}, your order #{{2}} has been confirmed! Total: {{3}}. Estimated delivery: {{4}}. Thank you for shopping with us!',
                'body_examples'       => json_encode([['John', 'ORD-12345', 'Rp 150.000', '2-3 business days']]),
                'variable_type'       => 'numeric',
                'footer'              => 'QashierWise Store',
                'buttons'             => json_encode([
                    ['type' => 'URL', 'text' => 'Track Order', 'url' => 'https://example.com/track/{{1}}'],
                ]),
                'quality_score'       => 'GREEN',
                'usage_count'         => 42,
                'approved_at'         => now()->subDays(10),
                'created_at'          => now()->subDays(10),
                'updated_at'          => now()->subDays(10),
            ],
            [
                'whatsapp_account_id' => $account->id,
                'phone_number_id'     => $account->phone_number_id,
                'template_id'         => 'tmpl_promo_flash_sale_001',
                'name'                => 'promo_flash_sale',
                'language'            => 'en',
                'category'            => 'MARKETING',
                'status'              => 'APPROVED',
                'header'              => '🔥 Flash Sale Today Only!',
                'header_type'         => 'TEXT',
                'body'                => 'Hey {{1}}! Don\'t miss our FLASH SALE — up to {{2}} off on all items. Use code *{{3}}* at checkout. Offer ends at {{4}}.',
                'body_examples'       => json_encode([['Sarah', '50%', 'FLASH50', '23:59 tonight']]),
                'variable_type'       => 'numeric',
                'footer'              => 'Unsubscribe: reply STOP',
                'buttons'             => json_encode([
                    ['type' => 'URL', 'text' => 'Shop Now', 'url' => 'https://example.com/sale'],
                    ['type' => 'QUICK_REPLY', 'text' => 'Not Interested'],
                ]),
                'quality_score'       => 'GREEN',
                'usage_count'         => 128,
                'approved_at'         => now()->subDays(5),
                'created_at'          => now()->subDays(5),
                'updated_at'          => now()->subDays(5),
            ],
            [
                'whatsapp_account_id' => $account->id,
                'phone_number_id'     => $account->phone_number_id,
                'template_id'         => null,
                'name'                => 'payment_reminder',
                'language'            => 'en',
                'category'            => 'UTILITY',
                'status'              => 'PENDING',
                'header'              => 'Payment Reminder',
                'header_type'         => 'TEXT',
                'body'                => 'Dear {{1}}, your invoice #{{2}} of {{3}} is due on {{4}}. Please complete your payment to avoid service interruption.',
                'body_examples'       => json_encode([['Alex', 'INV-9876', 'Rp 250.000', '15 May 2026']]),
                'variable_type'       => 'numeric',
                'footer'              => 'QashierWise Billing',
                'buttons'             => json_encode([
                    ['type' => 'URL', 'text' => 'Pay Now', 'url' => 'https://example.com/pay/{{1}}'],
                    ['type' => 'PHONE_NUMBER', 'text' => 'Call Support', 'phone_number' => '+628001234567'],
                ]),
                'quality_score'       => null,
                'usage_count'         => 0,
                'approved_at'         => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ],
        ];

        foreach ($templates as $template) {
            DB::table('whatsapp_templates')->updateOrInsert(
                [
                    'phone_number_id' => $template['phone_number_id'],
                    'name'            => $template['name'],
                    'language'        => $template['language'],
                ],
                $template
            );
        }

        $this->command->info('3 WhatsApp templates seeded successfully!');
        $this->command->info("  - order_confirmation (UTILITY, APPROVED)");
        $this->command->info("  - promo_flash_sale (MARKETING, APPROVED)");
        $this->command->info("  - payment_reminder (UTILITY, PENDING)");
    }
}
