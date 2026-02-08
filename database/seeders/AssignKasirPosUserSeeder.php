<?php

namespace Database\Seeders;

use App\Models\PosUser;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssignKasirPosUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kasir = User::where('email', 'kasir1@gmail.com')->first();

        if (! $kasir) {
            $this->command->error('Kasir user not found!');

            return;
        }

        $store = Store::first();
        if (! $store) {
            $this->command->error('No store found!');

            return;
        }

        $role = Role::where('name', 'Kasir')->first();
        if (! $role) {
            $this->command->error('Kasir role not found! Run RoleSeeder first.');

            return;
        }

        $posUser = PosUser::updateOrCreate(
            ['user_id' => $kasir->id, 'store_id' => $store->id],
            ['role_id' => $role->id, 'is_active' => true]
        );

        $this->command->info('✅ Assigned kasir 1 to store: '.$store->name);
        $this->command->info('✅ Role: Kasir');
        $this->command->info('✅ Permissions: '.count($role->permissions).' permissions');
    }
}
