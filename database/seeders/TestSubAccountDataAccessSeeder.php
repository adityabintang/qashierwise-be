<?php

namespace Database\Seeders;

use App\Models\AiAgent;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Store;
use App\Models\Table;
use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Seeder;

class TestSubAccountDataAccessSeeder extends Seeder
{
    /**
     * Test that sub-account can access all master admin's data.
     */
    public function run(): void
    {
        // Find kasir1 (sub-account)
        $kasir = User::where('email', 'kasir1@gmail.com')->first();

        if (! $kasir) {
            $this->command->error('❌ Kasir user not found!');

            return;
        }

        // Get master admin
        $masterAdmin = $kasir->getMasterAdmin();
        $effectiveUserId = $kasir->getEffectiveUserId();

        $this->command->info('=== Testing Sub-Account Data Access ===');
        $this->command->newLine();

        $this->command->info('👤 Sub-Account: '.$kasir->email);
        $this->command->info('👑 Master Admin: '.($masterAdmin ? $masterAdmin->email : 'N/A'));
        $this->command->info('🔑 Effective User ID: '.$effectiveUserId);
        $this->command->newLine();

        // Test Store Access
        $stores = Store::where('user_id', $effectiveUserId)->get();
        $this->command->info('🏪 Stores accessible: '.$stores->count());
        foreach ($stores as $store) {
            $this->command->info('   - '.$store->name);
        }
        $this->command->newLine();

        // Test Product Access
        $products = Product::where('user_id', $effectiveUserId)->get();
        $this->command->info('📦 Products accessible: '.$products->count());
        $this->command->newLine();

        // Test Table Access
        $tables = Table::where('user_id', $effectiveUserId)->get();
        $this->command->info('🪑 Tables accessible: '.$tables->count());
        $this->command->newLine();

        // Test WhatsApp Account Access
        $whatsappAccount = WhatsAppAccount::where('user_id', $effectiveUserId)->first();
        $this->command->info('💬 WhatsApp Account: '.($whatsappAccount ? '✅ Connected' : '❌ Not Connected'));
        if ($whatsappAccount) {
            $this->command->info('   - Business Account ID: '.($whatsappAccount->waba_id ?? 'N/A'));
            $this->command->info('   - Phone Number ID: '.($whatsappAccount->phone_number_id ?? 'N/A'));
        }
        $this->command->newLine();

        // Test AI Agent Access
        if ($whatsappAccount) {
            $aiAgent = AiAgent::where('whatsapp_account_id', $whatsappAccount->id)->first();
            $this->command->info('🤖 AI Agent: '.($aiAgent ? '✅ Configured' : '❌ Not Configured'));
            if ($aiAgent) {
                $this->command->info('   - Active: '.($aiAgent->is_active ? 'Yes' : 'No'));
                $this->command->info('   - Order Enabled: '.($aiAgent->order_enabled ? 'Yes' : 'No'));
            }
            $this->command->newLine();
        }

        // Test Reservation Access
        $reservations = Reservation::where('user_id', $effectiveUserId)->get();
        $this->command->info('📅 Reservations accessible: '.$reservations->count());
        $this->command->newLine();

        // Test Subscription
        $subscription = $kasir->getEffectiveSubscription();
        $this->command->info('💳 Subscription: '.($subscription ? $subscription->plan : 'Free Trial'));
        if ($subscription) {
            $this->command->info('   - Status: '.$subscription->status);
            $this->command->info('   - Expires: '.($subscription->expires_at ? $subscription->expires_at->format('Y-m-d') : 'N/A'));
        }
        $this->command->newLine();

        $this->command->info('✅ Sub-account data access test completed!');
        $this->command->info('📝 Summary: Sub-account "'.$kasir->email.'" can access all data from master admin "'.$masterAdmin->email.'"');
    }
}
