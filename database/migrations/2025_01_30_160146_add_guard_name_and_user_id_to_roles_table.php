<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds guard_name column required by Spatie Permission
     * and user_id for role ownership tracking.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Add guard_name if not exists (required by Spatie)
            if (! Schema::hasColumn('roles', 'guard_name')) {
                $table->string('guard_name')->default('sanctum')->after('name');
            }

            // Add user_id for role ownership if not exists
            if (! Schema::hasColumn('roles', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('guard_name')->constrained()->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            if (Schema::hasColumn('roles', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('roles', 'guard_name')) {
                $table->dropColumn('guard_name');
            }
        });
    }
};
