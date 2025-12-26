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
            $table->foreignId('current_order_id')->nullable()->after('order_context')->constrained('orders')->nullOnDelete();
            $table->foreignId('current_qris_transaction_id')->nullable()->after('current_order_id')->constrained('qris_transactions')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_agent_conversations', function (Blueprint $table) {
            $table->dropForeign(['current_order_id']);
            $table->dropForeign(['current_qris_transaction_id']);
            $table->dropColumn(['current_order_id', 'current_qris_transaction_id']);
        });
    }
};
