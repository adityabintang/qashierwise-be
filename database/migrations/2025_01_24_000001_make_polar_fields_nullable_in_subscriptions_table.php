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
            // Make Polar fields nullable to support multiple providers
            $table->string('polar_subscription_id')->nullable()->change();
            $table->string('polar_customer_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Revert to NOT NULL (only if no Midtrans subscriptions exist)
            $table->string('polar_subscription_id')->nullable(false)->change();
            $table->string('polar_customer_id')->nullable(false)->change();
        });
    }
};
