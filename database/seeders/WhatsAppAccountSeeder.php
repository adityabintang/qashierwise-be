<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\WhatsAppAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class WhatsAppAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create or get test user
        $user = User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password')
            ]
        );

        // Create WhatsApp account for the user
        WhatsAppAccount::updateOrCreate(
            ['phone_number_id' => '887698464419422'],
            [
                'user_id' => $user->id,
                'business_account_id' => '2344002079368562',
                'display_phone_number' => '15556362427',
                'access_token' => env('WHATSAPP_TOKEN', 'EAAUMwEqBDxoBOZBZBZAqbGbWR5UgpTVLbN1pIIxX5tW6V3OdUE4iY4rPvkNBJsOeZAVU2BRqtZAeGUonWj71i6vZCLgKXZANqNqg4YN8gFP9CZALh1k5ZApkZCZAq4ZC'),
                'is_active' => true,
                'verified_at' => now(),
            ]
        );

        $this->command->info('WhatsApp account created successfully!');
        $this->command->info('User email: test@example.com');
        $this->command->info('User password: password');
    }
}
