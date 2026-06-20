<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No action needed - Spatie's permission tables handle roles through pivot tables
        // The pos_users.role_id is no longer needed with Spatie permissions
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do
    }
};
