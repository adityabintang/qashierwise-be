<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->boolean('delivery_enabled')->default(false)->after('reservation_enabled');
            $table->decimal('default_ongkir', 12, 2)->default(0)->after('delivery_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->dropColumn(['delivery_enabled', 'default_ongkir']);
        });
    }
};
