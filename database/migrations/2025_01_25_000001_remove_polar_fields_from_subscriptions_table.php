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
        // Drop indexes first (required for SQLite compatibility)
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'polar_subscription_id')) {
                $table->dropUnique(['polar_subscription_id']);
            }
            if (Schema::hasColumn('subscriptions', 'polar_customer_id')) {
                $table->dropIndex(['polar_customer_id']);
            }
            if (Schema::hasIndex('subscriptions', 'idx_subscriptions_provider')) {
                $table->dropIndex('idx_subscriptions_provider');
            }
        });

        // Drop columns in separate calls for SQLite compatibility
        if (Schema::hasColumn('subscriptions', 'polar_subscription_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('polar_subscription_id');
            });
        }
        if (Schema::hasColumn('subscriptions', 'polar_customer_id')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('polar_customer_id');
            });
        }
        if (Schema::hasColumn('subscriptions', 'provider')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->dropColumn('provider');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Re-add Polar-specific fields for rollback
            if (! Schema::hasColumn('subscriptions', 'polar_subscription_id')) {
                $table->string('polar_subscription_id')->nullable()->after('user_id');
            }
            if (! Schema::hasColumn('subscriptions', 'polar_customer_id')) {
                $table->string('polar_customer_id')->nullable()->after('polar_subscription_id');
            }
            if (! Schema::hasColumn('subscriptions', 'provider')) {
                $table->string('provider', 50)->default('midtrans')->after('midtrans_customer_id');
            }

            // Re-add index for provider
            if (! Schema::hasIndex('subscriptions', 'idx_subscriptions_provider')) {
                $table->index('provider', 'idx_subscriptions_provider');
            }
        });
    }
};
