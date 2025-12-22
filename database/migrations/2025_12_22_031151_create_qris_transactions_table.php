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
        Schema::create('qris_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_merchant_id')->constrained()->onDelete('cascade');
            $table->string('order_id', 100)->unique();
            $table->decimal('amount', 15, 2);
            $table->decimal('platform_fee', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->enum('status', ['pending', 'settlement', 'expire', 'cancel'])->default('pending');
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->text('qr_code_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index(['sub_merchant_id', 'status', 'created_at'], 'idx_merchant_transactions');
            $table->index('order_id', 'idx_order_lookup');
            $table->index('midtrans_transaction_id', 'idx_midtrans_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qris_transactions');
    }
};
