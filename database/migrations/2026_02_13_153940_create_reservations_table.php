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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('store_id')->nullable()->constrained('stores')->onDelete('cascade');
            $table->string('customer_name');
            $table->string('phone');
            $table->string('email');
            $table->date('reservation_date');
            $table->integer('guest_count');
            $table->foreignId('table_id')->nullable()->constrained('tables')->nullOnDelete();
            $table->json('selected_products')->nullable()->comment('Array of product IDs pre-ordered');
            $table->enum('payment_type', ['dp', 'full'])->default('full');
            $table->enum('payment_method', ['qris'])->default('qris');
            $table->foreignId('qris_transaction_id')->nullable()->constrained('qris_transactions')->nullOnDelete();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('remaining_amount', 12, 2)->default(0);
            $table->enum('status', ['pending_payment', 'confirmed', 'completed', 'cancelled'])->default('pending_payment');
            $table->string('order_id')->unique()->comment('Generated order ID for customer reference');
            $table->string('calendar_event_id')->nullable()->comment('Google Calendar event ID');
            $table->timestamp('notified_at')->nullable();
            $table->text('cancelled_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'store_id']);
            $table->index('reservation_date');
            $table->index('status');
            $table->index('table_id');
            $table->index('qris_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
