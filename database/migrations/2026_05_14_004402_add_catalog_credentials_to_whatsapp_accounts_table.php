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
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->text('catalog_access_token')->nullable()->after('access_token');
            $table->timestamp('catalog_token_expires_at')->nullable()->after('catalog_access_token');
            $table->string('catalog_business_id')->nullable()->after('catalog_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn(['catalog_access_token', 'catalog_token_expires_at', 'catalog_business_id']);
        });
    }
};
