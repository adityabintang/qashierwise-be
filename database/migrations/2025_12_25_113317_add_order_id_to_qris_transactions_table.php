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
            // Note: order_id already exists as string for QRIS order ID
            // Adding linked_order_id as foreign key to orders table
            $table->foreignId('linked_order_id')->nullable()->after('sub_merchant_id')->constrained('orders')->nullOnDelete();
            $table->index('linked_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropForeign(['linked_order_id']);
            $table->dropIndex(['linked_order_id']);
            $table->dropColumn('linked_order_id');
        });
    }
};
