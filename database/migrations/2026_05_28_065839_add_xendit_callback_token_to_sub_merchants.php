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
        Schema::table('sub_merchants', function (Blueprint $table) {
            // Xendit auto-generates a unique callback_token per sub-account when
            // its callback URL is registered (POST /callback_urls/{type}). The
            // body parameter `callback_token` is IGNORED — Xendit always returns
            // its own. Webhooks from that sub-account carry THIS token in
            // x-callback-token, so we MUST store it to verify signatures.
            // (Empirically verified 2026-05-28: custom token in body ignored.)
            $table->string('xendit_callback_token', 64)->nullable()->after('xendit_account_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sub_merchants', function (Blueprint $table) {
            $table->dropColumn('xendit_callback_token');
        });
    }
};
