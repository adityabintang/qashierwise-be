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
        Schema::create('financial_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_merchant_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('admin_id')->nullable()->constrained('users')->onDelete('set null');

            // Action categorization
            $table->string('action_type', 50); // balance_update, withdrawal_request, withdrawal_approval, etc.
            $table->string('action_category', 30); // balance, withdrawal, transaction, fee

            // Reference to related entities
            $table->string('reference_type', 50)->nullable(); // qris_transaction, withdrawal_request, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_code', 100)->nullable(); // order_id, withdrawal_id, etc.

            // Financial details
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('fee_amount', 15, 2)->nullable();
            $table->decimal('balance_before', 15, 2)->nullable();
            $table->decimal('balance_after', 15, 2)->nullable();

            // Action details
            $table->string('status', 30)->nullable(); // success, failed, pending
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // Additional context data

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            // Indexes for efficient querying
            $table->index(['sub_merchant_id', 'created_at']);
            $table->index(['action_type', 'created_at']);
            $table->index(['action_category', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
            $table->index('reference_code');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financial_audit_logs');
    }
};
