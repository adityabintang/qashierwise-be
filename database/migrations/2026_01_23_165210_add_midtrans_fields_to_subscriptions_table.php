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
            // Add Midtrans-specific fields
            $table->string('midtrans_subscription_id')->nullable()->after('polar_customer_id');
            $table->string('midtrans_customer_id')->nullable()->after('midtrans_subscription_id');

            // Add provider field to distinguish between Polar and Midtrans
            $table->string('provider', 50)->default('polar')->after('midtrans_customer_id');

            // Add metadata field for additional data
            $table->json('metadata')->nullable()->after('provider');

            // Add indexes for faster lookups
            $table->index('midtrans_subscription_id', 'idx_subscriptions_midtrans_id');
            $table->index('provider', 'idx_subscriptions_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex('idx_subscriptions_midtrans_id');
            $table->dropIndex('idx_subscriptions_provider');

            // Drop columns
            $table->dropColumn([
                'midtrans_subscription_id',
                'midtrans_customer_id',
                'provider',
                'metadata',
            ]);
        });
    }
};
