<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No action needed - Spatie's roles table has already been created in the permission_tables migration
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Nothing to do - this migration just ensures Spatie's tables are created
    }
};
