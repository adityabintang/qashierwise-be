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
        Schema::create('platform_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qris_transaction_id')->constrained()->onDelete('cascade');
            $table->decimal('fee_percentage', 5, 4)->default(2.5000);
            $table->decimal('fee_amount', 15, 2);
            $table->timestamp('collected_at')->useCurrent();
            
            // Indexes
            $table->index(['collected_at', 'fee_amount'], 'idx_fee_collection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_fees');
    }
};
