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
        Schema::create('withdrawal_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_merchant_id')->constrained('sub_merchants')->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->string('bank_code');
            $table->string('bank_account_number');
            $table->string('bank_account_name');
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->string('xendit_payout_id')->nullable();
            $table->string('reference_id')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index('sub_merchant_id');
            $table->index('status');
            $table->index('xendit_payout_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('withdrawal_requests');
    }
};
