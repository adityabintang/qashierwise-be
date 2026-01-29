<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Set provider to 'midtrans' for all existing qris_transactions where provider is null
        DB::table('qris_transactions')
            ->whereNull('provider')
            ->update(['provider' => 'midtrans']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert provider back to null for transactions that were set to 'midtrans'
        DB::table('qris_transactions')
            ->where('provider', 'midtrans')
            ->update(['provider' => null]);
    }
};
