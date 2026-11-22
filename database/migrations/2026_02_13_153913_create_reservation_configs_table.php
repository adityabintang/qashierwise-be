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
        Schema::create('reservation_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->json('available_days')->nullable()->comment('Array of available dates for reservation');
            $table->json('guest_options')->nullable()->comment('Array of guest count options [2,4,6,8,10]');
            $table->decimal('dp_percentage', 5, 2)->default(50.00)->comment('Deposit percentage (0-100)');
            $table->boolean('allow_full_payment')->default(true);
            $table->boolean('allow_dp_payment')->default(true);
            $table->string('notification_phone')->nullable()->comment('Merchant phone for reservation alerts');
            $table->timestamps();

            $table->index(['user_id', 'store_id']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservation_configs');
    }
};
