<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Update unique constraint on roles table to include user_id
     * for proper multi-tenant support.
     */
    public function up(): void
    {
        // Drop the existing unique constraint
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name']);
        });

        // Add new unique constraint that includes user_id
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'guard_name', 'user_id'], 'roles_name_guard_name_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new unique constraint
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_user_id_unique');
        });

        // Restore the original unique constraint
        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'guard_name']);
        });
    }
};
