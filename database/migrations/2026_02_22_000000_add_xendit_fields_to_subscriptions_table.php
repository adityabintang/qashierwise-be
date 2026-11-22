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
        Schema::table('subscriptions', function (Blueprint $table) {
            // Add Xendit-specific fields for recurring payments
            $table->string('xendit_subscription_id')->nullable()->after('midtrans_customer_id');
            $table->string('xendit_customer_id')->nullable()->after('xendit_subscription_id');

            // Add indexes for faster lookups
            $table->index('xendit_subscription_id', 'idx_subscriptions_xendit_id');
            $table->index('xendit_customer_id', 'idx_subscriptions_xendit_customer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_subscriptions_xendit_id');
            $table->dropIndex('idx_subscriptions_xendit_customer');

            // Drop columns
            $table->dropColumn([
                'xendit_subscription_id',
                'xendit_customer_id',
            ]);
        });
    }
};
