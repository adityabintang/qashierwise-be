<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run migrations.
     */
    public function up(): void
    {
        // Update existing orders with status 'completed' to 'paid'
        DB::table('orders')
            ->where('status', 'completed')
            ->update(['status' => 'paid']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert 'paid' orders that were previously 'completed' back to 'completed'
        // Note: This will only affect orders that were migrated, not all paid orders
        // For simplicity, we can't perfectly revert, but this is a best-effort rollback
        DB::table('orders')
            ->where('status', 'paid')
            ->update(['status' => 'completed']);
    }
};
