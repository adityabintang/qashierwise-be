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
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('xendit_account_id')->nullable()->after('business_name');
            $table->string('xendit_account_status')->default('pending')->after('xendit_account_id');
            $table->string('bank_code')->nullable()->after('xendit_account_status');
            $table->string('bank_account_number')->nullable()->after('bank_code');
            $table->string('bank_account_name')->nullable()->after('bank_account_number');

            $table->index('xendit_account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->dropIndex(['xendit_account_id']);
            $table->dropColumn([
                'xendit_account_id',
                'xendit_account_status',
                'bank_code',
                'bank_account_number',
                'bank_account_name',
            ]);
        });
    }
};
