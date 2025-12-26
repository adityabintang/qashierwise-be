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
        Schema::table('qris_transactions', function (Blueprint $table) {
            // Add provider field after sub_merchant_id
            $table->string('provider', 20)->nullable()->after('sub_merchant_id');
            
            // Add provider_transaction_id after midtrans_transaction_id
            $table->string('provider_transaction_id', 100)->nullable()->after('midtrans_transaction_id');
            
            // Add index for provider-based queries
            $table->index(['provider', 'status', 'created_at'], 'idx_provider_transactions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_provider_transactions');
            $table->dropColumn(['provider', 'provider_transaction_id']);
        });
    }
};
