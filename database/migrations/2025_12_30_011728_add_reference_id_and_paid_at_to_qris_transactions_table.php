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
        Schema::table('qris_transactions', function (Blueprint $table) {
            // Add reference_id column after provider_transaction_id
            $table->string('reference_id', 255)->nullable()->after('provider_transaction_id');

            // Add paid_at column after settled_at
            $table->timestamp('paid_at')->nullable()->after('settled_at');

            // Add index for reference_id lookups
            $table->index('reference_id', 'idx_reference_id_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_reference_id_lookup');
            $table->dropColumn(['reference_id', 'paid_at']);
        });
    }
};
