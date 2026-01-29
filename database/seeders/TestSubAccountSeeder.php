<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TestSubAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kasir = User::where('email', 'kasir1@gmail.com')->first();

        if (! $kasir) {
            $this->command->error('Kasir not found!');

            return;
        }

        $this->command->info('Testing Sub-Account Methods:');
        $this->command->newLine();

        $this->command->info('Kasir Email: '.$kasir->email);
        $this->command->info('Is Master Admin: '.($kasir->isMasterAdmin() ? 'YES' : 'NO'));
        $this->command->newLine();

        $masterAdmin = $kasir->getMasterAdmin();
        if ($masterAdmin) {
            $this->command->info('Master Admin: '.$masterAdmin->name.' ('.$masterAdmin->email.')');
            $this->command->info('Effective User ID: '.$kasir->getEffectiveUserId());
            $this->command->newLine();

            $subscription = $kasir->getEffectiveSubscription();
            if ($subscription) {
                $this->command->info('Subscription Plan: '.$subscription->plan_name);
                $this->command->info('Subscription Status: '.$subscription->status);
            } else {
                $this->command->warn('No subscription found for master admin');
            }
        } else {
            $this->command->error('Master admin not found!');
        }
    }
}
