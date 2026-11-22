<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add guard_name column to existing roles table if it doesn't exist
        if (Schema::hasTable('roles') && !Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('guard_name')->default('sanctum')->after('name');
            });
            
            // Update existing roles to have guard_name
            DB::table('roles')->update(['guard_name' => 'sanctum']);
        }
        
        // Add guard_name column to permissions table if it doesn't exist
        if (Schema::hasTable('permissions') && !Schema::hasColumn('permissions', 'guard_name')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('guard_name')->default('sanctum')->after('name');
            });
            
            // Update existing permissions to have guard_name
            DB::table('permissions')->update(['guard_name' => 'sanctum']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('roles', 'guard_name')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
        }
        
        if (Schema::hasColumn('permissions', 'guard_name')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('guard_name');
            });
        }
    }
};
