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
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('qris_transaction_id')->nullable()->after('order_id')->constrained('qris_transactions')->nullOnDelete();
            $table->string('status')->default('pending')->after('amount');
            $table->timestamp('paid_at')->nullable()->after('status');
            $table->index(['order_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['order_id', 'status']);
            $table->dropForeign(['qris_transaction_id']);
            $table->dropColumn(['qris_transaction_id', 'status', 'paid_at']);
        });
    }
};
