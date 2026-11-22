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
     * This migration adds user_id to stores table to establish proper
     * tenant isolation at the database level. Each store must belong
     * to exactly one user (the master admin who created it).
     */
    public function up(): void
    {
        // Add user_id column to stores table if it doesn't exist
        if (! Schema::hasColumn('stores', 'user_id')) {
            Schema::table('stores', function (Blueprint $table) {
                // Add user_id as foreign key with cascade delete
                // This ensures that when a user is deleted, all their stores are deleted
                $table->foreignId('user_id')
                    ->after('id')
                    ->constrained('users')
                    ->onDelete('cascade');

                // Add unique constraint for store name per user
                // This prevents a user from creating multiple stores with the same name
                // while allowing different users to have stores with the same name
                $table->unique(['user_id', 'name'], 'stores_user_name_unique');
            });
        }

        // Update existing stores to set user_id based on PosUser relationship
        // This is a best-effort migration to set user_id for existing stores
        DB::statement('
            UPDATE stores
            SET user_id = (
                SELECT pu.user_id
                FROM pos_users pu
                WHERE pu.store_id = stores.id
                LIMIT 1
            )
            WHERE user_id IS NULL
        ');

        // Set a default user (super admin) for any stores that still don't have user_id
        // This should not happen in a properly configured system
        DB::statement("
            UPDATE stores
            SET user_id = (
                SELECT id FROM users WHERE email = 'admin@qashierwise.com' LIMIT 1
            )
            WHERE user_id IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique('stores_user_name_unique');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};
