<?php

namespace Database\Seeders;

use App\Models\PosUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class SyncPosUserRolesToSpatieSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Syncs all PosUser role_id assignments to actual User role assignments via Spatie.
     */
    public function run(): void
    {
        $posUsers = PosUser::with(['user', 'role'])->get();

        foreach ($posUsers as $posUser) {
            if ($posUser->user && $posUser->role) {
                // Find the Spatie role by ID
                $role = Role::find($posUser->role_id);

                if ($role) {
                    // Assign role to user with role name
                    $posUser->user->syncRoles([$role->name]);
                    $this->command->info("Assigned role '{$role->name}' to user {$posUser->user->email}");
                }
            }
        }

        $this->command->info('POS user roles synced to Spatie successfully!');
    }
}
