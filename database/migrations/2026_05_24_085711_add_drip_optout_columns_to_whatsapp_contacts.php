<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->timestamp('drips_paused_until')->nullable()->after('unread_count');
            $table->timestamp('drips_unsubscribed_at')->nullable()->after('drips_paused_until');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn(['drips_paused_until', 'drips_unsubscribed_at']);
        });
    }
};
