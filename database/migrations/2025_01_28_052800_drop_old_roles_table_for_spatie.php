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
        if (Schema::hasTable('roles')) {
            return;
        }
        // Drop foreign key constraint from pos_users table if it exists
        // Using raw SQL to avoid transaction issues in PostgreSQL
        if (Schema::hasTable('pos_users') && Schema::hasColumn('pos_users', 'role_id')) {
            DB::statement('ALTER TABLE "pos_users" DROP CONSTRAINT IF EXISTS "pos_users_role_id_foreign"');
        }

        // Drop the old roles table to make way for Spatie's roles table
        Schema::dropIfExists('roles');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recreate the old roles table structure
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('name');
            $table->json('permissions')->nullable();
            $table->timestamps();
        });

        // Re-add foreign key to pos_users
        Schema::table('pos_users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
        });
    }
};
