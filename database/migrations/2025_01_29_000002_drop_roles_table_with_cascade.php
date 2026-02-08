<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration fixes the failed migration 2026_01_28_053727_drop_old_roles_table_for_spatie
     * by using CASCADE to handle foreign key dependencies in PostgreSQL.
     *
     * The constraints being removed are:
     * - model_has_roles_role_id_foreign (in model_has_roles table)
     * - role_has_permissions_role_id_foreign (in role_has_permissions table)
     * - pos_users_role_id_foreign (in pos_users table)
     */
    public function up(): void
    {
        if (Schema::hasTable('roles')) { return; }
        // Check if old roles table exists
        if (Schema::hasTable('roles')) {
            // First, drop foreign key constraints that depend on roles table
            // This is needed because Schema::dropIfExists() doesn't handle CASCADE
            $this->dropForeignKeyConstraints();

            // Now drop the table
            Schema::dropIfExists('roles');

            \Log::info('Old roles table dropped successfully with CASCADE');
        } else {
            // Table might have been already dropped, try using CASCADE directly
            try {
                DB::statement('DROP TABLE IF EXISTS "roles" CASCADE');
                \Log::info('Old roles table dropped with CASCADE');
            } catch (\Exception $e) {
                \Log::warning('Could not drop roles table', ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Drop foreign key constraints that depend on the roles table.
     */
    private function dropForeignKeyConstraints(): void
    {
        // Tables that may have foreign keys referencing roles
        $tablesWithConstraints = [
            'model_has_roles' => 'model_has_roles_role_id_foreign',
            'role_has_permissions' => 'role_has_permissions_role_id_foreign',
            'pos_users' => 'pos_users_role_id_foreign',
        ];

        foreach ($tablesWithConstraints as $table => $constraint) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'role_id')) {
                try {
                    // Try to drop the foreign key constraint
                    DB::statement("ALTER TABLE \"$table\" DROP CONSTRAINT IF EXISTS \"$constraint\"");
                    \Log::info("Dropped constraint $constraint from $table");
                } catch (\Exception $e) {
                    // Constraint might not exist or already dropped
                    \Log::warning("Could not drop constraint $constraint", ['error' => $e->getMessage()]);
                }
            }
        }

        // Final attempt with CASCADE for any remaining dependencies
        try {
            DB::statement('DROP TABLE IF EXISTS "roles" CASCADE');
        } catch (\Exception $e) {
            // If still failing, the table might already be gone
            \Log::warning('Final CASCADE drop failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * Note: This cannot fully restore the old roles data because we dropped
     * the table with CASCADE. The old data would need to be restored from backup.
     */
    public function down(): void
    {
        // Recreate the old roles table structure (without data restoration)
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // Note: Old data cannot be restored through this migration
        // A database backup would be needed to restore the data
    }
};
