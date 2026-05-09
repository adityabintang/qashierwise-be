<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->string('ctwa_clid')->nullable()->after('ai_active');
            $table->enum('first_source_type', ['organic', 'ad'])->nullable()->after('ctwa_clid');
            $table->string('ctwa_headline')->nullable()->after('first_source_type');
            $table->timestamp('attribution_expires_at')->nullable()->after('ctwa_headline');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contacts', function (Blueprint $table) {
            $table->dropColumn(['ctwa_clid', 'first_source_type', 'ctwa_headline', 'attribution_expires_at']);
        });
    }
};
