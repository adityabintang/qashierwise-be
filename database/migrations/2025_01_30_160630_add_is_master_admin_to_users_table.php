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
        Schema::table('users', function (Blueprint $table) {
            // Add is_master_admin if not exists
            if (! Schema::hasColumn('users', 'is_master_admin')) {
                $table->boolean('is_master_admin')->default(false)->after('language_preference');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_master_admin')) {
                $table->dropColumn('is_master_admin');
            }
        });
    }
};
