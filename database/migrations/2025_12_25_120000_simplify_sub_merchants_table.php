<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Simplify sub_merchants table by removing bank account fields (no longer needed for withdrawal).
     */
    public function up(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            // Add business_name column
            $table->string('business_name', 255)->nullable()->after('user_id');
        });

        // Copy user names to business_name for existing records
        DB::statement('UPDATE sub_merchants SET business_name = (SELECT name FROM users WHERE users.id = sub_merchants.user_id)');

        Schema::table('sub_merchants', function (Blueprint $table) {
            // Drop bank account columns if they exist
            if (Schema::hasColumn('sub_merchants', 'bank_name')) {
                $table->dropColumn('bank_name');
            }
            if (Schema::hasColumn('sub_merchants', 'account_number')) {
                $table->dropColumn('account_number');
            }
            if (Schema::hasColumn('sub_merchants', 'account_holder_name')) {
                $table->dropColumn('account_holder_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            // Re-add bank account columns
            $table->string('bank_name', 100)->nullable()->after('user_id');
            $table->string('account_number', 255)->nullable()->after('bank_name');
            $table->string('account_holder_name', 100)->nullable()->after('account_number');
            
            // Drop business_name
            $table->dropColumn('business_name');
        });
    }
};
