<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * The account_number column needs to store encrypted values which are much
     * longer than the original 50 character limit. Encrypted values using
     * Laravel's Crypt facade are base64-encoded JSON strings that can be
     * several hundred characters long.
     */
    public function up(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->text('account_number')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->string('account_number', 50)->change();
        });
    }
};
