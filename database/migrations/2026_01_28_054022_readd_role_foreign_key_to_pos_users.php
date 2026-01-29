<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update any pos_users with invalid role_ids to a default role
        // Get the first available role (should be Cashier from our seeder)
        $defaultRole = \Spatie\Permission\Models\Role::where('guard_name', 'sanctum')->first();

        if ($defaultRole) {
            // Update all pos_users to use the default role temporarily
            DB::table('pos_users')->update(['role_id' => $defaultRole->id]);
        } else {
            // If no roles exist, set role_id to null
            DB::table('pos_users')->whereNotNull('role_id')->update(['role_id' => null]);
        }

        // Now add the foreign key constraint
        Schema::table('pos_users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pos_users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });
    }
};
