<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin
        $superAdmin = User::firstOrCreate(
            ['email' => User::SUPER_ADMIN_EMAIL],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'is_master_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Super Admin created: ' . $superAdmin->email);
        $this->command->info('Password: password (please change after first login)');

        // Create Author user
        $author = User::firstOrCreate(
            ['email' => 'author@qashierwise.com'],
            [
                'name' => 'Content Author',
                'password' => Hash::make('password'),
                'is_master_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        // Assign author role
        if (!$author->hasRole('author')) {
            $author->assignRole('author');
        }

        $this->command->info('Author created: ' . $author->email);
        $this->command->info('Password: password (please change after first login)');
    }
}
