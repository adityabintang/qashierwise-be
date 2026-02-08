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
            $table->foreign('sub_merchant_id')
                ->references('id')
                ->on('sub_merchants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qris_transactions', function (Blueprint $table) {
            $table->dropForeign(['sub_merchant_id']);
        });
    }
};
