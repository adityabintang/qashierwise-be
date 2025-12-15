<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds fields required for WhatsApp Embedded Signup v4 integration.
     * Requirements: 2.1, 2.2, 8.2
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('waba_id')->nullable()->after('business_account_id');
            $table->boolean('coexistence_enabled')->default(false)->after('is_active');
            $table->timestamp('token_expires_at')->nullable()->after('access_token');
            $table->string('connection_method')->default('manual')->after('coexistence_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'waba_id',
                'coexistence_enabled',
                'token_expires_at',
                'connection_method',
            ]);
        });
    }
};
