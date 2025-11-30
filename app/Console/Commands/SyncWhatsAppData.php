<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\WhatsAppAccount;
use App\Models\WhatsAppTemplate;

class SyncWhatsAppData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:sync {--templates : Sync templates only}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync WhatsApp data (templates, contacts, messages) from Meta API to local database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting WhatsApp data sync...');

        // Ensure WhatsApp account exists in DB
        $account = $this->ensureWhatsAppAccount();

        if (!$account) {
            $this->error('❌ Failed to create WhatsApp account');
            return 1;
        }

        $this->info("✅ WhatsApp Account: {$account->phone_number_id}");

        // Sync templates
        if ($this->option('templates') || !$this->option('templates')) {
            $this->syncTemplates($account);
        }

        $this->info('✅ Sync completed successfully!');
        return 0;
    }

    protected function ensureWhatsAppAccount()
    {
        return WhatsAppAccount::firstOrCreate(
            ['phone_number_id' => config('whatsapp.phone_number_id')],
            [
                'user_id' => 1,
                'business_account_id' => config('whatsapp.business_account_id'),
                'access_token' => config('whatsapp.access_token'),
                'is_active' => true,
            ]
        );
    }

    protected function syncTemplates($account)
    {
        $this->info('📋 Syncing templates...');

        try {
            $wabaId = config('whatsapp.business_account_id');
            $accessToken = config('whatsapp.access_token');

            $response = Http::withToken($accessToken)
                ->get("https://graph.facebook.com/v21.0/{$wabaId}/message_templates", [
                    'limit' => 100,
                    'fields' => 'name,status,category,language,components,id,quality_score'
                ]);

            if ($response->failed()) {
                $this->error('❌ Failed to fetch templates from Meta API');
                $this->error($response->body());
                return;
            }

            $templates = $response->json()['data'] ?? [];
            $count = 0;

            foreach ($templates as $templateData) {
                $components = $templateData['components'] ?? [];

                // Extract component details
                $header = null;
                $headerType = null;
                $body = null;
                $footer = null;
                $buttons = [];

                foreach ($components as $component) {
                    if ($component['type'] === 'HEADER') {
                        $headerType = $component['format'] ?? 'TEXT';
                        $header = $component['text'] ?? null;
                    } elseif ($component['type'] === 'BODY') {
                        $body = $component['text'] ?? null;
                    } elseif ($component['type'] === 'FOOTER') {
                        $footer = $component['text'] ?? null;
                    } elseif ($component['type'] === 'BUTTONS') {
                        $buttons = $component['buttons'] ?? [];
                    }
                }

                WhatsAppTemplate::updateOrCreate(
                    [
                        'whatsapp_account_id' => $account->id,
                        'name' => $templateData['name'],
                    ],
                    [
                        'status' => $templateData['status'],
                        'category' => $templateData['category'],
                        'language' => $templateData['language'],
                        'header' => $header,
                        'header_type' => $headerType,
                        'body' => $body,
                        'footer' => $footer,
                        'buttons' => !empty($buttons) ? json_encode($buttons) : null,
                        'components' => json_encode($components),
                        'quality_score' => $templateData['quality_score'] ?? null,
                    ]
                );

                $count++;
            }

            $this->info("✅ Synced {$count} templates");

        } catch (\Exception $e) {
            $this->error('❌ Error syncing templates: ' . $e->getMessage());
        }
    }
}
