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
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            // Store BytePlus Responses API cache ID for session caching
            $table->string('cache_response_id')->nullable()->after('current_qris_transaction_id');
            // Store cache expiration timestamp
            $table->timestamp('cache_expires_at')->nullable()->after('cache_response_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            $table->dropColumn(['cache_response_id', 'cache_expires_at']);
        });
    }
};
