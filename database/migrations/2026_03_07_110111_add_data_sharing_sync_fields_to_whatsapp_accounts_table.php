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
            $table->json('data_sync_settings')->nullable()->after('profile_picture_url');
            $table->timestamp('last_data_sync_at')->nullable()->after('data_sync_settings');
            $table->string('data_sync_status')->default('idle')->after('last_data_sync_at');
            $table->text('data_sync_error')->nullable()->after('data_sync_status');

            $table->index('data_sync_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropIndex(['data_sync_status']);
            $table->dropColumn([
                'data_sync_settings',
                'last_data_sync_at',
                'data_sync_status',
                'data_sync_error',
            ]);
        });
    }
};
