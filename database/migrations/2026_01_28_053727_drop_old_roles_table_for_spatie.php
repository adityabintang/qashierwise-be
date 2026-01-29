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
        // Drop the old roles table to make way for Spatie's roles table
        Schema::dropIfExists('roles');

        // Remove user_id foreign key from pos_users if it exists
        // We'll keep role_id for backwards compatibility but roles will be managed via Spatie
        if (Schema::hasColumn('pos_users', 'user_id')) {
            // Just ensuring the column exists, no changes needed
        }
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
    }
};
