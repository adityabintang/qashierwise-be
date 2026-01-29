<?php

namespace Database\Seeders;

use App\Models\PosUser;
use Illuminate\Database\Seeder;

class CheckPosUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $posUsers = PosUser::with(['user', 'store', 'role'])->get();

        if ($posUsers->isEmpty()) {
            $this->command->error('❌ No POS users found!');

            return;
        }

        $this->command->info('Found '.$posUsers->count().' POS users:');
        $this->command->newLine();

        foreach ($posUsers as $posUser) {
            $this->command->info('ID: '.$posUser->id);
            $this->command->info('  User: '.$posUser->user->name.' ('.$posUser->user->email.')');
            $this->command->info('  Store: '.$posUser->store->name);
            $this->command->info('  Role: '.($posUser->role ? $posUser->role->name : 'No Role'));
            $this->command->info('  Active: '.($posUser->is_active ? 'Yes' : 'No'));
            $this->command->newLine();
        }
    }
}
